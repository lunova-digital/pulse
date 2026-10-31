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
        $this->assertTrue(User::first()->isOwner());

        Livewire::test(Login::class)
            ->set('email', 'owner@pulse.test')
            ->set('password', 'a-strong-password')
            ->call('login')
            ->assertRedirect(url('/'));

        $this->assertAuthenticated();
    }

    public function test_deactivated_users_cannot_sign_in_and_are_signed_out(): void
    {
        $user = User::factory()->member()->create(['email' => 'gone@pulse.test', 'is_active' => false]);

        Livewire::test(Login::class)
            ->set('email', 'gone@pulse.test')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');
        $this->assertGuest();

        // A session that was open when the account was deactivated ends on the next request.
        $this->actingAs($user)->get('/daily')->assertRedirect('/login');
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
