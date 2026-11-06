<?php

namespace App\Providers;

use App\Models\User;
use App\Support\FrontendUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->max(255));

        // 5 login attempts per minute per address and IP
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));

        ResetPassword::createUrlUsing(fn (User $user, string $token) => FrontendUrl::to('reset-password', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));

        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('Neues Passwort für den Abo-Tracker')
            ->greeting("Hallo {$user->name}!")
            ->line('Du hast ein neues Passwort angefordert.')
            ->action('Passwort festlegen', FrontendUrl::to('reset-password', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]))
            ->line('Der Link gilt '.config('auth.passwords.users.expire').' Minuten.')
            ->line('Wenn du das nicht warst, kannst du diese Mail ignorieren. Dein Passwort bleibt dann unverändert.'));

        VerifyEmail::toMailUsing(fn (User $user, string $url) => (new MailMessage)
            ->subject('Bitte bestätige deine E-Mail-Adresse')
            ->greeting("Hallo {$user->name}!")
            ->line('Bitte bestätige deine Adresse, damit Erinnerungen an Kündigungsfristen bei dir ankommen.')
            ->action('Adresse bestätigen', $url)
            ->line('Wenn du kein Konto angelegt hast, kannst du diese Mail ignorieren.'));
    }
}
