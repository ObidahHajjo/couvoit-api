<?php

namespace App\Services\Implementations;

use App\Clients\Interfaces\SupabaseAuthClientInterface;
use App\Exceptions\ExternalServiceException;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Services\Interfaces\AuthServiceInterface;

/**
 * Service responsible for authentication workflows and
 * synchronization between Supabase auth and local Person aggregate.
 */
final readonly class AuthService implements AuthServiceInterface
{
    /**
     * @param SupabaseAuthClientInterface $supabaseAuth Supabase authentication client.
     * @param PersonRepositoryInterface $persons Repository for Person persistence.
     */
    public function __construct(
        private SupabaseAuthClientInterface $supabaseAuth,
        private PersonRepositoryInterface   $persons,
    )
    {
    }

    /** @inheritDoc */
    public function register(string $email, string $password): array
    {
        $data = $this->supabaseAuth->signUp($email, $password);

        $supabaseUserId = $data['user']['id'] ?? null;

        if (!is_string($supabaseUserId) || $supabaseUserId === '') {
            throw new ExternalServiceException(
                'Unexpected auth response (missing user.id). Check Supabase project_url/anon_key.'
            );
        }

        // Prevent duplicates in local persistence
        $existing = $this->persons->findBySupabaseUserId($supabaseUserId);

        if (!$existing) {
            $this->persons->create([
                'supabase_user_id' => $supabaseUserId,
                'email' => $email,
                'role_id' => 1, // TODO: replace with RoleRepository lookup ("user")
                'is_active' => true,
            ]);
        }

        return $data;
    }

    /** @inheritDoc */
    public function login(string $email, string $password): array
    {
        return $this->supabaseAuth->signInWithPassword($email, $password);
    }

    /** @inheritDoc */
    public function refresh(string $refreshToken): array
    {
        return $this->supabaseAuth->refreshToken($refreshToken);
    }
}
