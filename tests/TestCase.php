<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase's initial `migrate:fresh` (per test process, not per
     * test) only seeds if configured here — whichever test class runs first
     * decides for the whole suite, so this has to live on the base class
     * rather than on individual RBAC test classes.
     */
    protected $seed = true;

    protected string $seeder = RolePermissionSeeder::class;

    /**
     * RefreshDatabase wipes whatever database is configured. A cached config
     * (php artisan optimize) overrides phpunit.xml and points it at the real
     * one, so stop before any trait can run.
     */
    protected function setUpTraits()
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Tes dihentikan: database bukan SQLite in-memory. Jalankan "php artisan optimize:clear" lebih dulu.');
        }

        return parent::setUpTraits();
    }
}
