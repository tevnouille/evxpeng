<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $email = 'u@example.com', bool $admin = false): User
    {
        return app(UserProvisioner::class)->create($email, 'motdepasse-solide', $admin);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/recharges')->assertRedirect('/login');
    }

    public function test_login_page_is_public(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_with_valid_credentials(): void
    {
        $this->user();

        $this->post('/login', ['email' => 'u@example.com', 'password' => 'motdepasse-solide'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->user();

        $this->post('/login', ['email' => 'u@example.com', 'password' => 'mauvais'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_account_cannot_login(): void
    {
        $user = $this->user();
        $user->forceFill(['approved_at' => null])->save();

        $this->post('/login', ['email' => 'u@example.com', 'password' => 'motdepasse-solide'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_closes_session(): void
    {
        $this->actingAs($this->user())->post('/deconnexion')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $this->actingAs($this->user())->get('/admin/utilisateurs')->assertNotFound();
    }

    public function test_admin_creates_account_with_role(): void
    {
        $this->actingAs($this->user('boss@example.com', true))
            ->post('/admin/utilisateurs', [
                'email' => 'new@example.com',
                'password' => 'un-autre-mot-de-passe',
                'is_admin' => '1',
            ])->assertRedirect();

        $new = User::firstWhere('email', 'new@example.com');
        $this->assertNotNull($new);
        $this->assertTrue($new->is_admin);
        $this->assertNotNull($new->approved_at);
    }

    public function test_admin_rejects_short_password(): void
    {
        $this->actingAs($this->user('boss@example.com', true))
            ->post('/admin/utilisateurs', ['email' => 'new@example.com', 'password' => 'court'])
            ->assertSessionHasErrors('password');
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = $this->user('boss@example.com', true);

        $this->actingAs($admin)->put("/admin/utilisateurs/{$admin->id}/role", ['is_admin' => '0']);

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_resets_a_password(): void
    {
        $user = $this->user();

        $this->actingAs($this->user('boss@example.com', true))
            ->put("/admin/utilisateurs/{$user->id}/mot-de-passe", ['password' => 'nouveau-mot-de-passe'])
            ->assertRedirect();

        $this->post('/deconnexion');
        $this->post('/login', ['email' => 'u@example.com', 'password' => 'nouveau-mot-de-passe']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_changes_own_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/mon-compte/mot-de-passe', [
            'current_password' => 'motdepasse-solide',
            'password' => 'encore-un-mot-de-passe',
            'password_confirmation' => 'encore-un-mot-de-passe',
        ])->assertRedirect('/mon-compte');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('encore-un-mot-de-passe', $user->fresh()->password));
    }
}
