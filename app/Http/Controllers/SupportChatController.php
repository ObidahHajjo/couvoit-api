<?php

namespace App\Http\Controllers;

use App\Http\Resources\SupportChatMessageResource;
use App\Http\Resources\SupportChatSessionResource;
use App\Models\User;
use App\Services\Interfaces\SupportChatServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Support Chat', description: 'Support chat session endpoints')]
/**
 * Handles support chat session endpoints.
 */
class SupportChatController extends Controller
{
    /**
     * Create a new support chat controller instance.
     *
     * @param  SupportChatServiceInterface  $supportChat  Service for managing support chat sessions.
     */
    public function __construct(
        private readonly SupportChatServiceInterface $supportChat,
    ) {}

    #[OA\Post(
        path: '/support/sessions',
        operationId: 'createSupportSession',
        summary: 'Create support session',
        tags: ['Support Chat'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'subject', type: 'string', nullable: true),
            ])
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SupportChatSessionResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Create a new support chat session.
     *
     * @param  Request  $request  Current HTTP request.
     */
    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $session = $this->supportChat->createSession($user, $validated['subject'] ?? null);

        return (new SupportChatSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/support/sessions',
        operationId: 'listSupportSessions',
        summary: 'List support sessions',
        tags: ['Support Chat'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SupportChatSessionResource'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    /**
     * Get support chat sessions for the current user.
     *
     * @param  Request  $request  Current HTTP request.
     */
    public function getSessions(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $sessions = $user->isAdmin()
            ? $this->supportChat->getActiveSessionsForAdmin($user)
            : $this->supportChat->getUserSessions($user);

        return SupportChatSessionResource::collection($sessions);
    }

    #[OA\Get(
        path: '/support/sessions/waiting',
        operationId: 'listWaitingSessions',
        summary: 'List waiting support sessions',
        tags: ['Support Chat'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SupportChatSessionResource'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    /**
     * Get waiting support chat sessions (admin only).
     *
     * @param  Request  $request  Current HTTP request.
     */
    public function getWaitingSessions(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            abort(Response::HTTP_FORBIDDEN, 'Only admins can view waiting sessions.');
        }

        $sessions = $this->supportChat->getWaitingSessions();

        return SupportChatSessionResource::collection($sessions);
    }

    #[OA\Get(
        path: '/support/sessions/{sessionId}',
        operationId: 'getSupportSession',
        summary: 'Get support session',
        tags: ['Support Chat'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SupportChatSessionResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    /**
     * Get a specific support chat session.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $sessionId  Session identifier.
     */
    public function getSession(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $session = $this->supportChat->getSession($sessionId, $user);

        return (new SupportChatSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/support/sessions/{sessionId}/join',
        operationId: 'joinSupportSession',
        summary: 'Join support session',
        tags: ['Support Chat'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SupportChatSessionResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    /**
     * Join a support chat session.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $sessionId  Session identifier.
     */
    public function joinSession(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $session = $this->supportChat->joinSession($sessionId, $user);

        return (new SupportChatSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/support/sessions/{sessionId}/close',
        operationId: 'closeSupportSession',
        summary: 'Close support session',
        tags: ['Support Chat'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/SupportChatSessionResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    /**
     * Close a support chat session.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $sessionId  Session identifier.
     */
    public function closeSession(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $session = $this->supportChat->closeSession($sessionId, $user);

        return (new SupportChatSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/support/sessions/{sessionId}/messages',
        operationId: 'listSupportMessages',
        summary: 'List support messages',
        tags: ['Support Chat'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SupportChatMessageResource'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    /**
     * Get messages for a support chat session.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $sessionId  Session identifier.
     */
    public function getMessages(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $this->supportChat->getMessages(
            $sessionId,
            $user,
            (int) ($validated['limit'] ?? 50)
        );

        return SupportChatMessageResource::collection($messages)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/support/sessions/{sessionId}/messages',
        operationId: 'sendSupportMessage',
        summary: 'Send support message',
        tags: ['Support Chat'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\Multipart(
                properties: [
                    new OA\Property(property: 'body', type: 'string', nullable: true),
                    new OA\Property(property: 'attachments', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SupportChatMessageResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Send a message in a support chat session.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $sessionId  Session identifier.
     */
    public function sendMessage(Request $request, int $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $message = $this->supportChat->sendMessage(
            $sessionId,
            $user,
            (string) ($validated['body'] ?? ''),
            $request->file('attachments', [])
        );

        return (new SupportChatMessageResource($message))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Post(
        path: '/support/sessions/{sessionId}/read',
        operationId: 'markSupportMessagesRead',
        summary: 'Mark messages as read',
        tags: ['Support Chat'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    /**
     * Mark messages as read in a support chat session.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $sessionId  Session identifier.
     */
    public function markAsRead(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $this->supportChat->markAsRead($sessionId, $user);

        return response()->json([
            'message' => 'Messages marked as read.',
            'marked_count' => $count,
        ], Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/support/sessions/{sessionId}/typing',
        operationId: 'setTypingStatus',
        summary: 'Set typing status',
        tags: ['Support Chat'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'is_typing', type: 'boolean'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Set typing status in a support chat session.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $sessionId  Session identifier.
     */
    public function setTyping(Request $request, int $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->supportChat->setTyping($sessionId, $user, (bool) $validated['is_typing']);

        return response()->json([
            'message' => 'Typing status updated.',
        ], Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/support/presence',
        operationId: 'setPresence',
        summary: 'Set presence status',
        tags: ['Support Chat'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['online', 'away', 'offline']),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Set user presence status.
     *
     * @param  Request  $request  Current HTTP request.
     */
    public function setPresence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:online,away,offline'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $presence = $this->supportChat->setPresence($user, $validated['status']);

        return response()->json([
            'message' => 'Presence updated.',
            'data' => [
                'status' => $presence->status,
                'last_seen_at' => $presence->last_seen_at?->toISOString(),
            ],
        ], Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/support/presence',
        operationId: 'getPresence',
        summary: 'Get presence status',
        tags: ['Support Chat'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    /**
     * Get user presence status.
     *
     * @param  Request  $request  Current HTTP request.
     */
    public function getPresence(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $presence = $this->supportChat->getPresence($user);

        return response()->json([
            'data' => $presence ? [
                'status' => $presence->status,
                'last_seen_at' => $presence->last_seen_at?->toISOString(),
            ] : null,
        ], Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/support/sessions/{sessionId}/unread',
        operationId: 'getUnreadCount',
        summary: 'Get unread message count',
        tags: ['Support Chat'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    /**
     * Get unread message count for a session.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $sessionId  Session identifier.
     */
    public function getUnreadCount(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $this->supportChat->getUnreadCount($sessionId, $user);

        return response()->json([
            'unread_count' => $count,
        ], Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/support/sessions/{sessionId}/attachments/{attachmentId}',
        operationId: 'downloadSupportAttachment',
        summary: 'Download support attachment',
        tags: ['Support Chat'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    /**
     * Download an attachment from a support chat session.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $sessionId  Session identifier.
     * @param  int  $attachmentId  Attachment identifier.
     */
    public function downloadAttachment(Request $request, int $sessionId, int $attachmentId)
    {
        /** @var User $user */
        $user = $request->user();

        $attachment = $this->supportChat->findAttachmentForUser($sessionId, $attachmentId, $user);

        if ($attachment === null) {
            abort(Response::HTTP_NOT_FOUND, 'Attachment not found.');
        }

        return Storage::disk((string) $attachment->disk)->download(
            (string) $attachment->path,
            (string) $attachment->original_name,
            ['Content-Type' => (string) $attachment->mime_type]
        );
    }
}
