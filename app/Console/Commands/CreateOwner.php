<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Membuat akun Owner pertama. Password sementara ditampilkan SEKALI di layar,
 * tidak disimpan di mana pun, dan wajib diganti saat login pertama.
 *
 *   php artisan erp:create-owner "Nama" email@contoh.com
 */
class CreateOwner extends Command
{
    protected $signature = 'erp:create-owner {name} {email}';

    protected $description = 'Buat akun Owner dengan password sementara';

    public function handle(): int
    {
        $email = Str::lower($this->argument('email'));

        if (User::where('email', $email)->exists()) {
            $this->error("Email {$email} sudah terdaftar.");

            return self::FAILURE;
        }

        $password = Str::password(14, symbols: false);

        $user = new User([
            'name' => $this->argument('name'),
            'email' => $email,
            'password' => $password,
        ]);
        $user->role = Role::Owner;
        $user->is_active = true;
        $user->must_change_password = true;
        $user->save();

        $this->info('Akun Owner dibuat.');
        $this->line("Email             : {$email}");
        $this->line("Password sementara: {$password}");
        $this->warn('Catat sekarang — password ini tidak akan ditampilkan lagi dan wajib diganti saat login pertama.');

        return self::SUCCESS;
    }
}
