<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contact\SendContactEmailRequest;
use App\Models\User;
use App\Services\Interfaces\ContactEmailServiceInterface;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Support', description: 'Customer support contact endpoints')]
/**
 * Handles customer support contact endpoints.
 */
class SupportController extends Controller
{
    /**
     * Create a new support controller instance.
     *
     * @param  ContactEmailServiceInterface  $contactEmail  Service for sending contact emails.
     */
    public function __construct(
        private readonly ContactEmailServiceInterface $contactEmail,
    ) {}

    #[OA\Post(
        path: '/support/email',
        operationId: 'sendSupportEmail',
        summary: 'Send support email',
        tags: ['Support'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SendContactEmailRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Send a support email from the authenticated user.
     *
     * @param  SendContactEmailRequest  $request  Validated request payload.
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
