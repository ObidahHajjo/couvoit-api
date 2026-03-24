<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Http\Requests\Contact\SendContactEmailRequest;
use App\Http\Resources\ConversationMessageResource;
use App\Http\Resources\ConversationResource;
use App\Models\Person;
use App\Models\Trip;
use App\Models\User;
use App\Services\Interfaces\ChatServiceInterface;
use App\Services\Interfaces\ContactEmailServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles chat conversation endpoints.
 */
class ChatController extends Controller
{
    /**
     * Create a new chat controller instance.
     */
    public function __construct(
        private readonly ChatServiceInterface $chat,
        private readonly ContactEmailServiceInterface $contactEmail,
    ) {}

    /**
     * List conversations for the authenticated user.
     *
     * @param Request $request Current HTTP request.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        return ConversationResource::collection($this->chat->listConversations($authUser->person))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Show a conversation visible to the authenticated user.
     *
     * @param Request $request      Current HTTP request.
     * @param int     $conversation Conversation identifier.
     */
    public function show(Request $request, int $conversation): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        return (new ConversationResource($this->chat->getConversationForPerson($conversation, $authUser->person)))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Send a message in a conversation.
     *
     * @param SendChatMessageRequest $request      Validated message payload.
     * @param int                    $conversation Conversation identifier.
     */
    public function send(SendChatMessageRequest $request, int $conversation): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $message = $this->chat->sendMessageInConversation(
            $conversation,
            $authUser->person,
            (string) $request->validated('message', ''),
            $request->file('attachments', [])
        );

        return (new ConversationMessageResource($message))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Clear a conversation for the authenticated user.
     *
     * @param Request $request      Current HTTP request.
     * @param int     $conversation Conversation identifier.
     */
    public function clear(Request $request, int $conversation): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $clearedConversation = $this->chat->clearConversationForPerson($conversation, $authUser->person);

        return response()->json([
            'message' => __('api.chat.conversation_cleared'),
            'data' => (new ConversationResource($clearedConversation))->toArray($request),
        ], Response::HTTP_OK);
    }

    /**
     * Hide a single message for the authenticated user.
     *
     * @param Request $request      Current HTTP request.
     * @param int     $conversation Conversation identifier.
     * @param int     $message      Conversation message identifier.
     */
    public function clearMessage(Request $request, int $conversation, int $message): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $updatedConversation = $this->chat->clearMessageForPerson($conversation, $message, $authUser->person);

        return response()->json([
            'message' => __('api.chat.message_cleared'),
            'data' => (new ConversationResource($updatedConversation))->toArray($request),
        ], Response::HTTP_OK);
    }

    /**
     * Hide multiple messages for the authenticated user.
     *
     * @param Request $request      Current HTTP request.
     * @param int     $conversation Conversation identifier.
     */
    public function clearMessages(Request $request, int $conversation): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $validated = $request->validate([
            'message_ids' => ['required', 'array', 'min:1'],
            'message_ids.*' => ['integer'],
        ]);

        $updatedConversation = $this->chat->clearMessagesForPerson(
            $conversation,
            $validated['message_ids'],
            $authUser->person,
        );

        return response()->json([
            'message' => __('api.chat.messages_cleared'),
            'data' => (new ConversationResource($updatedConversation))->toArray($request),
        ], Response::HTTP_OK);
    }

    /**
     * Open or reuse a conversation with a trip driver.
     *
     * @param SendChatMessageRequest $request Validated contact payload.
     * @param Trip                   $trip    Route-bound trip.
     */
    public function contactDriver(SendChatMessageRequest $request, Trip $trip): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $message = $this->chat->contactDriver(
            $trip,
            $authUser->person,
            $request->validated('message'),
            $request->file('attachments', [])
        );

        $conversation = $message?->conversation ?? $this->chat->openOrCreateDriverConversation($trip, $authUser->person);
        $conversation->loadMissing('participantOne', 'participantTwo', 'trip.departureAddress.city', 'trip.arrivalAddress.city');

        return response()->json([
            'data' => [
                'message' => $message ? (new ConversationMessageResource($message))->resolve() : null,
                'conversation' => (new ConversationResource($conversation))->toArray($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Open or reuse a conversation with a passenger.
     *
     * @param SendChatMessageRequest $request Validated contact payload.
     * @param Trip                   $trip    Route-bound trip.
     * @param Person                 $person  Route-bound passenger.
     */
    public function contactPassenger(SendChatMessageRequest $request, Trip $trip, Person $person): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $message = $this->chat->contactPassenger(
            $trip,
            $person,
            $authUser->person,
            $request->validated('message'),
            $request->file('attachments', [])
        );

        $conversation = $message?->conversation ?? $this->chat->openOrCreatePassengerConversation($trip, $person, $authUser->person);
        $conversation->loadMissing('participantOne', 'participantTwo', 'trip.departureAddress.city', 'trip.arrivalAddress.city');

        return response()->json([
            'data' => [
                'message' => $message ? (new ConversationMessageResource($message))->resolve() : null,
                'conversation' => (new ConversationResource($conversation))->toArray($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Email a trip driver instead of opening chat.
     */
    public function contactDriverByEmail(SendContactEmailRequest $request, Trip $trip): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();
        $validated = $request->validated();

        $this->contactEmail->sendDriverContactEmail(
            $trip,
            $authUser->person,
            (string) $validated['subject'],
            $validated['message'] ?? null,
            $request->file('attachments', [])
        );

        return response()->json([
            'message' => 'Your email has been sent to the driver.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Email a passenger on the current driver trip.
     */
    public function contactPassengerByEmail(SendContactEmailRequest $request, Trip $trip, Person $person): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();
        $validated = $request->validated();

        $this->contactEmail->sendPassengerContactEmail(
            $trip,
            $person,
            $authUser->person,
            (string) $validated['subject'],
            $validated['message'] ?? null,
            $request->file('attachments', [])
        );

        return response()->json([
            'message' => 'Your email has been sent to the passenger.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Proxy broadcaster authentication requests.
     *
     * @param Request $request Current HTTP request.
     */
    public function proxy(Request $request): JsonResponse
    {
        // At this point LocalJwtAuth already ran, user is authenticated
        // Just forward to Laravel's broadcaster
        return response()->json(
            Broadcast::auth($request)
        );
    }

    /**
     * Download a chat attachment visible to the authenticated person.
     */
    public function downloadAttachment(Request $request, int $attachment)
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $messageAttachment = \App\Models\ConversationMessageAttachment::query()
            ->with('message')
            ->find($attachment);

        abort_if($messageAttachment === null, Response::HTTP_NOT_FOUND, 'Attachment not found.');

        $this->chat->getConversationForPerson((int) $messageAttachment->message->conversation_id, $authUser->person);

        return Storage::disk((string) $messageAttachment->disk)->download(
            (string) $messageAttachment->path,
            (string) $messageAttachment->original_name,
            ['Content-Type' => (string) $messageAttachment->mime_type]
        );
    }
}
