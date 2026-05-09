<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateAdminUserCommand extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Create a Filament admin user.';

    public function handle(): int
    {
        $email = $this->askEmail();
        $password = $this->askPassword();

        $admin = AdminUser::create([
            'name' => str($email)->before('@')->toString(),
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Admin user {$admin->email} created.");

        return self::SUCCESS;
    }

    private function askEmail(): string
    {
        while (true) {
            $email = (string) $this->ask('Email');

            $validator = Validator::make(['email' => $email], [
                'email' => ['required', 'email', Rule::unique(AdminUser::class, 'email')],
            ]);

            if ($validator->passes()) {
                return $email;
            }

            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
        }
    }

    private function askPassword(): string
    {
        while (true) {
            $password = (string) $this->secret('Password');
            $confirmation = (string) $this->secret('Confirm password');

            $validator = Validator::make([
                'password' => $password,
                'password_confirmation' => $confirmation,
            ], [
                'password' => ['required', 'confirmed', 'min:8'],
            ]);

            if ($validator->passes()) {
                return $password;
            }

            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
        }
    }
}
