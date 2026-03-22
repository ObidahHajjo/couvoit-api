<?php

namespace Tests\Feature\Http;

use App\Events\ChatMessageSent;
use App\Models\Address;
use App\Models\City;
use App\Models\Conversation;
use App\Models\Person;
use App\Models\Trip;
use App\Models\User;
use App\Security\JwtIssuer;
use App\Security\JwtIssuerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'user'],
            ['id' => 2, 'name' => 'admin'],
        ]);
    }

    private function fakeJwt(): string
    {
        return 'aaa.bbb.ccc';
    }

    private function authenticate(User $user): self
    {
        /** @var JwtIssuerInterface&MockInterface $jwt */
        $jwt = Mockery::mock(JwtIssuerInterface::class);
        $jwt->shouldReceive('verify')->andReturn((object) [
            'sub' => (string) $user->id,
            'exp' => time() + 3600,
        ]);

        $this->app->instance(JwtIssuer::class, $jwt);

        return $this->withHeader('Authorization', 'Bearer '.$this->fakeJwt());
    }

    private function makePerson(array $overrides = []): Person
    {
        return Person::query()->create(array_merge([
            'first_name' => 'First',
            'last_name' => 'Last',
            'pseudo' => 'p_'.uniqid(),
            'phone' => null,
            'car_id' => null,
        ], $overrides));
    }

    private function makeUser(Person $person, array $overrides = []): User
    {
        $this->seedRoles();

        return User::query()->create(array_merge([
            'email' => 'u_'.uniqid().'@example.test',
            'password' => Hash::make('secret12345'),
            'role_id' => 1,
            'is_active' => true,
            'person_id' => $person->id,
        ], $overrides));
    }

    private function makeAddress(string $cityName): Address
    {
        $city = City::query()->create([
            'name' => $cityName,
            'postal_code' => (string) random_int(10000, 99999),
        ]);

        return Address::query()->create([
            'street' => 'Rue '.uniqid(),
            'street_number' => '1',
            'city_id' => $city->id,
        ]);
    }

    private function makeTrip(Person $driver): Trip
    {
        $departure = $this->makeAddress('Papeete');
        $arrival = $this->makeAddress('Faaa');

        return Trip::query()->create([
            'departure_time' => '2026-04-01 10:00:00',
            'arrival_time' => '2026-04-01 11:00:00',
            'distance_km' => 12,
            'available_seats' => 3,
            'smoking_allowed' => false,
            'departure_address_id' => $departure->id,
            'arrival_address_id' => $arrival->id,
            'person_id' => $driver->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_contact_driver_creates_conversation_and_message(): void
    {
        Event::fake([ChatMessageSent::class]);

        $driver = $this->makePerson(['first_name' => 'Dina', 'last_name' => 'Driver', 'pseudo' => 'dina']);
        $passenger = $this->makePerson(['first_name' => 'Paul', 'last_name' => 'Passenger', 'pseudo' => 'paul']);
        $passengerUser = $this->makeUser($passenger);
        $this->makeUser($driver);

        $trip = $this->makeTrip($driver);

        $res = $this->authenticate($passengerUser)->postJson("/trips/{$trip->id}/contact-driver", [
            'message' => 'Hello driver',
        ]);

        $res->assertCreated();
        $res->assertJsonPath('data.message.body', 'Hello driver');
        $res->assertJsonPath('data.conversation.participant.name', 'Dina Driver');

        $this->assertDatabaseHas('conversations', [
            'participant_one_id' => min($driver->id, $passenger->id),
            'participant_two_id' => max($driver->id, $passenger->id),
            'trip_id' => $trip->id,
        ]);

        $this->assertDatabaseHas('conversation_messages', [
            'sender_person_id' => $passenger->id,
            'body' => 'Hello driver',
        ]);

        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_driver_can_contact_trip_passenger(): void
    {
        $driver = $this->makePerson(['first_name' => 'Dina', 'last_name' => 'Driver']);
        $passenger = $this->makePerson(['first_name' => 'Paul', 'last_name' => 'Passenger']);
        $driverUser = $this->makeUser($driver);
        $this->makeUser($passenger);

        $trip = $this->makeTrip($driver);
        $trip->passengers()->attach($passenger->id);

        $res = $this->authenticate($driverUser)->postJson("/my-trips/{$trip->id}/contact-passenger/{$passenger->id}", [
            'message' => 'Pickup updated',
        ]);

        $res->assertCreated();
        $res->assertJsonPath('data.message.body', 'Pickup updated');
        $res->assertJsonPath('data.conversation.participant.name', 'Paul Passenger');
    }

    public function test_list_conversations_returns_only_authenticated_person_conversations(): void
    {
        $driver = $this->makePerson(['first_name' => 'Dina', 'last_name' => 'Driver']);
        $passenger = $this->makePerson(['first_name' => 'Paul', 'last_name' => 'Passenger']);
        $other = $this->makePerson(['first_name' => 'Olga', 'last_name' => 'Other']);
        $passengerUser = $this->makeUser($passenger);

        $conversation = Conversation::query()->create([
            'participant_one_id' => min($driver->id, $passenger->id),
            'participant_two_id' => max($driver->id, $passenger->id),
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'sender_person_id' => $passenger->id,
            'body' => 'Hi Dina',
        ]);

        Conversation::query()->create([
            'participant_one_id' => min($driver->id, $other->id),
            'participant_two_id' => max($driver->id, $other->id),
            'last_message_at' => now(),
        ]);

        $res = $this->authenticate($passengerUser)->getJson('/conversations');

        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.participant.name', 'Dina Driver');
        $res->assertJsonPath('data.0.latest_message.body', 'Hi Dina');
    }

    public function test_list_conversations_prefers_highest_message_id_when_timestamps_match(): void
    {
        $driver = $this->makePerson(['first_name' => 'Dina', 'last_name' => 'Driver']);
        $passenger = $this->makePerson(['first_name' => 'Paul', 'last_name' => 'Passenger']);
        $passengerUser = $this->makeUser($passenger);

        $conversation = Conversation::query()->create([
            'participant_one_id' => min($driver->id, $passenger->id),
            'participant_two_id' => max($driver->id, $passenger->id),
            'last_message_at' => now(),
        ]);

        $first = $conversation->messages()->create([
            'sender_person_id' => $driver->id,
            'body' => 'Older message',
        ]);

        $second = $conversation->messages()->create([
            'sender_person_id' => $driver->id,
            'body' => 'Newest message',
        ]);

        $sameTimestamp = '2026-03-22 12:00:05';

        DB::table('conversation_messages')
            ->whereIn('id', [$first->id, $second->id])
            ->update([
                'created_at' => $sameTimestamp,
                'updated_at' => $sameTimestamp,
            ]);

        DB::table('conversations')
            ->where('id', $conversation->id)
            ->update([
                'last_message_at' => $sameTimestamp,
                'updated_at' => $sameTimestamp,
            ]);

        $res = $this->authenticate($passengerUser)->getJson('/conversations');

        $res->assertOk();
        $res->assertJsonPath('data.0.latest_message.body', 'Newest message');
    }

    public function test_clear_conversation_only_hides_history_for_authenticated_person(): void
    {
        $driver = $this->makePerson(['first_name' => 'Dina', 'last_name' => 'Driver']);
        $passenger = $this->makePerson(['first_name' => 'Paul', 'last_name' => 'Passenger']);
        $driverUser = $this->makeUser($driver);
        $passengerUser = $this->makeUser($passenger);

        $conversation = Conversation::query()->create([
            'participant_one_id' => min($driver->id, $passenger->id),
            'participant_two_id' => max($driver->id, $passenger->id),
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'sender_person_id' => $driver->id,
            'body' => 'First message',
        ]);

        $conversation->messages()->create([
            'sender_person_id' => $passenger->id,
            'body' => 'Second message',
        ]);

        $clearResponse = $this->authenticate($passengerUser)->postJson("/conversations/{$conversation->id}/clear");

        $clearResponse->assertOk();
        $clearResponse->assertJsonPath('message', 'Conversation cleared for your account.');
        $clearResponse->assertJsonCount(0, 'data.messages');
        $clearResponse->assertJsonPath('data.latest_message', null);

        $passengerView = $this->authenticate($passengerUser)->getJson("/conversations/{$conversation->id}");
        $passengerView->assertOk();
        $passengerView->assertJsonCount(0, 'data.messages');
        $passengerView->assertJsonPath('data.latest_message', null);

        $driverView = $this->authenticate($driverUser)->getJson("/conversations/{$conversation->id}");
        $driverView->assertOk();
        $driverView->assertJsonCount(2, 'data.messages');
        $driverView->assertJsonPath('data.latest_message.body', 'Second message');
    }
}
