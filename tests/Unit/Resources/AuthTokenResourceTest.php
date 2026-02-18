<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\AuthTokenResource;
use Illuminate\Http\Request;
use Tests\TestCase;
use Throwable;

/**
 * Class AuthTokenResourceTest
 *
 * Unit tests for AuthTokenResource serialization shape.
 */
class AuthTokenResourceTest extends TestCase
{
    /**
     * A request instance passed to resource->toArray().
     *
     * @var Request
     */
    private Request $request;

    /**
     * Setup request.
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->request = Request::create('/x');
    }

    /**
     * Resource should map known auth keys and user minimal fields.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_to_array_maps_expected_fields(): void
    {
        $resource = new AuthTokenResource([
            'access_token' => 'a',
            'token_type' => 'bearer',
            'expires_in' => 3600,
            'refresh_token' => 'r',
            'user' => ['id' => 'uuid', 'email' => 'x@example.com', 'extra' => 'ignored'],
        ]);

        $arr = $resource->toArray($this->request);

        $this->assertSame('a', $arr['access_token']);
        $this->assertSame('bearer', $arr['token_type']);
        $this->assertSame(3600, $arr['expires_in']);
        $this->assertSame('r', $arr['refresh_token']);
        $this->assertSame('uuid', $arr['user']['id']);
        $this->assertSame('x@example.com', $arr['user']['email']);
        $this->assertArrayNotHasKey('extra', $arr['user']);
    }

    /**
     * Resource should output nulls safely when keys are missing.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_to_array_handles_missing_keys(): void
    {
        $resource = new AuthTokenResource([]);

        $arr = $resource->toArray($this->request);

        $this->assertNull($arr['access_token']);
        $this->assertNull($arr['token_type']);
        $this->assertNull($arr['expires_in']);
        $this->assertNull($arr['refresh_token']);
        $this->assertNull($arr['user']);
    }
}
