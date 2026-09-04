<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Application\Banking\AdministrationBankAccountRepository;
use App\Application\Banking\AssessBankImportPreview;
use App\Application\Banking\BankImportArtifactStorage;
use App\Application\Banking\BankImportSourceRepository;
use App\Application\Banking\BankStatementParser;
use App\Application\Banking\BankStatementParseStatus;
use App\Application\Banking\ConfirmBankImport;
use App\Application\Banking\ConfirmBankImportStatus;
use App\Application\Banking\StoreBankImportArtifact;
use App\Application\Banking\StoreBankImportArtifactStatus;
use App\Application\Banking\StoredBankImportArtifact;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\BankStatementId;
use App\Domain\Banking\ValueObjects\OriginalFileHash;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\BankImportPreviewRequest;
use App\Http\Requests\Banking\ConfirmBankImportRequest;
use App\Presentation\Formatting\DutchMoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class BankImportController extends Controller
{
    private const string SESSION_PREFIX = 'bank_import_preview.';

    public function __construct(
        private readonly AdministrationBankAccountRepository $accounts,
        private readonly AssessBankImportPreview $assessPreview,
        private readonly StoreBankImportArtifact $artifacts,
        private readonly BankImportArtifactStorage $storage,
        private readonly BankStatementParser $parser,
        private readonly ConfirmBankImport $confirm,
        private readonly BankImportSourceRepository $sources,
        private readonly DutchMoneyFormatter $money,
    ) {}

    public function create(Request $request): View
    {
        $context = $this->context($request);

        return view('banking.import.create', $this->viewData($context) + [
            'bankAccounts' => array_values(array_filter($this->accounts->findForAdministration($context->administration->id()), static fn ($account): bool => $account->isActive() && $account->currency()->code() === 'EUR')),
        ]);
    }

    public function preview(BankImportPreviewRequest $request): View|RedirectResponse
    {
        $context = $this->context($request);
        $data = $request->validated();
        try {
            $accountId = new AdministrationBankAccountId(new Uuid($data['bank_account_id']));
        } catch (InvalidArgumentException) {
            abort(404);
        }
        $account = $this->accounts->find($context->administration->id(), $accountId);
        abort_if($account === null, 404);
        $bytes = $request->file('file')?->get();
        if (! is_string($bytes)) {
            return back()->withErrors(['file' => 'Het bestand kon niet veilig worden gelezen.']);
        }
        $stored = $this->artifacts->execute($bytes);
        if ($stored->status !== StoreBankImportArtifactStatus::Success || $stored->artifact === null) {
            return back()->withErrors(['file' => 'Het bestand kon niet in de private quarantaine worden opgeslagen.']);
        }
        $parsed = $this->parser->parse($bytes, $account->iban()->value());
        if ($parsed->status !== BankStatementParseStatus::Success) {
            $this->storage->deleteTemporary($stored->artifact->storageKey);

            return back()->withErrors(['file' => $this->parseMessage($parsed->status)]);
        }
        $assessment = $this->assessPreview->execute($context->administration->id(), $accountId, $parsed);
        if ($assessment !== ConfirmBankImportStatus::Success) {
            $this->storage->deleteTemporary($stored->artifact->storageKey);

            return back()->withErrors(['file' => $this->confirmMessage($assessment)]);
        }
        $token = bin2hex(random_bytes(32));
        $request->session()->put(self::SESSION_PREFIX.$token, [
            'administration_id' => $context->administration->id()->toString(),
            'user_id' => $context->user->id()->toString(),
            'bank_account_id' => $accountId->toString(),
            'storage_key' => $stored->artifact->storageKey,
            'hash' => $stored->artifact->hash->value,
            'size' => $stored->artifact->byteSize,
            'expires_at' => now()->addMinutes(15)->getTimestamp(),
        ]);

        return view('banking.import.preview', $this->viewData($context) + compact('account', 'parsed', 'token'));
    }

    public function confirm(ConfirmBankImportRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $token = $request->validated('preview_token');
        $preview = $request->session()->pull(self::SESSION_PREFIX.$token);
        if (! is_array($preview) || $preview['administration_id'] !== $context->administration->id()->toString() || $preview['user_id'] !== $context->user->id()->toString() || $preview['expires_at'] < now()->getTimestamp()) {
            if (is_array($preview) && isset($preview['storage_key'])) {
                $this->storage->deleteTemporary((string) $preview['storage_key']);
            }

            return redirect()->route('banking.import.create')->with('error', 'De importpreview is verlopen of ongeldig. Upload het bestand opnieuw.');
        }
        try {
            $accountId = new AdministrationBankAccountId(new Uuid($preview['bank_account_id']));
            $artifact = new StoredBankImportArtifact($preview['storage_key'], new OriginalFileHash($preview['hash']), (int) $preview['size']);
            $account = $this->accounts->find($context->administration->id(), $accountId);
            $bytes = $this->storage->read($artifact->storageKey);
            if ($account === null || $bytes === null || ! hash_equals($artifact->hash->value, hash('sha256', $bytes))) {
                return redirect()->route('banking.import.create')->with('error', 'De server-side importpreview kon niet veilig worden herladen.');
            }
            $parsed = $this->parser->parse($bytes, $account->iban()->value());
            $result = $this->confirm->execute($context->administration->id(), $accountId, $parsed, $artifact, $context->user->id());
        } catch (InvalidArgumentException) {
            return redirect()->route('banking.import.create')->with('error', 'De server-side importpreview is ongeldig.');
        } finally {
            $this->storage->deleteTemporary((string) ($preview['storage_key'] ?? ''));
        }
        if ($result->status !== ConfirmBankImportStatus::Success || $result->batchId === null) {
            return redirect()->route('banking.import.create')->with('error', $this->confirmMessage($result->status));
        }

        return redirect()->route('banking.import.batches.show', $result->batchId->toString())->with('status', 'Bankafschrift succesvol geïmporteerd. Er zijn nog geen financiële boekingen gemaakt.');
    }

    public function batches(Request $request): View
    {
        $context = $this->context($request);

        return view('banking.import.batches', $this->viewData($context) + ['batches' => $this->sources->list($context->administration->id())]);
    }

    public function batch(Request $request, string $batch): View
    {
        $context = $this->context($request);
        $entity = $this->sources->find($context->administration->id(), $this->batchId($batch));
        abort_if($entity === null, 404);

        return view('banking.import.batch', $this->viewData($context) + ['batch' => $entity]);
    }

    public function statement(Request $request, string $statement): View
    {
        $context = $this->context($request);
        $statementId = $this->statementId($statement);
        foreach ($this->sources->list($context->administration->id()) as $batch) {
            foreach ($batch->statements as $item) {
                if ($item->id->equals($statementId)) {
                    return view('banking.import.statement', $this->viewData($context) + compact('batch', 'item'));
                }
            }
        }
        abort(404);
    }

    private function parseMessage(BankStatementParseStatus $status): string
    {
        return match ($status) {
            BankStatementParseStatus::UnsupportedFormat => 'Alleen CAMT.053 .02 en .08 XML-bestanden worden ondersteund; ZIP is niet toegestaan.',
            BankStatementParseStatus::UnsupportedVersion => 'Deze CAMT-versie wordt niet ondersteund.',
            BankStatementParseStatus::UnsupportedCurrency => 'Alleen EUR-bankafschriften worden ondersteund.',
            BankStatementParseStatus::BankAccountMismatch => 'Het rekeningnummer in het bestand hoort niet bij de geselecteerde bankrekening.',
            BankStatementParseStatus::MalformedFile => 'Het XML-bestand is ongeldig of onvolledig.',
            BankStatementParseStatus::SecurityViolation => 'Het bestand is wegens een XML-beveiligingsrisico geweigerd.',
            default => 'Het bestand voldoet niet aan het vereiste CAMT-integriteitscontract.',
        };
    }

    private function confirmMessage(ConfirmBankImportStatus $status): string
    {
        return match ($status) {
            ConfirmBankImportStatus::DuplicateBatch => 'Dit bestand is al geïmporteerd.',
            ConfirmBankImportStatus::DuplicateStatement => 'Een afschrift uit dit bestand bestaat al.',
            ConfirmBankImportStatus::DuplicateEntry => 'Een bankmutatie uit dit bestand bestaat al.',
            ConfirmBankImportStatus::BankAccountMismatch => 'De bankrekening komt niet overeen met het afschrift.',
            ConfirmBankImportStatus::StatementBalanceMismatch => 'Beginstand, mutaties en eindstand sluiten niet op elkaar aan.',
            ConfirmBankImportStatus::MissingStatementBalance => 'Het afschrift bevat geen volledige begin- en eindstand.',
            ConfirmBankImportStatus::UnsupportedCurrency => 'Alleen EUR-bankafschriften worden ondersteund.',
            ConfirmBankImportStatus::StorageFailure => 'De private bronopslag kon niet betrouwbaar worden afgerond.',
            ConfirmBankImportStatus::ConcurrencyConflict => 'De import is gelijktijdig gewijzigd. Controleer de bestaande imports.',
            default => 'De import kon wegens een integriteitsfout niet worden bevestigd.',
        };
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function viewData(ActiveAdministrationContext $context): array
    {
        return ['domainUser' => $context->user, 'administrationContext' => $context, 'moneyFormatter' => $this->money];
    }

    private function batchId(string $value): BankImportBatchId
    {
        try {
            return new BankImportBatchId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function statementId(string $value): BankStatementId
    {
        try {
            return new BankStatementId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
