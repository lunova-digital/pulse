<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/today', '/projects', '/tasks', '/archived'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Your daily project status');
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'x@y.test', 'password' => 'password'])->assertNotFound();
    }

    public function test_user_is_created_from_env_config_by_the_seeder(): void
    {
        config(['pulse.email' => 'owner@pulse.test', 'pulse.password' => 'a-strong-password']);

        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class); // idempotent

        $this->assertSame(1, User::count());

        Livewire::test(Login::class)
            ->set('email', 'owner@pulse.test')
            ->set('password', 'a-strong-password')
            ->call('login')
            ->assertRedirect(route('today'));

        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'owner@pulse.test']);

        Livewire::test(Login::class)
            ->set('email', 'owner@pulse.test')
            ->set('password', 'nope')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
