<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\AuthSessionResource;
use Illuminate\Http\Request;
use Tests\TestCase;
use Throwable;

class AuthSessionResourceTest extends TestCase
{
    private Request $request;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->request = Request::create('/x');
    }

    public function test_to_array_maps_expected_fields(): void
    {
        $resource = new AuthSessionResource([
            'message' => 'Authenticated successfully.',
        ]);

        $arr = $resource->toArray($this->request);
        self::assertEquals('Authenticated successfully.', $arr['message']);
    }

    public function test_to_array_handles_missing_keys(): void
    {
        $resource = new AuthSessionResource([]);

        $arr = $resource->toArray($this->request);

        $this->assertNull($arr['message']);
    }
}
