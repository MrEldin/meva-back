<?php

namespace Tests;

use Database\Seeders\Test\TestingDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Traits\AuthenticatedUser;

abstract class TestCase extends BaseTestCase
{
    use AuthenticatedUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TestingDatabaseSeeder::class);

        $this->createAuthenticatedUser('super-admin');
    }
}
