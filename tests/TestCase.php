<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearDatabase();
    }

    // Laravel's RefreshDatabase trait wraps each test in a SQL transaction and
    // rolls it back. that doesn't map onto Mongo, so we drop the collections
    // ourselves before each test instead
    protected function clearDatabase(): void
    {
        $collections = [
            'users',
            'events',
            'tickets',
            'orders',
            'oauth_access_tokens',
            'oauth_refresh_tokens',
        ];

        foreach ($collections as $collection) {
            DB::connection('mongodb')->table($collection)->delete();
        }
    }
}