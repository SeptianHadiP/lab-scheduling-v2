<?php

namespace App\Console\Commands;

use App\Models\Lab;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
// use Illuminate\Console\Attributes\Description;
// use Illuminate\Console\Attributes\Signature;

use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\password;

// #[Signature('app:create-ketua-lab')]
// #[Description('Command description')]
class CreateKetuaLab extends Command
{
    /**
     * Execute the console command.
     */
    protected $signature = 'lab:create-ketua';

    protected $description = 'Membuat akun Ketua Lab untuk satu laboratorium (satu Ketua Lab aktif per lab)';

    public function handle(): int
    {
        $labs = Lab::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();

        if ($labs === []) {
            $this->error('Belum ada laboratorium. Jalankan: php artisan db:seed --class=LabSeeder');

            return self::FAILURE;
        }

        $labId = (int) select('Ketua Lab untuk laboratorium mana?', $labs);

        $sudahAda = User::where('role', Role::KetuaLab->value)
            ->where('lab_id', $labId)
            ->where('is_active', true)
            ->exists();

        if ($sudahAda) {
            $this->error('Laboratorium ini sudah memiliki Ketua Lab aktif.');

            return self::FAILURE;
        }

        $name = text('Nama lengkap', required: true);

        $email = text(
            'Email',
            required: true,
            validate: fn (string $v) => match (true) {
                ! filter_var($v, FILTER_VALIDATE_EMAIL) => 'Format email tidak valid.',
                User::where('email', strtolower($v))->exists() => 'Email sudah terdaftar.',
                default => null,
            },
        );

        $pass = password(
            'Password (minimal 8 karakter)',
            required: true,
            validate: fn (string $v) => strlen($v) < 8 ? 'Minimal 8 karakter.' : null,
        );

        $user = new User([
            'name' => $name,
            'email' => strtolower($email),
            'password' => $pass,
        ]);
        $user->lab_id = $labId;
        $user->role = Role::KetuaLab;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Ketua Lab dibuat: {$user->email}");

        return self::SUCCESS;
    }

}
