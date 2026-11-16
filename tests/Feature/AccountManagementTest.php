<?php

use App\Enums\Role;
use App\Models\Lab;
use App\Models\User;

function userDengan(Role $role, ?int $labId = null): User
{
    $user = User::factory()->create();
    $user->role = $role;
    $user->lab_id = $labId;
    $user->save();

    return $user;
}

test('dosen tidak dapat membuka maupun membuat akun', function () {
    $dosen = userDengan(Role::Dosen);

    $this->actingAs($dosen)->get('/accounts')->assertForbidden();
    $this->actingAs($dosen)->post('/accounts', [])->assertForbidden();
});

test('ketua lab dapat membuat akun dosen', function () {
    $this->actingAs(userDengan(Role::KetuaLab))
        ->post('/accounts', [
            'name' => 'Dosen Uji',
            'email' => 'Dosen@Example.com',
            'password' => 'password123',
            'role' => 'dosen',
        ])
        ->assertRedirect('/accounts');

    $this->assertDatabaseHas('users', ['email' => 'dosen@example.com', 'role' => 'dosen']);
});

test('asisten lab dan ketua lab wajib memiliki lab', function (string $role) {
    $this->actingAs(userDengan(Role::KetuaLab))
        ->post('/accounts', [
            'name' => 'Uji',
            'email' => 'uji@example.com',
            'password' => 'password123',
            'role' => $role,
        ])
        ->assertSessionHasErrors('lab_id');
})->with(['asisten_lab', 'ketua_lab']);

test('satu lab boleh memiliki banyak asisten lab', function () {
    $lab = Lab::create(['name' => 'Lab Uji', 'code' => 'UJI']);
    $ketua = userDengan(Role::KetuaLab);

    foreach (['a1', 'a2', 'a3'] as $nama) {
        $this->actingAs($ketua)
            ->post('/accounts', [
                'name' => $nama,
                'email' => "{$nama}@example.com",
                'password' => 'password123',
                'role' => 'asisten_lab',
                'lab_id' => $lab->id,
            ])
            ->assertSessionHasNoErrors();
    }

    expect(User::where('role', 'asisten_lab')->where('lab_id', $lab->id)->count())->toBe(3);
});

test('satu lab hanya boleh memiliki satu ketua lab aktif', function () {
    $lab = Lab::create(['name' => 'Lab Uji', 'code' => 'UJI']);
    userDengan(Role::KetuaLab, $lab->id);

    $this->actingAs(userDengan(Role::KetuaLab))
        ->post('/accounts', [
            'name' => 'Ketua Kedua',
            'email' => 'ketua2@example.com',
            'password' => 'password123',
            'role' => 'ketua_lab',
            'lab_id' => $lab->id,
        ])
        ->assertSessionHasErrors('lab_id');
});

test('ketua lab lama tidak dapat diaktifkan kembali jika lab sudah punya ketua aktif', function () {
    $lab = Lab::create(['name' => 'Lab Uji', 'code' => 'UJI']);

    $lama = userDengan(Role::KetuaLab, $lab->id);
    $lama->is_active = false;
    $lama->save();

    userDengan(Role::KetuaLab, $lab->id);
    $pelaku = userDengan(Role::KetuaLab);

    $this->actingAs($pelaku)
        ->patch("/accounts/{$lama->id}/status")
        ->assertSessionHasErrors('status');

    expect($lama->fresh()->is_active)->toBeFalse();
});

test('ketua lab tidak dapat menonaktifkan akunnya sendiri', function () {
    $ketua = userDengan(Role::KetuaLab);

    $this->actingAs($ketua)
        ->patch("/accounts/{$ketua->id}/status")
        ->assertSessionHasErrors('status');

    expect($ketua->fresh()->is_active)->toBeTrue();
});

test('akun nonaktif otomatis dikeluarkan', function () {
    $dosen = userDengan(Role::Dosen);
    $dosen->is_active = false;
    $dosen->save();

    $this->actingAs($dosen)->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});