<?php

namespace Tests\Feature;

use App\Models\IdHolder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_holders_are_not_seeded_by_default(): void
    {
        config(['idms.seed_demo_holders' => false]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, IdHolder::count());
    }

    public function test_demo_holders_require_explicit_opt_in(): void
    {
        config(['idms.seed_demo_holders' => true]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, IdHolder::count());
        $this->assertDatabaseHas('id_holders', ['id_number' => 'STU-2026-001']);
    }
}