<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssignmentRescheduled extends Notification
{
    public function __construct(public Assignment $assignment) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'assignment_id' => $this->assignment->id,
            'title' => $this->assignment->title,
            'new_due_date' => $this->assignment->due_at
        ];
    }
}
