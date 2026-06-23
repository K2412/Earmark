<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-first-user {--name=} {--email=} {--password=}')]
#[Description('Create the first household user when no users exist')]
class CreateFirstUserCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('A household user already exists.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $this->info('First household user created.');

        return self::SUCCESS;
    }
}
