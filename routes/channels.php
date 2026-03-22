<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.user.{personId}', function (User $user, int $personId) {
    return (int) $user->person_id === $personId;
});

Broadcast::channel('chat.conversation.{conversationId}', function (User $user, int $conversationId) {
    $conversation = Conversation::query()->find($conversationId);

    if ($conversation === null) {
        return false;
    }

    return $conversation->involvesPerson((int) $user->person_id);
});
