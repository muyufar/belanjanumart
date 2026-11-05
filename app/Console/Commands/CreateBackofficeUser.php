<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateBackofficeUser extends Command
{
    protected $signature = 'marketplace:create-backoffice-user
                            {email : Email login backoffice}
                            {--name=Staff Backoffice : Nama tampilan}
                            {--password= : Password (min 8); kosong = prompt}';

    protected $description = 'Buat akun staff backoffice (email + password) untuk /backoffice/masuk';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $name = (string) $this->option('name');

        if ($existing = User::where('email', $email)->first()) {
            $payload = ['marketplace_role' => User::ROLE_BACKOFFICE];
            $password = trim((string) ($this->option('password') ?: ''));
            if ($password !== '') {
                if (strlen($password) < 8) {
                    $this->error('Password minimal 8 karakter.');

                    return self::FAILURE;
                }
                $payload['password'] = Hash::make($password);
            }
            $existing->update($payload);
            $this->info("Akun {$email} diperbarui sebagai backoffice.");

            return self::SUCCESS;
        }

        $password = $this->resolvePassword();
        if ($password === '' || strlen($password) < 8) {
            $this->error('Password wajib (min 8 karakter) untuk akun baru.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'marketplace_role' => User::ROLE_BACKOFFICE,
        ]);

        $this->info("Backoffice user dibuat: {$email}");
        $this->line('Login: '.url('/backoffice/masuk'));

        return self::SUCCESS;
    }

    protected function resolvePassword(): string
    {
        $password = (string) ($this->option('password') ?: '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                $this->error('Password minimal 8 karakter.');

                exit(self::FAILURE);
            }

            return $password;
        }

        return $this->secret('Password backoffice (min 8 karakter)') ?: '';
    }
}
