<?php

namespace App\Repositories\Eloquent;

use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use Illuminate\Support\Collection;

class PersonEloquentRepository implements PersonRepositoryInterface
{
    public function all(): Collection
    {
        return Person::query()->with(['role', 'car'])->get();
    }

    public function findById(int $id): Person
    {
        return Person::query()->with(['role', 'car'])->findOrFail($id);
    }

    public function create(array $data): Person
    {
        return Person::query()->create($data);
    }

    public function update(int $id, array $data): void
    {
        Person::query()->findOrFail($id)->update($data);
    }

    public function delete(int $id): void
    {
        Person::query()->findOrFail($id)->delete();
    }

    public function attachCar(Person $person, int $carId): bool
    {
        $person->car_id = $carId;
        return $person->save();
    }

    public function findBySupabaseUserId(string $supabaseUserId): ?Person
    {
        return Person::query()->where('supabase_user_id', $supabaseUserId)->first();
    }

}

