<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Persistent in-app notifications, plus email when a real mailer is configured (MAIL_MAILER other than log/array).
 * `emailed_at` is set only after the mailer accepted the message; it stays null otherwise.
 */
class Notifier
{
    public static function send(?string $userId, string $type, string $title, string $body = '', ?string $url = null): void
    {
        if (! $userId) {
            return;
        }
        $n = UserNotification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'body' => $body, 'url' => $url, 'read_at' => null, 'emailed_at' => null]);

        if (self::emailConfigured() && ($user = User::find($userId)) && $user->email) {
            try {
                Mail::to($user->email)->send(new NotificationMail($title, $body, $url));
                $n->update(['emailed_at' => now()]);
            } catch (Throwable $e) {
                report($e); // in-app notification already stored; email failure is not hidden: emailed_at stays null
            }
        }
    }

    public static function emailConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }
}
