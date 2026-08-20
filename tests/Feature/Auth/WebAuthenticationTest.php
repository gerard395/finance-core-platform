<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Identity\ProvisionUserAccount;
use App\Application\Identity\UserRepository;
use App\Domain\Identity\Entities\User as DomainUser;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use App\Models\User as AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class WebAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('active@example.com|127.0.0.1');
        RateLimiter::clear('unknown@example.com|127.0.0.1');
    }

    public function test_guest_can_view_login_and_protected_route_redirects_to_it(): void
    {
        $this->get('/login')->assertOk()->assertSee('Inloggen')->assertSee('E-mailadres');
        $this->get('/app')->assertRedirect('/login');
    }

    public function test_active_domain_user_can_login_and_view_placeholder(): void
    {
        $this->provisionActiveUser();

        $response = $this->post('/login', [
            'email' => 'active@example.com',
            'password' => 'correct-secure-password',
        ]);

        $response->assertRedirect('/app');
        $this->assertAuthenticated();
        $this->get('/app')->assertOk()->assertSee('Active User')->assertSee('Ingelogd');
        $this->get('/login')->assertRedirect('/app');
    }

    public function test_wrong_password_and_unknown_email_use_same_generic_error(): void
    {
        $this->provisionActiveUser();

        $wrongPassword = $this->from('/login')->post('/login', [
            'email' => 'active@example.com',
            'password' => 'wrong-password',
        ]);
        $unknownEmail = $this->from('/login')->post('/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ]);

        $wrongPassword->assertRedirect('/login')->assertSessionHasErrors([
            'email' => 'De inloggegevens zijn ongeldig.',
        ]);
        $unknownEmail->assertRedirect('/login')->assertSessionHasErrors([
            'email' => 'De inloggegevens zijn ongeldig.',
        ]);
        $this->assertGuest();
    }

    public function test_inactive_domain_user_is_logged_out_and_denied(): void
    {
        $userId = $this->provisionActiveUser();
        $domainUser = (new EloquentUserRepository)->findById($userId);
        $domainUser->deactivate();
        (new EloquentUserRepository)->save($domainUser);

        $this->post('/login', [
            'email' => 'active@example.com',
            'password' => 'correct-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_missing_domain_bridge_resolution_is_denied_without_provisioning(): void
    {
        $this->provisionActiveUser();
        $this->app->instance(UserRepository::class, new class implements UserRepository
        {
            public function findById(UserId $id): ?DomainUser
            {
                return null;
            }

            public function save(DomainUser $user): void
            {
                throw new \LogicException('Login must never provision a Domain User.');
            }
        });

        $this->post('/login', [
            'email' => 'active@example.com',
            'password' => 'correct-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        self::assertSame(1, AuthUser::query()->count());
    }

    public function test_logout_is_post_only_and_ends_access(): void
    {
        $this->provisionActiveUser();
        $this->post('/login', [
            'email' => 'active@example.com',
            'password' => 'correct-secure-password',
        ]);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/app')->assertRedirect('/login');
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_repeated_failed_logins_are_rate_limited_but_other_key_can_succeed(): void
    {
        $this->provisionActiveUser();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'unknown@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        self::assertTrue(RateLimiter::tooManyAttempts('unknown@example.com|127.0.0.1', 5));
        $this->post('/login', [
            'email' => 'active@example.com',
            'password' => 'correct-secure-password',
        ])->assertRedirect('/app');
        $this->assertAuthenticated();
    }

    private function provisionActiveUser(): UserId
    {
        $userId = new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440001'));
        $this->app->make(ProvisionUserAccount::class)->execute(
            $userId,
            new DisplayName('Active User'),
            new EmailAddress('active@example.com'),
            'correct-secure-password',
        );

        return $userId;
    }
}
