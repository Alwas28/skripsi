<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;

class RecordSentNotification
{
    public function handle(NotificationSent $event): void
    {
        NotificationLog::create([
            'user_id' => $event->notifiable instanceof User ? $event->notifiable->id : null,
            'channel' => $event->channel,
            'notification_type' => class_basename($event->notification),
            'title' => $event->notification->title ?? null,
            'status' => $event->response === false ? 'failed' : 'sent',
        ]);
    }
}
