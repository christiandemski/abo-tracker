<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Support\FrontendUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    public function __construct(
        public readonly Invitation $invitation,
        #[\SensitiveParameter] public readonly string $plainToken,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->invitation->inviter?->name;
        $url = FrontendUrl::to('register', ['token' => $this->plainToken]);

        return (new MailMessage)
            ->subject('Deine Einladung zum Cost-Tracker')
            ->greeting('Hallo!')
            ->line($inviter
                ? "{$inviter} hat dich zum Cost-Tracker eingeladen."
                : 'Du bist zum Cost-Tracker eingeladen.')
            ->line('Damit behältst du deine Abos, Fixkosten und Kündigungsfristen im Blick.')
            ->action('Konto anlegen', $url)
            ->line('Der Link gilt bis '.$this->invitation->expires_at->format('d.m.Y, H:i').' Uhr.')
            ->line('Wenn du damit nichts anfangen kannst, ignorier diese Mail einfach.');
    }
}
