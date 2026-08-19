<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminTest extends TestCase
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

    // ===== updating events =====

    #[Test]
    public function an_admin_can_update_an_event(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson("/api/events/{$this->event->id}", ['status' => 'paused'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paused');
    }

    #[Test]
    public function a_partial_update_leaves_other_fields_alone(): void
    {
        // only status is sent — name and city must survive untouched
        $this->withToken($this->adminToken())
            ->patchJson("/api/events/{$this->event->id}", ['status' => 'paused']);

        $fresh = $this->event->fresh();

        $this->assertSame('Test Concert', $fresh->name);
        $this->assertSame('Riyadh', $fresh->city);
    }

    #[Test]
    public function a_regular_user_cannot_update_an_event(): void
    {
        $this->withToken($this->userToken())
            ->patchJson("/api/events/{$this->event->id}", ['status' => 'paused'])
            ->assertStatus(403);
    }

    #[Test]
    public function an_invalid_status_is_rejected_on_update(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson("/api/events/{$this->event->id}", ['status' => 'watermelon'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // ===== deleting events =====

    #[Test]
    public function an_admin_can_delete_an_event_with_no_orders(): void
    {
        $this->withToken($this->adminToken())
            ->deleteJson("/api/events/{$this->event->id}")
            ->assertStatus(204);

        $this->assertNull(Event::find($this->event->id));
    }

    #[Test]
    public function deleting_an_event_also_removes_its_ticket_types(): void
    {
        $this->withToken($this->adminToken())
            ->deleteJson("/api/events/{$this->event->id}");

        // ticket types are meaningless without their event
        $this->assertNull(Ticket::find($this->ticket->id));
    }

    #[Test]
    public function an_event_with_orders_cannot_be_deleted(): void
    {
        // place an order first
        $this->withToken($this->userToken())
            ->postJson('/api/orders', [
                'event_id'  => (string) $this->event->id,
                'ticket_id' => (string) $this->ticket->id,
                'quantity'  => 1,
            ]);

        $this->app['auth']->forgetGuards();

        // deleting would orphan that order and erase a financial record.
        // cancelling is the correct action instead
        $this->withToken($this->adminToken())
            ->deleteJson("/api/events/{$this->event->id}")
            ->assertStatus(422);

        $this->assertNotNull(Event::find($this->event->id));
    }

    // ===== ticket types =====

    #[Test]
    public function an_admin_can_create_a_ticket_type(): void
    {
        $this->withToken($this->adminToken())
            ->postJson("/api/events/{$this->event->id}/tickets", [
                'type'               => 'Early Bird',
                'price'              => 150,
                'quantity_available' => 200,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'Early Bird')
            ->assertJsonPath('data.sold_out', false);
    }

    #[Test]
    public function the_event_name_is_copied_from_the_event_not_the_request(): void
    {
        $this->withToken($this->adminToken())
            ->postJson("/api/events/{$this->event->id}/tickets", [
                'type'               => 'Early Bird',
                'price'              => 150,
                'quantity_available' => 200,
                'event_name'         => 'Injected Name',
            ]);

        $created = Ticket::where('type', 'Early Bird')->first();

        $this->assertSame('Test Concert', $created->event_name);
    }

    #[Test]
    public function a_regular_user_cannot_create_a_ticket_type(): void
    {
        $this->withToken($this->userToken())
            ->postJson("/api/events/{$this->event->id}/tickets", [
                'type'               => 'Early Bird',
                'price'              => 150,
                'quantity_available' => 200,
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function an_admin_can_update_a_ticket_price(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson("/api/tickets/{$this->ticket->id}", ['price' => 600])
            ->assertStatus(200)
            ->assertJsonPath('data.price', 600);
    }

    #[Test]
    public function changing_a_price_does_not_alter_existing_orders(): void
    {
        // this is the frozen-copy design proving itself
        $this->withToken($this->userToken())
            ->postJson('/api/orders', [
                'event_id'  => (string) $this->event->id,
                'ticket_id' => (string) $this->ticket->id,
                'quantity'  => 1,
            ]);

        $this->app['auth']->forgetGuards();

        $this->withToken($this->adminToken())
            ->patchJson("/api/tickets/{$this->ticket->id}", ['price' => 600]);

        // the order still says 500 — what it cost at purchase time
        $this->assertSame(500.0, Order::first()->unit_price);
        $this->assertSame(500.0, Order::first()->total);
    }

    #[Test]
    public function a_negative_price_is_rejected(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson("/api/tickets/{$this->ticket->id}", ['price' => -50])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    #[Test]
    public function a_ticket_type_with_orders_cannot_be_deleted(): void
    {
        $this->withToken($this->userToken())
            ->postJson('/api/orders', [
                'event_id'  => (string) $this->event->id,
                'ticket_id' => (string) $this->ticket->id,
                'quantity'  => 1,
            ]);

        $this->app['auth']->forgetGuards();

        $this->withToken($this->adminToken())
            ->deleteJson("/api/tickets/{$this->ticket->id}")
            ->assertStatus(422);
    }
}