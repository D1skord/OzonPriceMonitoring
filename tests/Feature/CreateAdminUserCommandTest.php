<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_admin_user_from_console_prompts(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Email', 'admin@example.com')
            ->expectsQuestion('Password', 'secret-password')
            ->expectsQuestion('Confirm password', 'secret-password')
            ->expectsOutput('Admin user admin@example.com created.')
            ->assertSuccessful();

        $admin = AdminUser::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('admin', $admin->name);
        $this->assertTrue(Hash::check('secret-password', $admin->password));
    }
}
