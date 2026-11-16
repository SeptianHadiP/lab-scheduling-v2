<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\User;

function slsReservasi(User $pembuat, Lab $lab, string $status, array $sesi): Reservation
{
    $r = Reservation::forceCreate([
        'type' => 'booking',
        'lab_id' => $lab->id,
        'created_by' => $pembuat->id,
        'title' => 'Uji selesai',
        'status' => $status,
    ]);

    foreach ($sesi as $i => [$tanggal, $mulai, $selesai]) {
        $r->sessions()->create([
            'sequence_no' => $i + 1,
            'date' => $tanggal,
            'start_time' => $mulai,
            'end_time' => $selesai,
        ]);
    }

    return $r;
}

test('pengajuan disetujui yang seluruh pertemuannya sudah lewat ditandai selesai', function () {
    $this->travelTo(now()->setTime(12, 0));

    $lab = Lab::create(['name' => 'Lab Uji', 'code' => 'UJI']);
    $dosen = User::factory()->create();
    $dosen->role = Role::Dosen;
    $dosen->save();

    $kemarin = now()->subDay()->format('Y-m-d');
    $hariIni = now()->format('Y-m-d');
    $besok = now()->addDay()->format('Y-m-d');

    $lewat = slsReservasi($dosen, $lab, 'approved', [[$kemarin, '08:00', '09:30']]);
    $lewatJamHariIni = slsReservasi($dosen, $lab, 'approved', [[$hariIni, '08:00', '09:30']]);
    $belumJamHariIni = slsReservasi($dosen, $lab, 'approved', [[$hariIni, '13:00', '14:30']]);
    $sebagian = slsReservasi($dosen, $lab, 'approved', [[$kemarin, '08:00', '09:30'], [$besok, '08:00', '09:30']]);
    $pending = slsReservasi($dosen, $lab, 'pending', [[$kemarin, '08:00', '09:30']]);
    $dibatalkan = slsReservasi($dosen, $lab, 'cancelled', [[$kemarin, '08:00', '09:30']]);
    $kosong = slsReservasi($dosen, $lab, 'approved', []);

    $this->artisan('lab:tandai-selesai')->assertSuccessful();

    expect($lewat->fresh()->status->value)->toBe('completed');
    expect($lewatJamHariIni->fresh()->status->value)->toBe('completed');
    expect($belumJamHariIni->fresh()->status->value)->toBe('approved');
    expect($sebagian->fresh()->status->value)->toBe('approved');
    expect($pending->fresh()->status->value)->toBe('pending');
    expect($dibatalkan->fresh()->status->value)->toBe('cancelled');
    expect($kosong->fresh()->status->value)->toBe('approved');

    expect(AuditLog::where('action', 'reservation.completed')->count())->toBe(2);

    // Dijalankan lagi tidak mengubah apa pun dan tidak menggandakan catatan audit.
    $this->artisan('lab:tandai-selesai')->assertSuccessful();

    expect(AuditLog::where('action', 'reservation.completed')->count())->toBe(2);
});