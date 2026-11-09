<?php

namespace App\Notifications\Channels;

use App\Notifications\ThesisUpdate;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Sends through an HTTP WhatsApp gateway (Fonnte's API by default). Returns
 * false on failure so the delivery log records it without stopping anything.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, ThesisUpdate $notification): bool
    {
        try {
            $response = Http::withHeaders(['Authorization' => config('services.whatsapp.token')])
                ->asForm()
                ->timeout(10)
                ->post(config('services.whatsapp.url'), [
                    'target' => $notifiable->whatsappNumber(),
                    'message' => $notification->toWhatsapp($notifiable),
                    'countryCode' => '62',
                ])
                ->throw();

            if ($response->json('status') === false) {
                throw new RuntimeException('WhatsApp gateway menolak pesan: '.$response->json('reason'));
            }

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
