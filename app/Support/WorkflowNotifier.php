<?php

namespace App\Support;

use App\Mail\WorkflowNotificationMail;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class WorkflowNotifier
{
    public function notify(
        User $recipient,
        string $eventKey,
        string $title,
        string $body,
        ?string $link,
        string $category = 'general',
        ?string $actionLabel = null,
    ): bool {
        if (! Schema::hasTable('user_notifications')) {
            $this->sendEmail($recipient, $eventKey, $title, $body, $link, $actionLabel);

            return false;
        }

        $notification = UserNotification::query()->firstOrCreate(
            ['user_id' => $recipient->getKey(), 'event_key' => $eventKey],
            [
                'title' => $title,
                'body' => $body,
                'category' => $category,
                'link' => $link,
            ],
        );

        if (! $notification->wasRecentlyCreated) {
            return false;
        }

        $this->sendEmail($recipient, $eventKey, $title, $body, $link, $actionLabel);

        return true;
    }

    private function sendEmail(User $recipient, string $eventKey, string $title, string $body, ?string $link, ?string $actionLabel): void
    {
        $email = trim((string) $recipient->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            Mail::to($recipient)->send(new WorkflowNotificationMail(
                subjectLine: $title,
                heading: $title,
                messageText: $body,
                actionUrl: $link,
                actionLabel: $actionLabel ?? 'View update',
            ));
        } catch (\Throwable $throwable) {
            // Delivery is secondary to the completed workflow and must never roll it back.
            Log::warning('Workflow notification email could not be delivered.', [
                'event_key' => $eventKey,
                'recipient_id' => $recipient->getKey(),
                'exception' => $throwable::class,
            ]);
        }
    }
}
