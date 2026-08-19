<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderTest extends TestCase
{
    private User $user;
    private Event $event;
    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Test Buyer',
            'email'    => 'buyer@test.com',
            'password' => 'password123',
            'role'     => 'user',
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

    // issues a real token for the user, the same way login does
    private function tokenFor(User $user): string
    {
        return $user->createToken('test', ['orders:create', 'orders:read'])->accessToken;
    }

    private function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'event_id'  => (string) $this->event->id,
            'ticket_id' => (string) $this->ticket->id,
            'quantity'  => 2,
        ], $overrides);
    }

    // ===== happy path =====

    #[Test]
    public function an_authenticated_user_can_place_an_order(): void
    {
        $response = $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.total', 1000)
            ->assertJsonPath('data.status', 'paid');
    }

    #[Test]
    public function placing_an_order_decrements_available_stock(): void
    {
        // the most important test here — this logic was written in step 2
        // and never verified. if decrement() silently failed, every other
        // test would still pass while the system oversold every event
        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['quantity' => 3]));

        $this->assertSame(7, $this->ticket->fresh()->quantity_available);
    }

    #[Test]
    public function the_order_records_the_correct_quantity_and_status(): void
    {
        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['quantity' => 4]));

        $order = Order::first();

        $this->assertSame(4, $order->quantity);
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->payment_reference);
    }

    // ===== financial integrity — Uzair's third suggestion =====

    #[Test]
    public function the_stored_unit_price_matches_the_ticket_price(): void
    {
        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload());

        $this->assertSame(500.0, Order::first()->unit_price);
    }

    #[Test]
    public function unit_price_times_quantity_equals_the_stored_total(): void
    {
        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['quantity' => 3]));

        $order = Order::first();

        // no discrepancy between the line item and the order total
        $this->assertSame(
            $order->unit_price * $order->quantity,
            $order->total
        );
    }

    // ===== security =====

    #[Test]
    public function a_client_supplied_total_is_ignored(): void
    {
        // someone trying to buy a 500 SAR ticket for 1 SAR
        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['total' => 1]));

        $this->assertSame(1000.0, Order::first()->total);
    }

    #[Test]
    public function a_client_supplied_status_is_ignored(): void
    {
        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['status' => 'refunded']));

        $this->assertSame(Order::STATUS_PAID, Order::first()->status);
    }

    #[Test]
    public function a_client_cannot_order_on_someone_elses_behalf(): void
    {
        $other = User::create([
            'name'     => 'Someone Else',
            'email'    => 'other@test.com',
            'password' => 'password123',
            'role'     => 'user',
        ]);

        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['user_id' => (string) $other->id]));

        // the order belongs to the token holder, not the injected id
        $this->assertSame((string) $this->user->id, (string) Order::first()->user_id);
    }

    // ===== boundaries =====

    #[Test]
    public function ordering_more_than_available_is_rejected(): void
    {
        $this->ticket->update(['quantity_available' => 3]);

        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['quantity' => 5]))
            ->assertStatus(422)
            ->assertJsonPath('errors.quantity.0', 'Only 3 tickets remaining, 5 requested.');
    }

    #[Test]
    public function ordering_exactly_the_remaining_stock_succeeds(): void
    {
        $this->ticket->update(['quantity_available' => 4]);

        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['quantity' => 4]))
            ->assertStatus(201);

        $this->assertSame(0, $this->ticket->fresh()->quantity_available);
    }

    #[Test]
    public function stock_can_never_go_negative(): void
    {
        $this->ticket->update(['quantity_available' => 2]);

        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['quantity' => 3]));

        $this->assertGreaterThanOrEqual(0, $this->ticket->fresh()->quantity_available);
    }

    #[Test]
    public function zero_quantity_is_rejected_by_validation(): void
    {
        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload(['quantity' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    // ===== auth =====

    #[Test]
    public function an_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/orders', $this->orderPayload())
            ->assertStatus(401);
    }

    #[Test]
    public function a_user_only_sees_their_own_orders(): void
    {
        $other = User::create([
            'name'     => 'Someone Else',
            'email'    => 'other@test.com',
            'password' => 'password123',
            'role'     => 'user',
        ]);

        // our user places one order
        $this->withToken($this->tokenFor($this->user))
            ->postJson('/api/orders', $this->orderPayload());
              // from its own token rather than reusing the first request's identity
        $this->app['auth']->forgetGuards();

     

        // the other user should see none of it
        $this->withToken($this->tokenFor($other))
            ->getJson('/api/orders')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
