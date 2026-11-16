<?php

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\Lab;
use App\Models\LabTimeBlock;
use App\Models\Reservation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake('local'));

function rsvLab(string $kode = 'DS'): Lab
{
    return Lab::firstOrCreate(['code' => $kode], ['name' => "Lab {$kode}"]);
}

function rsvUser(Role $role, ?Lab $lab = null, string $name = 'Pengguna Uji'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->role = $role;
    $user->lab_id = $lab?->id;
    $user->save();

    return $user;
}

function rsvKelas(User $dosen, int $mahasiswa = 2): CourseClass
{
    static $urut = 0;
    $urut++;

    $course = Course::firstOrCreate(['code' => 'DS201'], ['name' => 'Pembelajaran Mesin']);

    $kelas = CourseClass::create([
        'course_id' => $course->id,
        'dosen_id' => $dosen->id,
        'code' => '5A'.$urut,
        'academic_year' => '2026/2027',
    ]);

    for ($i = 0; $i < $mahasiswa; $i++) {
        $mhs = Student::create(['npm' => sprintf('2025107%05d', $urut * 100 + $i), 'name' => "Mahasiswa {$urut}-{$i}"]);
        $kelas->students()->attach($mhs->id);
    }

    return $kelas;
}

function rsvTgl(int $hari): string
{
    return now()->addDays($hari)->format('Y-m-d');
}

function rsvSesi(string $tanggal, string $mulai = '08:00', string $selesai = '09:30', bool $denganModul = true): array
{
    return array_filter([
        'date' => $tanggal,
        'start_time' => $mulai,
        'end_time' => $selesai,
        'module' => $denganModul ? UploadedFile::fake()->create('modul.pdf', 100, 'application/pdf') : null,
    ], fn ($v) => $v !== null);
}

function rsvPayload(Lab $lab, CourseClass $kelas, array $sesi, array $extra = []): array
{
    return array_merge([
        'lab_id' => $lab->id,
        'course_class_id' => $kelas->id,
        'tools_software' => 'Python, Jupyter',
        'grading_enabled' => true,
        'certificate_enabled' => false,
        'sessions' => $sesi,
    ], $extra);
}

function rsvBuat(User $pembuat, Lab $lab, CourseClass $kelas, array $sesi): Reservation
{
    test()->actingAs($pembuat)
        ->post('/reservations', rsvPayload($lab, $kelas, $sesi))
        ->assertSessionHasNoErrors();

    return Reservation::latest('id')->firstOrFail();
}

test('dosen membuat draft untuk kelasnya sendiri tanpa menahan slot', function () {
    $lab = rsvLab();
    $dosen = rsvUser(Role::Dosen);
    $kelas = rsvKelas($dosen);

    $r = rsvBuat($dosen, $lab, $kelas, [rsvSesi(rsvTgl(7)), rsvSesi(rsvTgl(14))]);

    expect($r->status)->toBe(ReservationStatus::Draft);
    expect($r->created_by)->toEqual($dosen->id);
    expect($r->booked_for_user_id)->toEqual($dosen->id);
    expect($r->grade_owner_id)->toEqual($dosen->id);
    expect($r->title)->toContain('Pembelajaran Mesin');
    expect($r->sessions()->count())->toBe(2);
    expect(LabTimeBlock::count())->toBe(0);

    expect(Storage::disk('local')->exists($r->sessions()->first()->module_path))->toBeTrue();
});

test('dosen tidak dapat membuat pengajuan untuk kelas dosen lain', function () {
    $lab = rsvLab();
    $a = rsvUser(Role::Dosen, null, 'Dosen A');
    $b = rsvUser(Role::Dosen, null, 'Dosen B');
    $kelasA = rsvKelas($a);

    $this->actingAs($b)
        ->post('/reservations', rsvPayload($lab, $kelasA, [rsvSesi(rsvTgl(7))]))
        ->assertSessionHasErrors('course_class_id');

    expect(Reservation::count())->toBe(0);
});

test('asisten lab dapat membuat pengajuan untuk kelas dosen, penanggung jawab nilai tetap dosen', function () {
    $lab = rsvLab();
    $dosen = rsvUser(Role::Dosen, null, 'Dosen Pemilik');
    $asisten = rsvUser(Role::AsistenLab, $lab, 'Asisten');

    $r = rsvBuat($asisten, $lab, rsvKelas($dosen), [rsvSesi(rsvTgl(7))]);

    expect($r->created_by)->toEqual($asisten->id);
    expect($r->booked_for_user_id)->toEqual($dosen->id);
    expect($r->grade_owner_id)->toEqual($dosen->id);
});

test('validasi pertemuan: jam, kelipatan 15 menit, dan tanggal lampau', function () {
    $lab = rsvLab();
    $dosen = rsvUser(Role::Dosen);
    $kelas = rsvKelas($dosen);

    $kirim = fn (array $sesi) => $this->actingAs($dosen)
        ->post('/reservations', rsvPayload($lab, $kelas, [$sesi]));

    $kirim(rsvSesi(rsvTgl(7), '10:00', '09:00'))->assertSessionHasErrors('sessions.0.end_time');
    $kirim(rsvSesi(rsvTgl(7), '08:10', '09:30'))->assertSessionHasErrors('sessions.0.start_time');
    $kirim(rsvSesi(now()->subDay()->format('Y-m-d')))->assertSessionHasErrors('sessions.0.date');

    expect(Reservation::count())->toBe(0);
});

test('sertifikat hanya dapat dinyalakan jika nilai menyala dan judul diisi', function () {
    $lab = rsvLab();
    $dosen = rsvUser(Role::Dosen);
    $kelas = rsvKelas($dosen);

    $this->actingAs($dosen)
        ->post('/reservations', rsvPayload($lab, $kelas, [rsvSesi(rsvTgl(7))], [
            'grading_enabled' => false,
            'certificate_enabled' => true,
            'certificate_title' => 'Dasar Python',
        ]))
        ->assertSessionHasErrors('certificate_enabled');

    $this->actingAs($dosen)
        ->post('/reservations', rsvPayload($lab, $kelas, [rsvSesi(rsvTgl(7))], [
            'grading_enabled' => true,
            'certificate_enabled' => true,
            'certificate_title' => '',
        ]))
        ->assertSessionHasErrors('certificate_title');
});

test('dosen mengirim pengajuan: menunggu asisten, slot ditahan, peserta disalin', function () {
    $lab = rsvLab();
    $dosen = rsvUser(Role::Dosen, null, 'Budi');
    $kelas = rsvKelas($dosen, 3);

    $r = rsvBuat($dosen, $lab, $kelas, [rsvSesi(rsvTgl(7)), rsvSesi(rsvTgl(14))]);

    $this->actingAs($dosen)->post("/reservations/{$r->id}/send")->assertSessionHasNoErrors();

    $r->refresh();

    expect($r->status)->toBe(ReservationStatus::PendingAssistantReview);
    expect(LabTimeBlock::count())->toBe(12);
    expect($r->participants()->count())->toBe(3);
    expect(AuditLog::where('action', 'reservation.sent')->exists())->toBeTrue();
});

test('jalur persetujuan ditentukan peran pembuat dan tahap yang dilewati dicatat', function () {
    $lab = rsvLab();
    $dosen = rsvUser(Role::Dosen, null, 'Dosen X');
    $asisten = rsvUser(Role::AsistenLab, $lab, 'Asisten X');
    $ketua = rsvUser(Role::KetuaLab, $lab, 'Ketua X');

    $kasus = [
        [$dosen, ReservationStatus::PendingAssistantReview, []],
        [$asisten, ReservationStatus::PendingFinalApproval, ['skipped']],
        [$ketua, ReservationStatus::Approved, ['skipped', 'auto']],
    ];

    foreach ($kasus as $i => [$pembuat, $status, $keputusan]) {
        $r = rsvBuat($pembuat, $lab, rsvKelas($dosen), [rsvSesi(rsvTgl(7 * ($i + 1)))]);

        $this->actingAs($pembuat)->post("/reservations/{$r->id}/send")->assertSessionHasNoErrors();

        $r->refresh();

        expect($r->status)->toBe($status);
        expect($r->approvals()->pluck('decision')->all())->toBe($keputusan);
        expect($r->grade_owner_id)->toEqual($dosen->id);
    }
});

test('pengajuan tanpa modul atau tanpa mahasiswa tidak dapat dikirim', function () {
    $lab = rsvLab();
    $dosen = rsvUser(Role::Dosen);

    $r = rsvBuat($dosen, $lab, rsvKelas($dosen, 0), [rsvSesi(rsvTgl(7), '08:00', '09:30', false)]);

    $this->actingAs($dosen)->post("/reservations/{$r->id}/send")->assertSessionHas('problems');

    expect($r->fresh()->status)->toBe(ReservationStatus::Draft);
    expect(LabTimeBlock::count())->toBe(0);
});

test('pengajuan yang bentrok tidak terkirim dan menampilkan tanggal yang bentrok', function () {
    $lab = rsvLab();
    $a = rsvUser(Role::Dosen, null, 'Dosen A');
    $b = rsvUser(Role::Dosen, null, 'Dosen B');

    $ra = rsvBuat($a, $lab, rsvKelas($a), [rsvSesi(rsvTgl(7), '08:00', '09:30')]);
    $rb = rsvBuat($b, $lab, rsvKelas($b), [rsvSesi(rsvTgl(7), '09:00', '10:30')]);

    $this->actingAs($a)->post("/reservations/{$ra->id}/send")->assertSessionHasNoErrors();
    expect(LabTimeBlock::count())->toBe(6);

    $this->actingAs($b)->post("/reservations/{$rb->id}/send")
        ->assertSessionHas('conflicts', [[
            'sequence_no' => 1,
            'date' => rsvTgl(7),
            'start_time' => '09:00',
            'end_time' => '10:30',
        ]]);

    expect($rb->fresh()->status)->toBe(ReservationStatus::Draft);
    expect(LabTimeBlock::count())->toBe(6);
});

test('membatalkan pengajuan melepas slot sehingga dapat dipakai pengajuan lain', function () {
    $lab = rsvLab();
    $a = rsvUser(Role::Dosen, null, 'Dosen A');
    $b = rsvUser(Role::Dosen, null, 'Dosen B');

    $ra = rsvBuat($a, $lab, rsvKelas($a), [rsvSesi(rsvTgl(7), '08:00', '09:30')]);
    $rb = rsvBuat($b, $lab, rsvKelas($b), [rsvSesi(rsvTgl(7), '09:00', '10:30')]);

    $this->actingAs($a)->post("/reservations/{$ra->id}/send");
    $this->actingAs($b)->post("/reservations/{$rb->id}/send")->assertSessionHas('conflicts');

    $this->actingAs($a)->post("/reservations/{$ra->id}/cancel")->assertSessionHasNoErrors();

    expect($ra->fresh()->status)->toBe(ReservationStatus::Cancelled);
    expect(LabTimeBlock::count())->toBe(0);

    $this->actingAs($b)->post("/reservations/{$rb->id}/send")->assertSessionHasNoErrors();

    expect($rb->fresh()->status)->toBe(ReservationStatus::PendingAssistantReview);
    expect(LabTimeBlock::count())->toBe(6);
});

test('hak melihat, mengubah, mengirim, dan membatalkan dibatasi', function () {
    $ds = rsvLab('DS');
    $sd = rsvLab('SD');
    $a = rsvUser(Role::Dosen, null, 'Dosen A');
    $b = rsvUser(Role::Dosen, null, 'Dosen B');

    $r = rsvBuat($a, $ds, rsvKelas($a), [rsvSesi(rsvTgl(7))]);

    $this->actingAs($b)->get("/reservations/{$r->id}")->assertForbidden();
    $this->actingAs($b)->get("/reservations/{$r->id}/edit")->assertForbidden();
    $this->actingAs($b)->post("/reservations/{$r->id}/send")->assertForbidden();
    $this->actingAs($b)->post("/reservations/{$r->id}/cancel")->assertForbidden();

    // Draft tidak terlihat oleh asisten maupun ketua, bahkan di lab yang sama.
    $this->actingAs(rsvUser(Role::AsistenLab, $ds, 'Asisten DS'))->get("/reservations/{$r->id}")->assertForbidden();
    $this->actingAs(rsvUser(Role::KetuaLab, $ds, 'Ketua DS'))->get("/reservations/{$r->id}")->assertForbidden();

    $this->actingAs($a)->post("/reservations/{$r->id}/send")->assertSessionHasNoErrors();

    $this->actingAs(rsvUser(Role::AsistenLab, $ds, 'Asisten DS 2'))->get("/reservations/{$r->id}")->assertOk();
    $this->actingAs(rsvUser(Role::AsistenLab, $sd, 'Asisten SD'))->get("/reservations/{$r->id}")->assertForbidden();
    $this->actingAs(rsvUser(Role::KetuaLab, $sd, 'Ketua SD'))->get("/reservations/{$r->id}")->assertOk();
    $this->actingAs(rsvUser(Role::KetuaLab, $sd, 'Ketua SD 2'))->post("/reservations/{$r->id}/cancel")->assertForbidden();

    $this->actingAs(rsvUser(Role::KetuaLab, $ds, 'Ketua DS 2'))
        ->post("/reservations/{$r->id}/cancel")
        ->assertSessionHasNoErrors();

    expect($r->fresh()->status)->toBe(ReservationStatus::Cancelled);
});

test('modul hanya dapat diunduh oleh pihak yang berwenang', function () {
    $lab = rsvLab();
    $a = rsvUser(Role::Dosen, null, 'Dosen A');
    $b = rsvUser(Role::Dosen, null, 'Dosen B');

    $r = rsvBuat($a, $lab, rsvKelas($a), [rsvSesi(rsvTgl(7))]);
    $s = $r->sessions()->first();

    $this->actingAs($a)->get("/reservations/{$r->id}/sessions/{$s->id}/module")->assertOk();
    $this->actingAs($b)->get("/reservations/{$r->id}/sessions/{$s->id}/module")->assertForbidden();
});

test('mengubah draft mempertahankan modul lama dan membuang pertemuan yang dihapus', function () {
    $lab = rsvLab();
    $dosen = rsvUser(Role::Dosen);
    $kelas = rsvKelas($dosen);

    $r = rsvBuat($dosen, $lab, $kelas, [rsvSesi(rsvTgl(7)), rsvSesi(rsvTgl(14))]);
    $sesi = $r->sessions()->get();
    $pathPertama = $sesi[0]->module_path;
    $pathKedua = $sesi[1]->module_path;

    $this->actingAs($dosen)
        ->put("/reservations/{$r->id}", rsvPayload($lab, $kelas, [[
            'id' => $sesi[0]->id,
            'date' => rsvTgl(8),
            'start_time' => '10:00',
            'end_time' => '11:30',
        ]]))
        ->assertSessionHasNoErrors();

    $r->refresh();

    expect($r->sessions()->count())->toBe(1);
    expect($r->sessions()->first()->module_path)->toBe($pathPertama);
    expect($r->sessions()->first()->sequence_no)->toEqual(1);
    expect(Storage::disk('local')->exists($pathPertama))->toBeTrue();
    expect(Storage::disk('local')->exists($pathKedua))->toBeFalse();

    // Setelah dikirim, pengajuan tidak dapat diubah lagi.
    $this->actingAs($dosen)->post("/reservations/{$r->id}/send")->assertSessionHasNoErrors();
    $this->actingAs($dosen)->get("/reservations/{$r->id}/edit")->assertForbidden();
});

test('daftar pengajuan hanya menampilkan yang berhak dilihat', function () {
    $lab = rsvLab();
    $a = rsvUser(Role::Dosen, null, 'Dosen A');
    $b = rsvUser(Role::Dosen, null, 'Dosen B');
    $ketua = rsvUser(Role::KetuaLab, $lab, 'Ketua');

    rsvBuat($a, $lab, rsvKelas($a), [rsvSesi(rsvTgl(7))]); // draft A

    $rb = rsvBuat($b, $lab, rsvKelas($b), [rsvSesi(rsvTgl(14))]);
    $this->actingAs($b)->post("/reservations/{$rb->id}/send");

    $hitung = fn (User $u, int $n) => $this->actingAs($u)->get('/reservations')
        ->assertInertia(fn (Assert $page) => $page
            ->component('reservations/index')
            ->has('reservations', $n));

    $hitung($a, 1);      // draft miliknya sendiri
    $hitung($b, 1);      // pengajuannya yang sudah terkirim
    $hitung($ketua, 1);  // pengajuan B; draft A tidak terlihat
});