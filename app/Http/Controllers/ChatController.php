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
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Chat', description: 'Chat conversation endpoints')]
/**
 * Handles chat conversation endpoints.
 */
class ChatController extends Controller
{
    /**
     * Create a new chat controller instance.
     *
     * @param  ChatServiceInterface  $chat  Chat service for managing conversations.
     * @param  ContactEmailServiceInterface  $contactEmail  Service for sending contact emails.
     */
    public function __construct(
        private readonly ChatServiceInterface $chat,
        private readonly ContactEmailServiceInterface $contactEmail,
    ) {}

    #[OA\Get(
        path: '/conversations',
        operationId: 'listConversations',
        summary: 'List conversations',
        tags: ['Chat'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ConversationResource'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    /**
     * List conversations for the authenticated user.
     *
     * @param  Request  $request  Current HTTP request.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        return ConversationResource::collection($this->chat->listConversations($authUser->person))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/conversations/{conversation}',
        operationId: 'showConversation',
        summary: 'Show conversation',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'conversation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConversationResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    /**
     * Show a conversation visible to the authenticated user.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $conversation  Conversation identifier.
     */
    public function show(Request $request, int $conversation): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        return (new ConversationResource($this->chat->getConversationForPerson($conversation, $authUser->person)))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/conversations/{conversation}/messages',
        operationId: 'sendMessage',
        summary: 'Send message',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'conversation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SendChatMessageRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ConversationMessageResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Send a message in a conversation.
     *
     * @param  SendChatMessageRequest  $request  Validated message payload.
     * @param  int  $conversation  Conversation identifier.
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

    #[OA\Delete(
        path: '/conversations/{conversation}',
        operationId: 'clearConversation',
        summary: 'Clear conversation',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'conversation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConversationResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    /**
     * Clear a conversation for the authenticated user.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $conversation  Conversation identifier.
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

    #[OA\Delete(
        path: '/conversations/{conversation}/messages/{message}',
        operationId: 'clearMessage',
        summary: 'Clear single message',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'conversation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'message', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConversationResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    /**
     * Hide a single message for the authenticated user.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $conversation  Conversation identifier.
     * @param  int  $message  Conversation message identifier.
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

    #[OA\Post(
        path: '/conversations/{conversation}/messages/bulk-delete',
        operationId: 'clearMessages',
        summary: 'Clear multiple messages',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'conversation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'message_ids', type: 'array', items: new OA\Items(type: 'integer')),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConversationResource')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Hide multiple messages for the authenticated user.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $conversation  Conversation identifier.
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

    #[OA\Post(
        path: '/trips/{trip}/contact-driver',
        operationId: 'contactDriver',
        summary: 'Contact driver',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'trip', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SendChatMessageRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Open or reuse a conversation with a trip driver.
     *
     * @param  SendChatMessageRequest  $request  Validated contact payload.
     * @param  Trip  $trip  Route-bound trip.
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

    #[OA\Post(
        path: '/trips/{trip}/passengers/{person}/contact',
        operationId: 'contactPassenger',
        summary: 'Contact passenger',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'trip', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'person', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SendChatMessageRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Open or reuse a conversation with a passenger.
     *
     * @param  SendChatMessageRequest  $request  Validated contact payload.
     * @param  Trip  $trip  Route-bound trip.
     * @param  Person  $person  Route-bound passenger.
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

    #[OA\Post(
        path: '/trips/{trip}/email-driver',
        operationId: 'emailDriver',
        summary: 'Email driver',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'trip', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
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
     * Email a trip driver instead of opening chat.
     *
     * @param  SendContactEmailRequest  $request  Validated contact payload.
     * @param  Trip  $trip  Route-bound trip.
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

    #[OA\Post(
        path: '/trips/{trip}/passengers/{person}/email',
        operationId: 'emailPassenger',
        summary: 'Email passenger',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'trip', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'person', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
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
     * Email a passenger on the current driver trip.
     *
     * @param  SendContactEmailRequest  $request  Validated contact payload.
     * @param  Trip  $trip  Route-bound trip.
     * @param  Person  $person  Route-bound passenger.
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

    #[OA\Post(
        path: '/chat/proxy',
        operationId: 'chatProxy',
        summary: 'Chat proxy authentication',
        tags: ['Chat'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    /**
     * Proxy broadcaster authentication requests.
     *
     * @param  Request  $request  Current HTTP request.
     */
    public function proxy(Request $request): JsonResponse
    {
        // At this point LocalJwtAuth already ran, user is authenticated
        // Just forward to Laravel's broadcaster
        return response()->json(
            Broadcast::auth($request)
        );
    }

    #[OA\Get(
        path: '/chat/attachments/{attachment}',
        operationId: 'downloadAttachment',
        summary: 'Download attachment',
        tags: ['Chat'],
        parameters: [
            new OA\Parameter(name: 'attachment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    /**
     * Download a chat attachment visible to the authenticated person.
     *
     * @param  Request  $request  Current HTTP request.
     * @param  int  $attachment  Attachment identifier.
     */
    public function downloadAttachment(Request $request, int $attachment)
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $messageAttachment = $this->chat->findAttachmentForPerson($attachment, $authUser->person);

        abort_if($messageAttachment === null, Response::HTTP_NOT_FOUND, 'Attachment not found.');

        return Storage::disk((string) $messageAttachment->disk)->download(
            (string) $messageAttachment->path,
            (string) $messageAttachment->original_name,
            ['Content-Type' => (string) $messageAttachment->mime_type]
        );
    }
}
