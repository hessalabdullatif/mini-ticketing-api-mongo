<?php

namespace Tests\Unit;

use App\Contracts\PaymentGateway;
use App\Services\OrderService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // a stub gateway — calculateTotal never touches it, but the constructor
        // requires one. this is only possible because we inject the dependency
        // rather than newing it up inside the service
        $gateway = new class implements PaymentGateway {
            public function charge(float $amount, array $meta = []): string
            {
                return 'test_reference';
            }

            public function name(): string
            {
                return 'test';
            }
        };

        $this->service = new OrderService($gateway);
    }

    #[Test]
    public function it_multiplies_unit_price_by_quantity(): void
    {
        $this->assertSame(1000.0, $this->service->calculateTotal(500, 2));
    }

    #[Test]
    public function it_rounds_to_two_decimal_places(): void
    {
        // 33.333 * 3 = 99.999 — money must not carry fractions of a halala
        $this->assertSame(100.0, $this->service->calculateTotal(33.333, 3));
    }

    #[Test]
    public function it_returns_zero_for_zero_quantity(): void
    {
        $this->assertSame(0.0, $this->service->calculateTotal(500, 0));
    }

    #[Test]
    public function it_handles_decimal_prices_without_drift(): void
    {
        // floating point: 19.99 * 3 is 59.970000000000006 in raw PHP.
        // round() is what keeps the stored total honest
        $this->assertSame(59.97, $this->service->calculateTotal(19.99, 3));
    }
}