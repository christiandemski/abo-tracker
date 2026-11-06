<?php

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Notification::fake();
    config(['app.frontend_url' => 'https://abo.codekorb.de']);
});

/** Returns the plain token from the faked mail link. */
function sentInvitationToken(string $email): string
{
    $token = null;

    Notification::assertSentTo(
        new AnonymousNotifiable,
        InvitationNotification::class,
        function (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token) {
            if ($notifiable->routes['mail'] !== $email) {
                return false;
            }
            $token = $notification->plainToken;

            return true;
        }
    );

    return $token;
}

describe('invite:create', function () {
    it('sends an admin invitation and stores only the token hash', function () {
        $this->artisan('invite:create', ['email' => 'Chris@Example.com', '--admin' => true])
            ->assertSuccessful();

        $invitation = Invitation::sole();
        $token = sentInvitationToken('chris@example.com');

        expect($invitation->email)->toBe('chris@example.com')
            ->and($invitation->is_admin)->toBeTrue()
            ->and($invitation->token_hash)->toBe(hash('sha256', $token))
            ->and($invitation->token_hash)->not->toBe($token)
            ->and($invitation->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)))->toBeTrue();
    });

    it('builds the link to the web app register screen', function () {
        $this->artisan('invite:create', ['email' => 'chris@example.com'])->assertSuccessful();
        $token = sentInvitationToken('chris@example.com');

        Notification::assertSentTo(new AnonymousNotifiable, InvitationNotification::class,
            function (InvitationNotification $n) use ($token) {
                return $n->toMail(new AnonymousNotifiable)->actionUrl
                    === 'https://abo.codekorb.de/register?token='.$token;
            });
    });

    it('replaces an open invitation for the same address', function () {
        $this->artisan('invite:create', ['email' => 'chris@example.com'])->assertSuccessful();
        $this->artisan('invite:create', ['email' => 'chris@example.com'])->assertSuccessful();

        expect(Invitation::count())->toBe(1);
    });

    it('refuses existing users and invalid addresses', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('invite:create', ['email' => 'taken@example.com'])->assertFailed();
        $this->artisan('invite:create', ['email' => 'not-an-email'])->assertFailed();

        Notification::assertNothingSent();
    });
});

describe('POST /invitations', function () {
    it('lets an admin invite someone', function () {
        Sanctum::actingAs($admin = User::factory()->admin()->create(['name' => 'Chris']));

        $this->postJson('/api/v1/invitations', ['email' => 'friend@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'friend@example.com')
            ->assertJsonMissingPath('data.token_hash');

        $invitation = Invitation::sole();
        expect($invitation->invited_by)->toBe($admin->id)
            ->and($invitation->is_admin)->toBeFalse();
        sentInvitationToken('friend@example.com');
    });

    it('forbids non-admins', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/invitations', ['email' => 'friend@example.com'])->assertForbidden();
        expect(Invitation::count())->toBe(0);
    });

    it('requires authentication', function () {
        $this->postJson('/api/v1/invitations', ['email' => 'friend@example.com'])->assertUnauthorized();
    });

    it('validates the address', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/invitations', ['email' => 'nope'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/invitations', ['email' => 'Taken@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    });
});

describe('GET /invitations/{token}', function () {
    it('returns the invited address for a valid token', function () {
        [, $token] = Invitation::issue('friend@example.com');

        $this->getJson("/api/v1/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.email', 'friend@example.com');
    });

    it('rejects unknown, expired and accepted tokens', function () {
        $this->getJson('/api/v1/invitations/unknown')->assertNotFound();

        [$expired, $expiredToken] = Invitation::issue('a@example.com');
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->getJson("/api/v1/invitations/{$expiredToken}")->assertNotFound();

        [$accepted, $acceptedToken] = Invitation::issue('b@example.com');
        $accepted->forceFill(['accepted_at' => now()])->save();
        $this->getJson("/api/v1/invitations/{$acceptedToken}")->assertNotFound();
    });
});
