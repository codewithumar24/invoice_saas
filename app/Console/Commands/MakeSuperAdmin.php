<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'saas:super-admin {email} {--password= : Create the user with this password if it does not exist}';

    protected $description = 'Give a user platform administrator access';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user && $this->option('password')) {
            $user = new User(['name' => 'Platform Admin', 'email' => $this->argument('email'), 'password' => $this->option('password')]);
            $user->email_verified_at = now();
        }

        if (! $user) {
            $this->error('User not found. Pass --password to create it.');

            return self::FAILURE;
        }

        $user->is_super_admin = true;
        $user->save();
        $this->info("{$user->email} is now a platform administrator.");

        return self::SUCCESS;
    }
}
