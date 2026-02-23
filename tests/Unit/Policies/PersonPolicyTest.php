<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Policies\PersonPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Class PersonPolicyTest
 *
 * Unit tests for PersonPolicy authorization rules using User (auth) + Person (profile).
 */
final class PersonPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var PersonPolicy
     */
    private PersonPolicy $policy;

    /**
     * @var int
     */
    private int $adminRoleId;

    /**
     * @var int
     */
    private int $userRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PersonPolicy();

        // Fixed role ids (as in your models)
        $this->userRoleId  = 1;
        $this->adminRoleId = 2;

        $this->ensureRoles();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function before_allows_admin_everything(): void
    {
        $array = $this->makeUserWithProfileAndRole($this->adminRoleId);

        $result = $this->policy->before($array[0]);

        self::assertTrue($result === true);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function view_any_denies_non_admin(): void
    {
        $array = $this->makeUserWithProfileAndRole($this->userRoleId);

        $res = $this->policy->viewAny($array[0]);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function view_allows_self_and_denies_other_for_non_admin(): void
    {
        [$user, $myPerson] = $this->makeUserWithProfileAndRole($this->userRoleId);
        [, $otherPerson]   = $this->makeUserWithProfileAndRole($this->userRoleId);

        self::assertTrue($this->policy->view($user, $myPerson)->allowed());
        self::assertTrue($this->policy->view($user, $otherPerson)->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function create_allows_when_user_is_active(): void
    {
        [$user] = $this->makeUserWithProfileAndRole($this->userRoleId);

        $res = $this->policy->create($user);

        self::assertTrue($res->allowed());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_allows_self_and_denies_other_for_non_admin(): void
    {
        [$user, $myPerson] = $this->makeUserWithProfileAndRole($this->userRoleId);
        [, $otherPerson]   = $this->makeUserWithProfileAndRole($this->userRoleId);

        self::assertTrue($this->policy->update($user, $myPerson)->allowed());
        self::assertTrue($this->policy->update($user, $otherPerson)->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_is_denied_for_non_admin(): void
    {
        [$user] = $this->makeUserWithProfileAndRole($this->userRoleId);
        [, $targetPerson] = $this->makeUserWithProfileAndRole($this->userRoleId);

        $res = $this->policy->delete($user, $targetPerson);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_role_denies_non_admin(): void
    {
        [$user] = $this->makeUserWithProfileAndRole($this->userRoleId);

        $res = $this->policy->updateRole($user);

        self::assertTrue($res->denied());
    }

    /**
     * Ensure roles exist with correct ids.
     *
     * @return void
     */
    private function ensureRoles(): void
    {
        if (!Role::query()->where('id', $this->userRoleId)->exists()) {
            Role::unguarded(function (): void {
                Role::query()->create(['id' => $this->userRoleId, 'name' => 'user']);
            });
        }

        if (!Role::query()->where('id', $this->adminRoleId)->exists()) {
            Role::unguarded(function (): void {
                Role::query()->create(['id' => $this->adminRoleId, 'name' => 'admin']);
            });
        }
    }

    /**
     * Create a User (auth) + Person (profile) pair.
     *
     * @param int $roleId
     * @return array{0:User,1:Person}
     */
    private function makeUserWithProfileAndRole(int $roleId): array
    {
        $suffix = Str::lower(Str::random(8));

        $person = Person::query()->create([
            'pseudo' => "p_$suffix",
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+33600000000',
        ]);

        $user = User::query()->create([
            'email' => "u_$suffix@example.com",
            'password' => bcrypt('secret123'),
            'role_id' => $roleId,
            'is_active' => true,
            'person_id' => $person->id,
        ]);

        $user->loadMissing('person');

        return [$user, $person];
    }
}
