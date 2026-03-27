<?php

use App\Models\Conversation;
use App\Models\SupportChatSession;
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

Broadcast::channel('support.session.{sessionId}', function (User $user, int $sessionId) {
    $session = SupportChatSession::query()->find($sessionId);

    if ($session === null) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    return $session->involvesUser((int) $user->id);
});

Broadcast::channel('support.user.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('support.admin.{adminId}', function (User $user, int $adminId) {
    return (int) $user->id === $adminId && $user->isAdmin();
});

Broadcast::channel('support.admins', function (User $user) {
    return $user->isAdmin();
});

Broadcast::channel('support.presence.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId;
});
