<?php

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:user
        {--name= : The user\'s name}
        {--email= : The user\'s email address}
        {--password= : The user\'s password (prompted when omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a user (with a personal team) — the way to get the first account on a fresh install';

    public function handle(CreateNewUser $creator): int
    {
        $name = $this->option('name') ?? $this->ask('Name');
        $email = $this->option('email') ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password');

        try {
            $user = $creator->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info("User {$user->email} created. You can log in now.");

        return self::SUCCESS;
    }
}
