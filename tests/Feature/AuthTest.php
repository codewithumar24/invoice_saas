<?php

namespace Tests\Feature;

use App\Mail\TemplateMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TwoFactorService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private array $registration = [
        'name' => 'Umar', 'email' => 'umar@example.com', 'password' => 'Secret@123', 'password_confirmation' => 'Secret@123',
        'country' => 'PK', 'timezone' => 'Asia/Karachi', 'business_name' => 'UmarTechLab', 'terms' => '1',
    ];

    public function test_registration_creates_user_workspace_roles_and_sends_verification(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $this->post('/register', $this->registration)->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'umar@example.com')->firstOrFail();
        $tenant = Tenant::where('name', 'UmarTechLab')->firstOrFail();

        $this->assertTrue(Hash::check('Secret@123', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->isOwnerOf($tenant->id));
        $this->assertSame(8, DB::table('roles')->where('tenant_id', $tenant->id)->count());
        $this->assertSame('trial', $tenant->subscription->status);
        Mail::assertQueued(TemplateMail::class, fn ($mail) => $mail->hasTo('umar@example.com'));
    }

    public function test_registration_validates_input(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/register', ['password' => 'weak', 'password_confirmation' => 'x'] + $this->registration)
            ->assertSessionHasErrors(['password']);
        $this->post('/register', array_diff_key($this->registration, ['terms' => 1]))->assertSessionHasErrors(['terms']);
        $this->assertSame(0, User::where('email', 'umar@example.com')->count());
    }

    public function test_unverified_users_cannot_open_the_app_until_they_verify(): void
    {
        [$user] = $this->workspace();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));

        // A tampered link is rejected; the signed link works
        $this->get('/email/verify/'.$user->id.'/'.sha1($user->email))->assertStatus(403);
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]))->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_logout_and_wrong_password(): void
    {
        [$user] = $this->workspace();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_attempts', ['email' => $user->email, 'successful' => true]);
        $this->assertDatabaseHas('login_attempts', ['email' => $user->email, 'successful' => false]);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        [$user] = $this->workspace();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    }

    public function test_suspended_users_cannot_sign_in(): void
    {
        [$user] = $this->workspace();
        $user->forceFill(['status' => 'suspended'])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_flow(): void
    {
        [$user] = $this->workspace();
        Mail::fake();

        // Unknown addresses get the same answer and no email
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('success');
        Mail::assertNothingQueued();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success');
        $token = null;
        Mail::assertQueued(TemplateMail::class, function ($mail) use (&$token) {
            preg_match('~reset-password/([A-Za-z0-9]+)~', $mail->actionUrl, $match);
            $token = $match[1] ?? null;

            return true;
        });

        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'), 'only a hash of the token is stored');

        $this->post('/reset-password', ['token' => 'wrong', 'email' => $user->email, 'password' => 'NewSecret@123', 'password_confirmation' => 'NewSecret@123'])->assertSessionHasErrors('email');
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'NewSecret@123', 'password_confirmation' => 'NewSecret@123'])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewSecret@123', $user->fresh()->password));
        $this->assertSame(0, DB::table('password_reset_tokens')->count(), 'a token works once');
    }

    public function test_two_factor_authentication(): void
    {
        [$user] = $this->workspace();
        $service = app(TwoFactorService::class);

        // Enable: password -> secret in session -> confirm with a code
        $this->actingAs($user)->post('/two-factor/enable', ['current_password' => 'password'])->assertRedirect(route('account.security'));
        $secret = session('two_factor_setup');
        $this->post('/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/two-factor/confirm', ['code' => $service->codeAt($secret, intdiv(time(), 30))])->assertSessionHas('recovery_codes');
        $user->refresh();
        $this->assertTrue($user->hasTwoFactor());
        $this->assertNotSame($secret, $user->getRawOriginal('two_factor_secret'), 'the secret is encrypted at rest');

        // Sign in: password alone is not enough
        $this->post('/logout');
        $this->freshRequest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['code' => $service->codeAt($secret, intdiv(time(), 30))])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // A backup code works once
        $code = $user->two_factor_recovery_codes[0];
        $this->post('/logout');
        $this->freshRequest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $code])->assertRedirect(route('dashboard'));
        $this->assertNotContains($code, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_changing_password_requires_the_current_one(): void
    {
        [$user] = $this->workspace();

        $this->actingAs($user)->put('/account/password', ['current_password' => 'nope', 'password' => 'NewSecret@123', 'password_confirmation' => 'NewSecret@123'])->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/account/password', ['current_password' => 'password', 'password' => 'NewSecret@123', 'password_confirmation' => 'NewSecret@123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewSecret@123', $user->fresh()->password));
    }

    public function test_team_invitation_flow(): void
    {
        [$owner, $tenant] = $this->workspace();
        Mail::fake();
        $roleId = DB::table('roles')->where('tenant_id', $tenant->id)->where('slug', 'accountant')->value('id');

        $this->actingAs($owner)->post('/team/invite', ['email' => 'sara@example.com', 'role_id' => $roleId])->assertSessionHasNoErrors();
        $token = DB::table('invitations')->value('token');

        $this->post('/logout');
        $this->freshRequest();
        $this->get('/invitations/'.$token)->assertOk()->assertSee($tenant->name);
        $this->post('/invitations/'.$token, ['name' => 'Sara', 'password' => 'Secret@123', 'password_confirmation' => 'Secret@123'])->assertRedirect(route('dashboard'));

        $sara = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertNotNull($sara->email_verified_at);
        $this->assertDatabaseHas('user_roles', ['user_id' => $sara->id, 'role_id' => $roleId, 'tenant_id' => $tenant->id]);

        // The link cannot be used twice
        $this->freshRequest();
        $this->get('/invitations/'.$token)->assertNotFound();
    }
}
