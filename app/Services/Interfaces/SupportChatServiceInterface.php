<?php

namespace App\Services\Interfaces;

use App\Models\SupportChatMessage;
use App\Models\SupportChatPresence;
use App\Models\SupportChatSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SupportChatServiceInterface
{
    public function createSession(User $user, ?string $subject = null): SupportChatSession;

    public function getSession(int $sessionId, User $user): SupportChatSession;

    public function getWaitingSessions(): Collection;

    public function getActiveSessionsForAdmin(User $admin): Collection;

    public function getUserSessions(User $user): Collection;

    public function joinSession(int $sessionId, User $admin): SupportChatSession;

    public function closeSession(int $sessionId, User $user): SupportChatSession;

    public function sendMessage(int $sessionId, User $sender, string $body, array $attachments = []): SupportChatMessage;

    public function getMessages(int $sessionId, User $user, int $limit = 50): LengthAwarePaginator;

    public function markAsRead(int $sessionId, User $user): int;

    public function setTyping(int $sessionId, User $user, bool $isTyping): void;

    public function setPresence(User $user, string $status): SupportChatPresence;

    public function getPresence(User $user): ?SupportChatPresence;

    public function getUnreadCount(int $sessionId, User $user): int;

    /**
     * Find an attachment by id and verify the user has access to its session.
     *
     * @param int $sessionId Session identifier.
     * @param int $attachmentId Attachment identifier.
     * @param User $user Authenticated user requesting the attachment.
     *
     * @return \App\Models\SupportChatMessageAttachment|null
     */
    public function findAttachmentForUser(int $sessionId, int $attachmentId, User $user): ?\App\Models\SupportChatMessageAttachment;
}
