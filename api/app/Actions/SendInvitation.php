<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SendInvitation
{
    /**
     * Replaces any open invitation for the address, so only the newest link works.
     */
    public function __invoke(string $email, ?User $invitedBy = null, bool $isAdmin = false): Invitation
    {
        $email = Str::lower(trim($email));

        [$invitation, $plainToken] = DB::transaction(function () use ($email, $invitedBy, $isAdmin) {
            Invitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

            return Invitation::issue($email, $invitedBy, $isAdmin);
        });

        Notification::route('mail', $email)
            ->notify(new InvitationNotification($invitation, $plainToken));

        return $invitation;
    }
}
