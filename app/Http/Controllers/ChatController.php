<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Http\Resources\ConversationMessageResource;
use App\Http\Resources\ConversationResource;
use App\Models\Person;
use App\Models\Trip;
use App\Models\User;
use App\Services\Interfaces\ChatServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles chat conversation endpoints.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly ChatServiceInterface $chat,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        return ConversationResource::collection($this->chat->listConversations($authUser->person))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        return (new ConversationResource($this->chat->getConversationForPerson($conversation, $authUser->person)))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function send(SendChatMessageRequest $request, int $conversation): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $message = $this->chat->sendMessageInConversation(
            $conversation,
            $authUser->person,
            (string) $request->validated('message')
        );

        return (new ConversationMessageResource($message))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function contactDriver(SendChatMessageRequest $request, Trip $trip): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $message = $this->chat->contactDriver(
            $trip,
            $authUser->person,
            $request->validated('message')
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

    public function contactPassenger(SendChatMessageRequest $request, Trip $trip, Person $person): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $message = $this->chat->contactPassenger(
            $trip,
            $person,
            $authUser->person,
            $request->validated('message')
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

    public function proxy(Request $request): JsonResponse
    {
        // At this point LocalJwtAuth already ran, user is authenticated
        // Just forward to Laravel's broadcaster
        return response()->json(
            Broadcast::auth($request)
        );
    }
}
