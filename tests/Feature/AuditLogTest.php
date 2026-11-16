<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;

function ketuaUntukAudit(): User
{
    $user = User::factory()->create();
    $user->role = Role::KetuaLab;
    $user->save();

    return $user;
}

test('hanya ketua lab yang dapat melihat audit log', function () {
    $this->actingAs(User::factory()->create())->get('/audit-logs')->assertForbidden();
    $this->actingAs(ketuaUntukAudit())->get('/audit-logs')->assertOk();
});

test('pembuatan akun tercatat tanpa password', function () {
    $ketua = ketuaUntukAudit();

    $this->actingAs($ketua)->post('/accounts', [
        'name' => 'Dosen Uji',
        'email' => 'dosen@example.com',
        'password' => 'password123',
        'role' => 'dosen',
    ]);

    $log = AuditLog::where('action', 'account.created')->first();

    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($ketua->id);
    expect(json_encode($log->metadata))->not->toContain('password123');
});

test('perubahan password tercatat sebagai diubah, bukan isinya', function () {
    $ketua = ketuaUntukAudit();
    $target = User::factory()->create();

    $this->actingAs($ketua)->put("/accounts/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'password' => 'passwordbaru123',
        'role' => 'dosen',
    ]);

    $log = AuditLog::where('action', 'account.updated')->first();

    expect($log->metadata['changes']['password'])->toBe('diubah');
    expect(json_encode($log->metadata))->not->toContain('passwordbaru123');
});

test('login gagal tercatat', function () {
    $this->post(route('login'), ['email' => 'tidakada@example.com', 'password' => 'salah']);

    expect(AuditLog::where('action', 'auth.login_failed')->where('result', 'failed')->exists())->toBeTrue();
});

test('audit log tidak dapat diubah atau dihapus dari aplikasi', function () {
    Audit::record('uji.catatan');
    $log = AuditLog::first();

    expect(fn () => $log->delete())->toThrow(LogicException::class);

    $log->action = 'diubah';
    expect(fn () => $log->save())->toThrow(LogicException::class);
});