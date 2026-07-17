<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class MakeAdmin extends Command
{
    protected $signature = 'make:admin
                            {--name= : Nama admin}
                            {--email= : Email admin}
                            {--password= : Password admin (min 8 karakter)}';

    protected $description = 'Buat admin baru';

    public function handle(): int
    {
        $name = $this->option('name') ?? text('Nama', required: true);
        $email = $this->option('email') ?? text('Email', required: true, validate: fn (string $value): ?string => match (true) {
            ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Format email tidak valid.',
            User::where('email', $value)->exists() => 'Email sudah digunakan.',
            default => null,
        });
        $password = $this->option('password') ?? password('Password', required: true, validate: fn (string $value): ?string => match (true) {
            strlen($value) < 8 => 'Password minimal 8 karakter.',
            default => null,
        });

        // Validate email again for non-interactive mode
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error('Format email tidak valid: '.$email);

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            error('Email sudah digunakan: '.$email);

            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            error('Password minimal 8 karakter.');

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->email_verified_at = now();
        $user->save();

        $user->assignRole('admin');

        $this->info("Admin {$name} <{$email}> berhasil dibuat.");

        return self::SUCCESS;
    }
}
