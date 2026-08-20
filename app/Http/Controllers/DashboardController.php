<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Dashboard\GetDashboardOverview;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Identity\Entities\User as DomainUser;
use App\Http\Administration\ActiveAdministrationContext;
use App\Presentation\Formatting\DutchMoneyFormatter;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly GetDashboardOverview $dashboard,
        private readonly DutchMoneyFormatter $moneyFormatter,
    ) {}

    public function __invoke(Request $request): View
    {
        /** @var DomainUser $domainUser */
        $domainUser = $request->attributes->get('domain_user');
        /** @var ActiveAdministrationContext $administrationContext */
        $administrationContext = $request->attributes->get('administration_context');
        $today = new DateTimeImmutable('today', new DateTimeZone(config('app.timezone')));
        $periodEnd = new PostingDate($today);
        $periodStart = new PostingDate($today->modify('first day of this month'));
        $overview = $this->dashboard->execute(
            $administrationContext->administration->id(),
            $periodStart,
            $periodEnd,
            $administrationContext->administration->baseCurrency(),
        );

        return view('app.dashboard', [
            'domainUser' => $domainUser,
            'administrationContext' => $administrationContext,
            'overview' => $overview,
            'moneyFormatter' => $this->moneyFormatter,
        ]);
    }
}
