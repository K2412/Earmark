<?php

namespace App\Notifications\Households;

use App\Models\HouseholdInvitation as HouseholdInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HouseholdInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public HouseholdInvitationModel $invitation)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $household = $this->invitation->household;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject(__("You've been invited to join :householdName", ['householdName' => $household->name]))
            ->line(__(':inviterName has invited you to join the :householdName household.', [
                'inviterName' => $inviter->name,
                'householdName' => $household->name,
            ]))
            ->action(__('Accept invitation'), url("/invitations/{$this->invitation->code}/accept"));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'household_id' => $this->invitation->household_id,
            'household_name' => $this->invitation->household->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
