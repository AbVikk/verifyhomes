<?php

namespace App\Notifications;

use App\Models\Occupancy;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OccupancyPaymentReminder extends Notification
{
    use Queueable;

    public function __construct(
        public Occupancy $occupancy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $propertyTitle = $this->occupancy->property?->title ?? 'your property';
        $dueDate = $this->occupancy->computedNextPaymentDueAt();

        $subject = $dueDate
            ? "Rent reminder for {$propertyTitle}"
            : 'Rent payment reminder';

        $message = (new MailMessage())
            ->subject($subject)
            ->greeting('Hello '.$notifiable->name.',')
            ->line("This is a reminder about your rent payment for {$propertyTitle}.");

        if ($dueDate) {
            $message->line('Next due date: '.$dueDate->format('M j, Y').'.');
        }

        return $message
            ->line('If you have already paid, you can ignore this message.')
            ->line('Need help? Reply to this email so our team can assist.');
    }
}
