<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_page_works()
    {
        $this
            ->get(route('login'))
            ->assertStatus(200)
            ->assertSee('Login');
    }

    /** @test */
    public function a_guest_can_login_with_correct_credentials()
    {
        $user = factory(User::class)->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password'
        ])->assertSessionMissing('errors');

        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function a_guest_cannot_login_with_incorrect_credentials()
    {
        $user = factory(User::class)->create();

        $this
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'invalid'
            ])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    /** @test */
    public function dashboard_page_works()
    {
        $user = factory(User::class)->create();

        $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertStatus(200)
            ->assertSee($user->name)
            ->assertSee('Dashboard');
    }

    /** @test */
    public function a_user_can_logout()
    {
        $user = factory(User::class)->create();

        $this
            ->actingAs($user)
            ->post(route('logout'))
            ->assertStatus(302);

        $this->assertGuest();
    }

    /** @test */
    public function register_page_works()
    {
        $this
            ->get(route('register'))
            ->assertStatus(200)
            ->assertSee('Register');
    }

    /** @test */
    public function a_guest_can_register()
    {
        $guest = factory(User::class)->make();

        $this->post(route('register'), [
            'name' => $guest->name,
            'email' => $guest->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionMissing('errors');

        $this->assertAuthenticatedAs(
            User::whereEmail($guest->email)->first()
        );
    }

    /** @test */
    public function forgot_password_page_works()
    {
        $this
            ->get(route('password.request'))
            ->assertStatus(200)
            ->assertSee('Reset Password');
    }

    /** @test */
    public function a_user_can_request_a_password_reset_email()
    {
        $user = factory(User::class)->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertSessionMissing('errors');
    }
}
