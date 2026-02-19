<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Policies\PersonPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Class PersonPolicyTest
 *
 * Unit tests for PersonPolicy authorization rules.
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

        // Your project uses fixed role ids on Person model.
        $this->userRoleId  = $this->resolveConst(Person::class, 'ROLE_USER', 1);
        $this->adminRoleId = $this->resolveConst(Person::class, 'ROLE_ADMIN', 2);

        $this->ensureRoles();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function before_allows_admin_everything(): void
    {
        $admin = $this->makePersonWithRole($this->adminRoleId);

        $result = $this->policy->before($admin);

        self::assertTrue($result === true);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function view_any_denies_non_admin(): void
    {
        $user = $this->makePersonWithRole($this->userRoleId);

        $res = $this->policy->viewAny($user);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function view_allows_self_and_denies_other_for_non_admin(): void
    {
        $user  = $this->makePersonWithRole($this->userRoleId);
        $other = $this->makePersonWithRole($this->userRoleId);

        self::assertTrue($this->policy->view($user, $user)->allowed());
        self::assertTrue($this->policy->view($user, $other)->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function trips_driver_allows_self_and_denies_other_for_non_admin(): void
    {
        $user  = $this->makePersonWithRole($this->userRoleId);
        $other = $this->makePersonWithRole($this->userRoleId);

        self::assertTrue($this->policy->viewTripsDriver($user, $user)->allowed());
        self::assertTrue($this->policy->viewTripsDriver($user, $other)->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function trips_passenger_allows_self_and_denies_other_for_non_admin(): void
    {
        $user  = $this->makePersonWithRole($this->userRoleId);
        $other = $this->makePersonWithRole($this->userRoleId);

        self::assertTrue($this->policy->viewTripsPassenger($user, $user)->allowed());
        self::assertTrue($this->policy->viewTripsPassenger($user, $other)->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function create_allows_when_user_exists(): void
    {
        $user = $this->makePersonWithRole($this->userRoleId);

        $res = $this->policy->create($user);

        self::assertTrue($res->allowed());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_allows_self_and_denies_other_for_non_admin(): void
    {
        $user  = $this->makePersonWithRole($this->userRoleId);
        $other = $this->makePersonWithRole($this->userRoleId);

        self::assertTrue($this->policy->update($user, $user)->allowed());
        self::assertTrue($this->policy->update($user, $other)->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_is_denied_for_non_admin(): void
    {
        $user   = $this->makePersonWithRole($this->userRoleId);
        $target = $this->makePersonWithRole($this->userRoleId);

        $res = $this->policy->delete($user, $target);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_role_denies_non_admin(): void
    {
        $user = $this->makePersonWithRole($this->userRoleId);

        $res = $this->policy->updateRole($user);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_role_allows_admin_via_before(): void
    {
        $admin = $this->makePersonWithRole($this->adminRoleId);

        self::assertTrue($this->policy->before($admin) === true);
    }

    /**
     * Ensure roles exist with correct ids.
     *
     * IMPORTANT: Role::id is commonly guarded, so we create unguarded.
     *
     * @return void
     */
    private function ensureRoles(): void
    {
        $userExists = Role::query()->where('name', 'user')->exists();
        if (! $userExists) {
            Role::unguarded(function (): void {
                Role::query()->create(['id' => $this->userRoleId, 'name' => 'user']);
            });
        }

        $adminExists = Role::query()->where('name', 'admin')->exists();
        if (! $adminExists) {
            Role::unguarded(function (): void {
                Role::query()->create(['id' => $this->adminRoleId, 'name' => 'admin']);
            });
        }
    }

    /**
     * @param int $roleId
     * @return Person
     */
    private function makePersonWithRole(int $roleId): Person
    {
        $suffix = Str::lower(Str::random(8));

        $person = Person::query()->create([
            'supabase_user_id' => (string) Str::uuid(),
            'pseudo' => "user_$suffix",
            'email' => "user_$suffix@example.com",
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+33600000000',
            'role_id' => $roleId,
            'is_active' => true,
        ]);

        // isAdmin() often relies on $person->role relation (name), so load it.
        $person->load('role');

        return $person;
    }

    /**
     * @param class-string $fqcn
     * @param string $name
     * @param int $fallback
     * @return int
     */
    private function resolveConst(string $fqcn, string $name, int $fallback): int
    {
        $const = $fqcn . '::' . $name;

        return defined($const) ? (int) constant($const) : $fallback;
    }
}
