<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationSender;
use Throwable;

/**
 * A step in the thesis workflow that someone should know about or act on.
 */
class ThesisUpdate extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public string $url,
    ) {}

    /**
     * Send to the recipients, minus whoever just performed the action. A
     * delivery failure is logged and never interrupts the workflow step.
     *
     * @param  User|iterable<int, User|null>|null  $recipients
     */
    public static function send(User|iterable|null $recipients, string $title, string $message, string $url): void
    {
        $users = collect($recipients instanceof User ? [$recipients] : $recipients)
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->is(auth()->user()));

        foreach ($users as $user) {
            try {
                NotificationSender::send($user, new self($title, $message, $url));
            } catch (Throwable $exception) {
                report($exception);

                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'delivery',
                    'notification_type' => class_basename(self::class),
                    'title' => $title,
                    'status' => 'failed',
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('services.thesis_notifications.email')) {
            $channels[] = 'mail';
        }

        if (config('services.whatsapp.enabled') && config('services.whatsapp.token') && $notifiable->whatsappNumber()) {
            $channels[] = 'whatsapp';
        }

        return $channels;
    }

    /**
     * @return array{title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "*{$this->title}*\n{$this->message}\n".url($this->url);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action('Buka di Sistem', url($this->url));
    }
}
