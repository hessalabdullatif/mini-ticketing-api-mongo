<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventTest extends TestCase
{
    private User $user;
    private User $admin;
    private Event $event;
    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@test.com',
            'password' => 'password123',
            'role'     => 'user',
        ]);

        $this->admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@test.com',
            'password' => 'password123',
            'role'     => 'admin',
        ]);

        $this->event = Event::create([
            'name'   => 'Test Concert',
            'city'   => 'Riyadh',
            'date'   => now()->addMonths(3),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->ticket = Ticket::create([
            'event_id'           => $this->event->id,
            'event_name'         => $this->event->name,
            'type'               => 'VIP',
            'price'              => 500,
            'quantity_available' => 10,
        ]);
    }

    private function userToken(): string
    {
        return $this->user->createToken('test', ['orders:create', 'orders:read'])->accessToken;
    }

    private function adminToken(): string
    {
        return $this->admin->createToken('test', [
            'orders:create', 'orders:read', 'events:create', 'events:manage',
        ])->accessToken;
    }

    private function orderPayload(): array
    {
        return [
            'event_id'  => (string) $this->event->id,
            'ticket_id' => (string) $this->ticket->id,
            'quantity'  => 1,
        ];
    }

    // ===== event availability — Uzair's first suggestion =====

    #[Test]
    public function tickets_cannot_be_bought_for_a_paused_event(): void
    {
        $this->event->update(['status' => Event::STATUS_PAUSED]);

        $this->withToken($this->userToken())
            ->postJson('/api/orders', $this->orderPayload())
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.event_id.0',
                'Ticket sales for this event are currently paused.'
            );
    }

    #[Test]
    public function tickets_cannot_be_bought_for_a_cancelled_event(): void
    {
        $this->event->update(['status' => Event::STATUS_CANCELLED]);

        $this->withToken($this->userToken())
            ->postJson('/api/orders', $this->orderPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.event_id.0', 'This event has been cancelled.');
    }

    #[Test]
    public function a_paused_event_does_not_reduce_stock(): void
    {
        // a rejected order must leave inventory untouched
        $this->event->update(['status' => Event::STATUS_PAUSED]);

        $this->withToken($this->userToken())
            ->postJson('/api/orders', $this->orderPayload());

        $this->assertSame(10, $this->ticket->fresh()->quantity_available);
    }

    #[Test]
    public function tickets_cannot_be_bought_for_a_past_event(): void
    {
        $this->event->update(['date' => now()->subMonth()]);

        $this->withToken($this->userToken())
            ->postJson('/api/orders', $this->orderPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('event_id');
    }

    #[Test]
    public function cancelled_takes_priority_over_past_in_the_error_message(): void
    {
        // both conditions true — the buyer should hear the more meaningful one
        $this->event->update([
            'status' => Event::STATUS_CANCELLED,
            'date'   => now()->subMonth(),
        ]);

        $this->withToken($this->userToken())
            ->postJson('/api/orders', $this->orderPayload())
            ->assertJsonPath('errors.event_id.0', 'This event has been cancelled.');
    }

    // ===== authorization =====

    #[Test]
    public function an_admin_can_create_an_event(): void
    {
        $this->withToken($this->adminToken())
            ->postJson('/api/events', [
                'name' => 'New Concert',
                'city' => 'Jeddah',
                'date' => now()->addMonths(6)->format('Y-m-d'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'active');
    }

    #[Test]
    public function a_regular_user_cannot_create_an_event(): void
    {
        // 403, not 401 — the server knows exactly who this is and refuses anyway
        $this->withToken($this->userToken())
            ->postJson('/api/events', [
                'name' => 'New Concert',
                'city' => 'Jeddah',
                'date' => now()->addMonths(6)->format('Y-m-d'),
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function creating_an_event_in_the_past_is_rejected(): void
    {
        $this->withToken($this->adminToken())
            ->postJson('/api/events', [
                'name' => 'Yesterday Concert',
                'city' => 'Riyadh',
                'date' => now()->subDay()->format('Y-m-d'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');
    }

    #[Test]
    public function an_invalid_status_is_rejected(): void
    {
        // Mongo would store status: "watermelon" happily — validation is the only guard
        $this->withToken($this->adminToken())
            ->postJson('/api/events', [
                'name'   => 'New Concert',
                'city'   => 'Jeddah',
                'date'   => now()->addMonths(6)->format('Y-m-d'),
                'status' => 'watermelon',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // ===== public reads =====

    #[Test]
    public function listing_events_requires_no_token(): void
    {
        $this->getJson('/api/events')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function the_city_filter_actually_filters(): void
    {
        $this->getJson('/api/events?city=Jeddah')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function a_single_event_includes_its_tickets(): void
    {
        $this->getJson("/api/events/{$this->event->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.type', 'VIP');
    }
}
