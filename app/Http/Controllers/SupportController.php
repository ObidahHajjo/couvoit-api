<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contact\SendContactEmailRequest;
use App\Models\User;
use App\Services\Interfaces\ContactEmailServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles customer support contact endpoints.
 */
class SupportController extends Controller
{
    /**
     * Create a new support controller instance.
     */
    public function __construct(
        private readonly ContactEmailServiceInterface $contactEmail,
    ) {}

    /**
     * Send a support email from the authenticated user.
     */
    public function sendSupportEmail(SendContactEmailRequest $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $validated = $request->validated();

        $this->contactEmail->sendSupportEmail(
            $authUser->person,
            (string) $validated['subject'],
            $validated['message'] ?? null,
            $request->file('attachments', [])
        );

        return response()->json([
            'message' => 'Your message has been sent to support.',
        ], Response::HTTP_CREATED);
    }
}
