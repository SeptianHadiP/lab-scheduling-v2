<?php

use App\Enums\Role;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function kalLab(string $kode): Lab
{
    return Lab::firstOrCreate(['code' => $kode], ['name' => "Lab {$kode}"]);
}

function kalUser(Role $role, ?Lab $lab = null, string $name = 'Pengguna Uji'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->role = $role;
    $user->lab_id = $lab?->id;
    $user->save();

    return $user;
}

function kalReservasi(User $pembuat, Lab $lab, string $status, string $tanggal, string $mulai = '08:00', string $selesai = '09:30', ?User $untuk = null): Reservation
{
    $r = Reservation::forceCreate([
        'type' => 'booking',
        'lab_id' => $lab->id,
        'created_by' => $pembuat->id,
        'booked_for_user_id' => $untuk?->id,
        'title' => 'Judul Rahasia',
        'status' => $status,
    ]);

    $r->sessions()->create([
        'sequence_no' => 1,
        'date' => $tanggal,
        'start_time' => $mulai,
        'end_time' => $selesai,
    ]);

    return $r;
}

test('tamu diarahkan ke halaman login', function () {
    $this->get('/calendar')->assertRedirect(route('login'));
});

test('hanya pengajuan yang menahan slot atau sudah selesai yang tampil, tanpa detail privat', function () {
    $lab = kalLab('DS');
    $dosen = kalUser(Role::Dosen, null, 'Dosen A');
    $bulan = now()->addMonth()->format('Y-m');

    kalReservasi($dosen, $lab, 'approved', "{$bulan}-10");
    kalReservasi($dosen, $lab, 'pending', "{$bulan}-11");
    kalReservasi($dosen, $lab, 'completed', "{$bulan}-12");

    foreach (['draft', 'cancelled', 'rejected', 'revision_requested'] as $i => $status) {
        kalReservasi($dosen, $lab, $status, "{$bulan}-".(13 + $i));
    }

    $response = $this->actingAs(kalUser(Role::Dosen, null, 'Dosen Lain'))
        ->get("/calendar?lab={$lab->id}&month={$bulan}");

    $response->assertInertia(fn (Assert $page) => $page
        ->component('calendar/index')
        ->has('events', 3)
        ->where('events.0.status', 'terpakai')
        ->where('events.1.status', 'menunggu')
        ->where('events.2.status', 'terpakai')
        ->where('events.0.mine', false)
        ->missing('events.0.title')
        ->missing('events.0.reservation_id'));

    $response->assertDontSee('Judul Rahasia');
});

test('pengajuan milik sendiri ditandai dan memuat tautan, milik orang lain tidak', function () {
    $lab = kalLab('DS');
    $a = kalUser(Role::Dosen, null, 'Dosen A');
    $b = kalUser(Role::Dosen, null, 'Dosen B');
    $bulan = now()->addMonth()->format('Y-m');

    $r = kalReservasi($a, $lab, 'approved', "{$bulan}-10", '08:00', '09:30', $a);

    $this->actingAs($a)->get("/calendar?lab={$lab->id}&month={$bulan}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.0.mine', true)
            ->where('events.0.reservation_id', $r->id));

    $this->actingAs($b)->get("/calendar?lab={$lab->id}&month={$bulan}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.0.mine', false)
            ->missing('events.0.reservation_id'));
});

test('lab bawaan adalah lab milik pengguna dan lab lain dapat dipilih', function () {
    $ds = kalLab('DS');
    $sd = kalLab('SD');
    $dosen = kalUser(Role::Dosen, null, 'Dosen A');
    $asistenSd = kalUser(Role::AsistenLab, $sd, 'Asisten SD');
    $bulan = now()->addMonth()->format('Y-m');

    kalReservasi($dosen, $ds, 'approved', "{$bulan}-10");
    kalReservasi($dosen, $sd, 'approved', "{$bulan}-11");

    $this->actingAs($asistenSd)->get("/calendar?month={$bulan}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedLabId', $sd->id)
            ->has('events', 1)
            ->where('events.0.date', "{$bulan}-11"));

    $this->actingAs($asistenSd)->get("/calendar?lab={$ds->id}&month={$bulan}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedLabId', $ds->id)
            ->has('events', 1)
            ->where('events.0.date', "{$bulan}-10"));
});

test('bulan yang tidak valid kembali ke bulan ini', function () {
    kalLab('DS');

    $this->actingAs(kalUser(Role::Dosen))->get('/calendar?month=bukan-bulan')
        ->assertInertia(fn (Assert $page) => $page->where('month', now()->format('Y-m')));
});