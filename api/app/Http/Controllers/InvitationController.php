<?php

namespace App\Http\Controllers;

use App\Actions\SendInvitation;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, SendInvitation $sendInvitation): JsonResponse
    {
        $invitation = $sendInvitation($request->validated('email'), $request->user());

        return InvitationResource::make($invitation)->response()->setStatusCode(201);
    }

    /**
     * Public: lets the registration screen check a link before showing the form.
     */
    public function show(string $token): InvitationResource
    {
        $invitation = Invitation::findValidByToken($token);

        abort_if($invitation === null, 404, 'Die Einladung ist ungültig oder abgelaufen.');

        return InvitationResource::make($invitation);
    }
}
