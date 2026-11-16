<?php

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Lab;
use App\Models\LabTimeBlock;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BlockService;
use Inertia\Testing\AssertableInertia as Assert;

function bkLab(string $kode): Lab
{
    return Lab::firstOrCreate(['code' => $kode], ['name' => "Lab {$kode}"]);
}

function bkUser(Role $role, ?Lab $lab = null, string $name = 'Pengguna Uji'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->role = $role;
    $user->lab_id = $lab?->id;
    $user->save();

    return $user;
}

function bkTgl(int $hari): string
{
    return now()->addDays($hari)->format('Y-m-d');
}

function bkSesi(string $tanggal, string $mulai = '08:00', string $selesai = '09:30'): array
{
    return ['date' => $tanggal, 'start_time' => $mulai, 'end_time' => $selesai];
}

function bkPayload(Lab $lab, array $sesi, array $extra = []): array
{
    return array_merge([
        'lab_id' => $lab->id,
        'title' => 'Workshop komunitas',
        'tools_software' => 'VS Code',
        'party' => 'self',
        'grading_enabled' => false,
        'certificate_enabled' => false,
        'sessions' => $sesi,
    ], $extra);
}

function bkBuat(User $pembuat, Lab $lab, array $sesi, array $extra = []): Reservation
{
    test()->actingAs($pembuat)
        ->post('/bookings', bkPayload($lab, $sesi, $extra))
        ->assertSessionHasNoErrors();

    return Reservation::latest('id')->firstOrFail();
}

test('dosen membuat booking untuk dirinya sendiri sebagai draft tanpa menahan slot', function () {
    $lab = bkLab('DS');
    $dosen = bkUser(Role::Dosen, null, 'Dosen A');

    $r = bkBuat($dosen, $lab, [bkSesi(bkTgl(7)), bkSesi(bkTgl(14))], ['grading_enabled' => true]);

    expect($r->type->value)->toBe('booking');
    expect($r->status)->toBe(ReservationStatus::Draft);
    expect($r->created_by)->toEqual($dosen->id);
    expect($r->booked_for_user_id)->toEqual($dosen->id);
    expect($r->grade_owner_id)->toEqual($dosen->id);
    expect($r->course_class_id)->toBeNull();
    expect($r->sessions()->count())->toBe(2);
    expect(LabTimeBlock::count())->toBe(0);
});

test('dosen tidak dapat booking atas nama orang lain atau pihak eksternal', function () {
    $lab = bkLab('DS');
    $dosen = bkUser(Role::Dosen, null, 'Dosen A');
    $lain = bkUser(Role::Dosen, null, 'Dosen B');

    $this->actingAs($dosen)
        ->post('/bookings', bkPayload($lab, [bkSesi(bkTgl(7))], ['party' => 'user', 'booked_for_user_id' => $lain->id]))
        ->assertSessionHasErrors('party');

    $this->actingAs($dosen)
        ->post('/bookings', bkPayload($lab, [bkSesi(bkTgl(7))], [
            'party' => 'external', 'external_name' => 'GDSC', 'external_contact' => 'gdsc@example.com',
        ]))
        ->assertSessionHasErrors('party');

    expect(Reservation::count())->toBe(0);
});

test('asisten lab dapat booking atas nama pengguna terdaftar atau pihak eksternal', function () {
    $lab = bkLab('DS');
    $asisten = bkUser(Role::AsistenLab, $lab, 'Asisten A');
    $dosen = bkUser(Role::Dosen, null, 'Dosen Tamu');

    $untukDosen = bkBuat($asisten, $lab, [bkSesi(bkTgl(7))], [
        'party' => 'user', 'booked_for_user_id' => $dosen->id, 'grading_enabled' => true,
    ]);

    expect($untukDosen->created_by)->toEqual($asisten->id);
    expect($untukDosen->booked_for_user_id)->toEqual($dosen->id);
    expect($untukDosen->grade_owner_id)->toEqual($dosen->id);
    expect($untukDosen->external_name)->toBeNull();

    $eksternal = bkBuat($asisten, $lab, [bkSesi(bkTgl(8))], [
        'title' => 'Workshop GDSC',
        'party' => 'external',
        'external_name' => 'GDSC UBJ',
        'external_contact' => 'gdsc@example.com',
        'grading_enabled' => true,
    ]);

    expect($eksternal->booked_for_user_id)->toBeNull();
    expect($eksternal->external_name)->toBe('GDSC UBJ');
    expect($eksternal->external_contact)->toBe('gdsc@example.com');
    // Pihak eksternal tidak punya akun, jadi pembuat yang menjadi penanggung jawab nilai.
    expect($eksternal->grade_owner_id)->toEqual($asisten->id);
});

test('pihak eksternal wajib memiliki nama dan kontak, pengguna terdaftar wajib dipilih', function () {
    $lab = bkLab('DS');
    $asisten = bkUser(Role::AsistenLab, $lab, 'Asisten A');

    $this->actingAs($asisten)
        ->post('/bookings', bkPayload($lab, [bkSesi(bkTgl(7))], ['party' => 'external']))
        ->assertSessionHasErrors(['external_name', 'external_contact']);

    $this->actingAs($asisten)
        ->post('/bookings', bkPayload($lab, [bkSesi(bkTgl(7))], ['party' => 'user']))
        ->assertSessionHasErrors('booked_for_user_id');

    expect(Reservation::count())->toBe(0);
});

test('validasi pertemuan booking: jam, kelipatan 15 menit, tanggal lampau, dan sertifikat', function () {
    $lab = bkLab('DS');
    $dosen = bkUser(Role::Dosen, null, 'Dosen A');

    $kirim = fn (array $sesi, array $extra = []) => $this->actingAs($dosen)
        ->post('/bookings', bkPayload($lab, [$sesi], $extra));

    $kirim(bkSesi(bkTgl(7), '10:00', '09:00'))->assertSessionHasErrors('sessions.0.end_time');
    $kirim(bkSesi(bkTgl(7), '08:10', '09:30'))->assertSessionHasErrors('sessions.0.start_time');
    $kirim(bkSesi(now()->subDay()->format('Y-m-d')))->assertSessionHasErrors('sessions.0.date');
    $kirim(bkSesi(bkTgl(7)), [
        'grading_enabled' => false, 'certificate_enabled' => true, 'certificate_title' => 'Dasar Python',
    ])->assertSessionHasErrors('certificate_enabled');

    expect(Reservation::count())->toBe(0);
});

test('jalur kirim booking ditentukan peran pembuat', function () {
    $lab = bkLab('DS');

    $kasus = [
        [bkUser(Role::Dosen, null, 'Dosen X'), ReservationStatus::Pending, []],
        [bkUser(Role::AsistenLab, $lab, 'Asisten X'), ReservationStatus::Pending, []],
        [bkUser(Role::KetuaLab, $lab, 'Ketua X'), ReservationStatus::Approved, ['auto']],
    ];

    foreach ($kasus as $i => [$pembuat, $status, $keputusan]) {
        $r = bkBuat($pembuat, $lab, [bkSesi(bkTgl(7 * ($i + 1)))]);

        $this->actingAs($pembuat)->post("/reservations/{$r->id}/send")->assertSessionHasNoErrors();

        $r->refresh();

        expect($r->status)->toBe($status);
        expect($r->approvals()->pluck('decision')->all())->toBe($keputusan);
    }

    expect(LabTimeBlock::count())->toBe(18);
});

test('booking dosen disetujui asisten lab yang sama dalam satu tahap', function () {
    $ds = bkLab('DS');
    $sd = bkLab('SD');
    $dosen = bkUser(Role::Dosen, null, 'Dosen A');
    $asisten = bkUser(Role::AsistenLab, $ds, 'Asisten DS');
    $asistenSd = bkUser(Role::AsistenLab, $sd, 'Asisten SD');

    $r = bkBuat($dosen, $ds, [bkSesi(bkTgl(7))]);
    $this->actingAs($dosen)->post("/reservations/{$r->id}/send");

    $this->actingAs($asistenSd)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertForbidden();

    $this->actingAs($asisten)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    $r->refresh();
    $a = $r->approvals()->first();

    expect($r->status)->toBe(ReservationStatus::Approved);
    expect($a->stage)->toBe('booking');
    expect($a->decision)->toBe('approved');
    expect($a->decided_by)->toEqual($asisten->id);
    expect($a->is_override)->toBeFalse();
    expect(LabTimeBlock::count())->toBe(6);
});

test('booking tidak memiliki revisi, penolakan wajib catatan dan melepas slot', function () {
    $lab = bkLab('DS');
    $dosen = bkUser(Role::Dosen, null, 'Dosen A');
    $asisten = bkUser(Role::AsistenLab, $lab, 'Asisten DS');

    $r = bkBuat($dosen, $lab, [bkSesi(bkTgl(7))]);
    $this->actingAs($dosen)->post("/reservations/{$r->id}/send");

    $this->actingAs($asisten)->post("/reservations/{$r->id}/decide", [
        'decision' => 'revision_requested', 'note' => 'Mohon diperbaiki.',
    ])->assertSessionHasErrors('decision');

    $this->actingAs($asisten)->post("/reservations/{$r->id}/decide", ['decision' => 'rejected'])
        ->assertSessionHasErrors('note');

    expect($r->fresh()->status)->toBe(ReservationStatus::Pending);
    expect(LabTimeBlock::count())->toBe(6);

    $this->actingAs($asisten)->post("/reservations/{$r->id}/decide", [
        'decision' => 'rejected', 'note' => 'Lab dipakai kegiatan fakultas.',
    ])->assertSessionHasNoErrors();

    expect($r->fresh()->status)->toBe(ReservationStatus::Rejected);
    expect(LabTimeBlock::count())->toBe(0);
});

test('booking asisten lab hanya dapat disetujui ketua lab', function () {
    $lab = bkLab('DS');
    $asisten = bkUser(Role::AsistenLab, $lab, 'Asisten A');
    $asisten2 = bkUser(Role::AsistenLab, $lab, 'Asisten B');
    $ketua = bkUser(Role::KetuaLab, $lab, 'Ketua DS');

    $r = bkBuat($asisten, $lab, [bkSesi(bkTgl(7))]);
    $this->actingAs($asisten)->post("/reservations/{$r->id}/send");

    $this->actingAs($asisten)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])->assertForbidden();
    $this->actingAs($asisten2)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])->assertForbidden();

    $this->actingAs($ketua)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    expect($r->fresh()->status)->toBe(ReservationStatus::Approved);
    expect($r->approvals()->first()->is_override)->toBeFalse();
});

test('ketua lab dari lab lain dapat override booking dengan alasan wajib', function () {
    $ds = bkLab('DS');
    $sd = bkLab('SD');
    $dosen = bkUser(Role::Dosen, null, 'Dosen A');
    $ketuaSd = bkUser(Role::KetuaLab, $sd, 'Ketua SD');

    $r = bkBuat($dosen, $ds, [bkSesi(bkTgl(7))]);
    $this->actingAs($dosen)->post("/reservations/{$r->id}/send");

    $this->actingAs($ketuaSd)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertSessionHasErrors('note');

    $this->actingAs($ketuaSd)->post("/reservations/{$r->id}/decide", [
        'decision' => 'approved', 'note' => 'Ketua dan asisten DS berhalangan.',
    ])->assertSessionHasNoErrors();

    expect($r->fresh()->status)->toBe(ReservationStatus::Approved);
    expect($r->approvals()->first()->is_override)->toBeTrue();
});

test('booking dan jadwal praktikum memperebutkan slot yang sama', function () {
    $lab = bkLab('DS');
    $dosen = bkUser(Role::Dosen, null, 'Dosen A');

    $jadwal = Reservation::forceCreate([
        'type' => 'schedule',
        'lab_id' => $lab->id,
        'created_by' => $dosen->id,
        'title' => 'Jadwal praktikum',
        'status' => 'approved',
    ]);
    $jadwal->sessions()->create([
        'sequence_no' => 1, 'date' => bkTgl(7), 'start_time' => '08:00', 'end_time' => '09:30',
    ]);
    app(BlockService::class)->claim($jadwal);

    $r = bkBuat($dosen, $lab, [bkSesi(bkTgl(7), '09:00', '10:30')]);

    $this->actingAs($dosen)->post("/reservations/{$r->id}/send")
        ->assertSessionHas('conflicts', [[
            'sequence_no' => 1,
            'date' => bkTgl(7),
            'start_time' => '09:00',
            'end_time' => '10:30',
        ]]);

    expect($r->fresh()->status)->toBe(ReservationStatus::Draft);
    expect(LabTimeBlock::count())->toBe(6);
});

test('antrean persetujuan memuat booking sesuai wewenang pemroses', function () {
    $ds = bkLab('DS');
    $sd = bkLab('SD');
    $dosen = bkUser(Role::Dosen, null, 'Dosen A');
    $asisten = bkUser(Role::AsistenLab, $ds, 'Asisten DS');
    $ketua = bkUser(Role::KetuaLab, $ds, 'Ketua DS');

    $a = bkBuat($dosen, $ds, [bkSesi(bkTgl(7))]);    // booking dosen di DS
    $b = bkBuat($asisten, $ds, [bkSesi(bkTgl(8))]);  // booking asisten di DS
    $c = bkBuat($dosen, $sd, [bkSesi(bkTgl(9))]);    // booking dosen di SD

    $this->actingAs($dosen)->post("/reservations/{$a->id}/send");
    $this->actingAs($asisten)->post("/reservations/{$b->id}/send");
    $this->actingAs($dosen)->post("/reservations/{$c->id}/send");

    $jumlah = fn (User $u, int $n) => $this->actingAs($u)->get('/approvals')
        ->assertInertia(fn (Assert $page) => $page
            ->component('approvals/index')
            ->has('items', $n));

    $jumlah($asisten, 1); // hanya booking dosen di labnya
    $jumlah($ketua, 3);   // semua, termasuk booking asisten dan lab lain (override)
});

test('halaman detail menandai jenis booking dan rute ubah dipisah dari jadwal praktikum', function () {
    $lab = bkLab('DS');
    $dosen = bkUser(Role::Dosen, null, 'Dosen A');

    $r = bkBuat($dosen, $lab, [bkSesi(bkTgl(7))]);

    $this->actingAs($dosen)->get("/reservations/{$r->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('reservations/show')
            ->where('reservation.type', 'booking')
            ->where('reservation.edit_url', "/bookings/{$r->id}/edit"));

    $this->actingAs($dosen)->get("/reservations/{$r->id}/edit")->assertNotFound();
    $this->actingAs($dosen)->get("/bookings/{$r->id}/edit")->assertOk();

    $this->actingAs($dosen)->post("/reservations/{$r->id}/send")->assertSessionHasNoErrors();

    $this->actingAs($dosen)->get("/bookings/{$r->id}/edit")->assertForbidden();
});