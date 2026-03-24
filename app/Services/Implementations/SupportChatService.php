<?php

namespace App\Services\Implementations;

use App\Events\SupportMessageSent;
use App\Events\SupportMessagesRead;
use App\Events\SupportPresenceChanged;
use App\Events\SupportSessionClosed;
use App\Events\SupportSessionCreated;
use App\Events\SupportSessionJoined;
use App\Events\SupportTyping;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Models\SupportChatMessage;
use App\Models\SupportChatMessageAttachment;
use App\Models\SupportChatPresence;
use App\Models\SupportChatSession;
use App\Models\SupportChatTyping;
use App\Models\User;
use App\Services\Interfaces\SupportChatServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportChatService implements SupportChatServiceInterface
{
    public function createSession(User $user, ?string $subject = null): SupportChatSession
    {
        $existingSession = SupportChatSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [SupportChatSession::STATUS_WAITING, SupportChatSession::STATUS_ACTIVE])
            ->first();

        if ($existingSession !== null) {
            return $existingSession;
        }

        $session = SupportChatSession::query()->create([
            'user_id' => $user->id,
            'status' => SupportChatSession::STATUS_WAITING,
            'subject' => $subject,
            'last_message_at' => now(),
        ]);

        $session->load('user.person');

        event(new SupportSessionCreated($session));

        return $session;
    }

    public function getSession(int $sessionId, User $user): SupportChatSession
    {
        $session = SupportChatSession::query()
            ->with(['user.person', 'admin.person'])
            ->find($sessionId);

        if ($session === null) {
            throw new NotFoundException('Session not found.');
        }

        if (! $user->isAdmin() && ! $session->involvesUser((int) $user->id)) {
            throw new ForbiddenException('You do not have access to this session.');
        }

        return $session;
    }

    public function getWaitingSessions(): Collection
    {
        return SupportChatSession::query()
            ->where('status', SupportChatSession::STATUS_WAITING)
            ->with(['user.person'])
            ->orderBy('created_at')
            ->get();
    }

    public function getActiveSessionsForAdmin(User $admin): Collection
    {
        return SupportChatSession::query()
            ->where('admin_id', $admin->id)
            ->where('status', SupportChatSession::STATUS_ACTIVE)
            ->with(['user.person', 'messages' => fn ($q) => $q->orderByDesc('created_at')->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function getUserSessions(User $user): Collection
    {
        return SupportChatSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [SupportChatSession::STATUS_WAITING, SupportChatSession::STATUS_ACTIVE])
            ->with(['admin.person', 'messages' => fn ($q) => $q->orderByDesc('created_at')->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function joinSession(int $sessionId, User $admin): SupportChatSession
    {
        if (! $admin->isAdmin()) {
            throw new ForbiddenException('Only admins can join support sessions.');
        }

        $session = SupportChatSession::query()->find($sessionId);

        if ($session === null) {
            throw new NotFoundException('Session not found.');
        }

        if ($session->status === SupportChatSession::STATUS_CLOSED) {
            throw new ForbiddenException('Cannot join a closed session.');
        }

        $session->update([
            'admin_id' => $admin->id,
            'status' => SupportChatSession::STATUS_ACTIVE,
        ]);

        $session->load(['user.person', 'admin.person']);

        event(new SupportSessionJoined($session, (int) $admin->id));

        return $session;
    }

    public function closeSession(int $sessionId, User $user): SupportChatSession
    {
        $session = $this->getSession($sessionId, $user);

        if ($session->isClosed()) {
            throw new ForbiddenException('Session is already closed.');
        }

        $session->update([
            'status' => SupportChatSession::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $session->load(['user.person', 'admin.person']);

        event(new SupportSessionClosed($session));

        return $session;
    }

    public function sendMessage(int $sessionId, User $sender, string $body, array $attachments = []): SupportChatMessage
    {
        $session = $this->getSession($sessionId, $sender);

        if ($session->isClosed()) {
            throw new ForbiddenException('Cannot send messages in a closed session.');
        }

        $isFromAdmin = $sender->isAdmin();

        $message = DB::transaction(function () use ($attachments, $body, $session, $sender, $isFromAdmin): SupportChatMessage {
            $message = $session->messages()->create([
                'sender_id' => $sender->id,
                'is_from_admin' => $isFromAdmin,
                'body' => trim($body),
            ]);

            foreach ($attachments as $attachment) {
                if (! $attachment instanceof UploadedFile) {
                    continue;
                }

                $storedPath = $attachment->storeAs(
                    sprintf('support-attachments/%d', $session->id),
                    Str::uuid()->toString().'-'.$attachment->getClientOriginalName(),
                    'local'
                );

                SupportChatMessageAttachment::query()->create([
                    'message_id' => $message->id,
                    'disk' => 'local',
                    'path' => $storedPath,
                    'original_name' => $attachment->getClientOriginalName(),
                    'mime_type' => $attachment->getClientMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $attachment->getSize() ?: 0,
                ]);
            }

            $session->update([
                'last_message_at' => now(),
            ]);

            return $message;
        });

        $message->load(['sender.person', 'attachments']);

        event(new SupportMessageSent($session, $message));

        return $message;
    }

    public function getMessages(int $sessionId, User $user, int $limit = 50): LengthAwarePaginator
    {
        $this->getSession($sessionId, $user);

        return SupportChatMessage::query()
            ->where('session_id', $sessionId)
            ->with(['sender.person', 'attachments'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($limit);
    }

    public function markAsRead(int $sessionId, User $user): int
    {
        $session = $this->getSession($sessionId, $user);

        $updated = SupportChatMessage::query()
            ->where('session_id', $sessionId)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        if ($updated > 0) {
            event(new SupportMessagesRead($session, (int) $user->id));
        }

        return $updated;
    }

    public function setTyping(int $sessionId, User $user, bool $isTyping): void
    {
        $session = $this->getSession($sessionId, $user);

        if ($session->isClosed()) {
            return;
        }

        if ($isTyping) {
            SupportChatTyping::query()->updateOrCreate(
                [
                    'session_id' => $sessionId,
                    'user_id' => $user->id,
                ],
                [
                    'typing_at' => now(),
                ]
            );
        } else {
            SupportChatTyping::query()
                ->where('session_id', $sessionId)
                ->where('user_id', $user->id)
                ->delete();
        }

        $userName = $user->person ? trim($user->person->first_name.' '.$user->person->last_name) : $user->email;

        event(new SupportTyping($session, (int) $user->id, $userName, $isTyping));
    }

    public function setPresence(User $user, string $status): SupportChatPresence
    {
        $presence = SupportChatPresence::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status,
                'last_seen_at' => now(),
            ]
        );

        $userName = $user->person ? trim($user->person->first_name.' '.$user->person->last_name) : $user->email;

        event(new SupportPresenceChanged($presence, $userName));

        return $presence;
    }

    public function getPresence(User $user): ?SupportChatPresence
    {
        return SupportChatPresence::query()
            ->where('user_id', $user->id)
            ->first();
    }

    public function getUnreadCount(int $sessionId, User $user): int
    {
        $this->getSession($sessionId, $user);

        return SupportChatMessage::query()
            ->where('session_id', $sessionId)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public function findAttachmentForUser(int $sessionId, int $attachmentId, User $user): ?SupportChatMessageAttachment
    {
        $this->getSession($sessionId, $user);

        return SupportChatMessageAttachment::query()
            ->whereHas('message', fn ($q) => $q->where('session_id', $sessionId))
            ->find($attachmentId);
    }
}
