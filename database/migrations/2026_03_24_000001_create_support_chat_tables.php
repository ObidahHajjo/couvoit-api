<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['waiting', 'active', 'closed'])->default('waiting');
            $table->string('subject')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('admin_id');
        });

        Schema::create('support_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('support_chat_sessions')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_from_admin')->default(false);
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['session_id', 'created_at']);
        });

        Schema::create('support_chat_typing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('support_chat_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('typing_at');
            $table->timestamps();
            $table->unique(['session_id', 'user_id']);
        });

        Schema::create('support_chat_presence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['online', 'away', 'offline'])->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('support_chat_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('support_chat_messages')->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_chat_message_attachments');
        Schema::dropIfExists('support_chat_presence');
        Schema::dropIfExists('support_chat_typing');
        Schema::dropIfExists('support_chat_messages');
        Schema::dropIfExists('support_chat_sessions');
    }
};
