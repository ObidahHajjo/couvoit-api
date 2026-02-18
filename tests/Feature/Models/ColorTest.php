<?php

namespace Tests\Feature\Models;

use App\Models\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_color_has_timestamps_disabled(): void
    {
        $this->assertFalse((new Color())->timestamps);
    }

    public function test_color_fillable_allows_mass_assignment(): void
    {
        $color = Color::query()->create(['name' => 'Cyan', 'hex_code' => '#00AAFF']);

        $this->assertDatabaseHas('colors', [
            'id' => $color->id,
            'name' => 'Cyan',
            'hex_code' => '#00AAFF',
        ]);
    }
}
