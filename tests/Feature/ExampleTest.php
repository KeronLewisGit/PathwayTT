<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_url_sends_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login', absolute: false));
    }

    public function test_the_root_url_sends_users_to_their_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard', absolute: false));
    }
}
