<?php

namespace App\Services\Interfaces;

use App\Models\SupportChatMessage;
use App\Models\SupportChatMessageAttachment;
use App\Models\SupportChatPresence;
use App\Models\SupportChatSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Contract for support chat services.
 */
interface SupportChatServiceInterface
{
    /**
     * Create a new support chat session.
     *
     * @param  User  $user  User creating the session.
     * @param  string|null  $subject  Optional session subject.
     *
     * @throws \Throwable If the operation fails.
     */
    public function createSession(User $user, ?string $subject = null): SupportChatSession;

    /**
     * Get a specific support chat session.
     *
     * @param  int  $sessionId  Session identifier.
     * @param  User  $user  User requesting the session.
     *
     * @throws ModelNotFoundException If the session is not found.
     */
    public function getSession(int $sessionId, User $user): SupportChatSession;

    /**
     * Get all waiting support chat sessions.
     *
     * @return Collection<int, SupportChatSession>
     */
    public function getWaitingSessions(): Collection;

    /**
     * Get all active sessions for an admin.
     *
     * @param  User  $admin  Admin user.
     * @return Collection<int, SupportChatSession>
     */
    public function getActiveSessionsForAdmin(User $admin): Collection;

    /**
     * Get all sessions for a user.
     *
     * @param  User  $user  User requesting their sessions.
     * @return Collection<int, SupportChatSession>
     */
    public function getUserSessions(User $user): Collection;

    /**
     * Join a session as an admin.
     *
     * @param  int  $sessionId  Session identifier.
     * @param  User  $admin  Admin joining the session.
     *
     * @throws ModelNotFoundException If the session is not found.
     */
    public function joinSession(int $sessionId, User $admin): SupportChatSession;

    /**
     * Close a support chat session.
     *
     * @param  int  $sessionId  Session identifier.
     * @param  User  $user  User closing the session.
     *
     * @throws ModelNotFoundException If the session is not found.
     */
    public function closeSession(int $sessionId, User $user): SupportChatSession;

    /**
     * Send a message in a support chat session.
     *
     * @param  int  $sessionId  Session identifier.
     * @param  User  $sender  User sending the message.
     * @param  string  $body  Message content.
     * @param  array<int, array<string, mixed>>  $attachments  Optional attachments.
     *
     * @throws \Throwable If the operation fails.
     */
    public function sendMessage(int $sessionId, User $sender, string $body, array $attachments = []): SupportChatMessage;

    /**
     * Get messages for a support chat session.
     *
     * @param  int  $sessionId  Session identifier.
     * @param  User  $user  User requesting the messages.
     * @param  int  $limit  Number of messages to retrieve.
     * @return LengthAwarePaginator<int, SupportChatMessage>
     */
    public function getMessages(int $sessionId, User $user, int $limit = 50): LengthAwarePaginator;

    /**
     * Mark messages as read in a session.
     *
     * @param  int  $sessionId  Session identifier.
     * @param  User  $user  User marking messages as read.
     * @return int Number of messages marked as read.
     */
    public function markAsRead(int $sessionId, User $user): int;

    /**
     * Set typing status for a user in a session.
     *
     * @param  int  $sessionId  Session identifier.
     * @param  User  $user  User setting typing status.
     * @param  bool  $isTyping  Whether the user is typing.
     */
    public function setTyping(int $sessionId, User $user, bool $isTyping): void;

    /**
     * Set presence status for a user.
     *
     * @param  User  $user  User setting presence.
     * @param  string  $status  Presence status.
     *
     * @throws \Throwable If the operation fails.
     */
    public function setPresence(User $user, string $status): SupportChatPresence;

    /**
     * Get presence status for a user.
     *
     * @param  User  $user  User to get presence for.
     */
    public function getPresence(User $user): ?SupportChatPresence;

    /**
     * Get unread message count for a session.
     *
     * @param  int  $sessionId  Session identifier.
     * @param  User  $user  User requesting the count.
     */
    public function getUnreadCount(int $sessionId, User $user): int;

    /**
     * Find an attachment by id and verify the user has access to its session.
     *
     * @param  int  $sessionId  Session identifier.
     * @param  int  $attachmentId  Attachment identifier.
     * @param  User  $user  Authenticated user requesting the attachment.
     */
    public function findAttachmentForUser(int $sessionId, int $attachmentId, User $user): ?SupportChatMessageAttachment;
}
