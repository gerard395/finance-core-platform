<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\DocumentMailMessage;
use App\Application\Sales\DocumentMailTransport;
use App\Application\Sales\DocumentMailTransportResult;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class LaravelDocumentMailTransport implements DocumentMailTransport
{
    public function send(DocumentMailMessage $message): DocumentMailTransportResult
    {
        try {
            Mail::raw($message->body, function ($mail) use ($message): void {
                $mail->to($message->toEmail, $message->toName)->from($message->fromEmail, $message->fromName)->subject($message->subject);
                if ($message->replyTo !== null) {
                    $mail->replyTo($message->replyTo);
                }
                $mail->attachData($message->attachmentBytes, $message->attachmentFilename, ['mime' => $message->mimeType]);
            });
        } catch (TransportExceptionInterface) {
            return DocumentMailTransportResult::failed('transport_temporary', true);
        }

        return DocumentMailTransportResult::accepted();
    }

    public function identifier(): string
    {
        return 'laravel:'.(string) config('mail.default');
    }
}
