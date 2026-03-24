<?php

namespace App\Http\Controllers;

use App\Http\Resources\SupportChatMessageResource;
use App\Http\Resources\SupportChatSessionResource;
use App\Models\User;
use App\Services\Interfaces\SupportChatServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;

class SupportChatController extends Controller
{
    public function __construct(
        private readonly SupportChatServiceInterface $supportChat,
    ) {}

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

    public function getSessions(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $sessions = $user->isAdmin()
            ? $this->supportChat->getActiveSessionsForAdmin($user)
            : $this->supportChat->getUserSessions($user);

        return SupportChatSessionResource::collection($sessions);
    }

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

    public function getSession(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $session = $this->supportChat->getSession($sessionId, $user);

        return (new SupportChatSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function joinSession(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $session = $this->supportChat->joinSession($sessionId, $user);

        return (new SupportChatSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function closeSession(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $session = $this->supportChat->closeSession($sessionId, $user);

        return (new SupportChatSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

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

    public function getUnreadCount(Request $request, int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $this->supportChat->getUnreadCount($sessionId, $user);

        return response()->json([
            'unread_count' => $count,
        ], Response::HTTP_OK);
    }

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
