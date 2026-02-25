<?php

namespace Tests\Feature\Models;

use App\Models\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_has_timestamps_disabled(): void
    {
        $this->assertFalse((new Type())->timestamps);
    }

    public function test_type_fillable_allows_mass_assignment(): void
    {
        $type = Type::query()->create(['type' => 'SUV']);

        $this->assertDatabaseHas('types', [
            'id' => $type->id,
            'type' => 'SUV',
        ]);
    }
}
