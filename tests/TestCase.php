<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (collect(class_uses_recursive(static::class))->contains(RefreshDatabase::class)) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }
    }
}
