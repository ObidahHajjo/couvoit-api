<?php

namespace Tests\Feature\Repositories;

use App\Models\Brand;
use App\Repositories\Eloquent\BrandEloquentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_or_first_returns_existing_brand(): void
    {
        $repo = app(BrandEloquentRepository::class);

        $existing = Brand::factory()->create(['name' => 'toyota']);

        $brand = $repo->createOrFirst('toyota');

        $this->assertEquals($existing->id, $brand->id);
        $this->assertDatabaseCount('brands', 1);
    }

    public function test_create_or_first_creates_when_missing(): void
    {
        $repo = app(BrandEloquentRepository::class);

        $brand = $repo->createOrFirst('renault');

        $this->assertDatabaseHas('brands', ['name' => 'renault']);
        $this->assertEquals('renault', $brand->name);
    }
}
