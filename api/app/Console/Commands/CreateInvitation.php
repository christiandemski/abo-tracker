<?php

namespace App\Console\Commands;

use App\Actions\SendInvitation;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('invite:create {email : E-mail address to invite} {--admin : The new user may invite others}')]
#[Description('Send an invitation link (needed for the very first user, since there is no admin yet)')]
class CreateInvitation extends Command
{
    public function handle(SendInvitation $sendInvitation): int
    {
        $email = (string) $this->argument('email');

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            $this->error('Invalid e-mail address.');

            return self::FAILURE;
        }

        if (User::query()->where('email', mb_strtolower($email))->exists()) {
            $this->error('A user with this e-mail address already exists.');

            return self::FAILURE;
        }

        $invitation = $sendInvitation($email, isAdmin: (bool) $this->option('admin'));

        $this->info("Invitation sent to {$invitation->email} (valid until {$invitation->expires_at->format('d.m.Y H:i')}).");

        return self::SUCCESS;
    }
}
