<?php

namespace App\Console\Commands;

use App\Actions\Households\CreateHousehold;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateFirstUserCommand extends Command
{
    protected $signature = 'earmark:create-first-user
                            {--email= : Email address for the new user}
                            {--name= : Full name for the new user}
                            {--password= : Password (prompted if omitted)}';

    protected $description = 'Bootstrap the first household by creating a verified user and attached household. Refuses to run if any users already exist.';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('Refusing to run: at least one user already exists. Use the invite-link flow to add more users.');

            return self::FAILURE;
        }

        $email = $this->option('email') ?: $this->ask('Email');
        $name = $this->option('name') ?: $this->ask('Name');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $household = CreateHousehold::run($user, "{$name}'s Household");

        $this->info("Created user {$user->email} (id={$user->id}) and household '{$household->name}' (id={$household->id}). Email pre-verified.");

        return self::SUCCESS;
    }
}
