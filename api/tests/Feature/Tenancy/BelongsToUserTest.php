<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Note;
use Tests\Fixtures\NotePolicy;

/*
 * Reference test for tenant isolation. Every new endpoint on a user-owned model
 * gets a test like the HTTP cases below: user B must not read or change user A's data.
 */

beforeEach(function () {
    Schema::create('test_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->timestamps();
    });

    Gate::policy(Note::class, NotePolicy::class);

    Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/test-notes')->group(function () {
        Route::get('/', fn () => Note::query()->get(['id', 'title']));
        Route::get('/{note}', function (Note $note) {
            Gate::authorize('view', $note);

            return $note->only('id', 'title');
        });
        Route::patch('/{note}', function (Note $note) {
            Gate::authorize('update', $note);
            $note->update(request()->only('title'));

            return $note->only('id', 'title');
        });
        Route::delete('/{note}', function (Note $note) {
            Gate::authorize('delete', $note);
            $note->delete();

            return response()->noContent();
        });
    });

    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();

    $this->actingAs($this->alice);
    $this->aliceNote = Note::create(['title' => 'Alice only']);
    auth()->forgetUser();
});

it('assigns the authenticated user on create', function () {
    expect($this->aliceNote->user_id)->toBe($this->alice->id);
});

it('scopes queries to the authenticated user', function () {
    $this->actingAs($this->bob);

    expect(Note::query()->count())->toBe(0)
        ->and(Note::find($this->aliceNote->id))->toBeNull();
});

it('returns no rows without an authenticated user', function () {
    expect(Note::query()->count())->toBe(0);
});

it('lets user B list nothing of user A', function () {
    Sanctum::actingAs($this->bob);

    $this->getJson('/api/v1/test-notes')->assertOk()->assertExactJson([]);
});

it('lets user B neither read, change nor delete user A\'s note', function () {
    Sanctum::actingAs($this->bob);
    $id = $this->aliceNote->id;

    $this->getJson("/api/v1/test-notes/{$id}")->assertNotFound();
    $this->patchJson("/api/v1/test-notes/{$id}", ['title' => 'hacked'])->assertNotFound();
    $this->deleteJson("/api/v1/test-notes/{$id}")->assertNotFound();

    expect(Note::withoutGlobalScopes()->find($id)->title)->toBe('Alice only');
});

it('lets the owner read and change the note', function () {
    Sanctum::actingAs($this->alice);
    $id = $this->aliceNote->id;

    $this->getJson("/api/v1/test-notes/{$id}")->assertOk()->assertJson(['title' => 'Alice only']);
    $this->patchJson("/api/v1/test-notes/{$id}", ['title' => 'Updated'])->assertOk();
});

it('denies foreign models via the policy even without the scope', function () {
    $note = Note::withoutGlobalScopes()->find($this->aliceNote->id);

    expect(Gate::forUser($this->bob)->allows('view', $note))->toBeFalse()
        ->and(Gate::forUser($this->bob)->allows('update', $note))->toBeFalse()
        ->and(Gate::forUser($this->alice)->allows('update', $note))->toBeTrue();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/v1/test-notes')->assertUnauthorized();
});
