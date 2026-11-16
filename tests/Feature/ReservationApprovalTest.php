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
use App\Services\BlockService;
use Inertia\Testing\AssertableInertia as Assert;

function apvLab(string $kode): Lab
{
    return Lab::firstOrCreate(['code' => $kode], ['name' => "Lab {$kode}"]);
}

function apvUser(Role $role, ?Lab $lab = null, string $name = 'Pengguna Uji'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->role = $role;
    $user->lab_id = $lab?->id;
    $user->save();

    return $user;
}

function apvKelas(User $dosen): CourseClass
{
    $course = Course::firstOrCreate(['code' => 'APV101'], ['name' => 'Uji Persetujuan']);

    $kelas = CourseClass::create([
        'course_id' => $course->id,
        'dosen_id' => $dosen->id,
        'code' => 'A'.$dosen->id,
        'academic_year' => '2026/2027',
    ]);

    $mhs = Student::create(['npm' => sprintf('2025109%05d', $dosen->id), 'name' => "Mahasiswa {$dosen->id}"]);
    $kelas->students()->attach($mhs->id);

    return $kelas;
}

function apvReservasi(User $pembuat, Lab $lab, ReservationStatus $status, ?CourseClass $kelas = null, int $hari = 7): Reservation
{
    $r = Reservation::forceCreate([
        'type' => 'schedule',
        'lab_id' => $lab->id,
        'created_by' => $pembuat->id,
        'booked_for_user_id' => $kelas?->dosen_id,
        'course_class_id' => $kelas?->id,
        'title' => 'Uji persetujuan',
        'status' => $status->value,
        'grading_enabled' => true,
        'grade_owner_id' => $kelas?->dosen_id,
    ]);

    $r->sessions()->create([
        'sequence_no' => 1,
        'date' => now()->addDays($hari)->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '09:30',
        'module_path' => 'modules/uji.pdf',
    ]);

    if ($status->blocksSlots()) {
        app(BlockService::class)->claim($r);
    }

    return $r;
}

test('asisten lab di lab yang sama menyetujui tahap 1 dan slot tetap ditahan', function () {
    $lab = apvLab('DS');
    $dosen = apvUser(Role::Dosen, null, 'Dosen A');
    $asisten = apvUser(Role::AsistenLab, $lab, 'Asisten A');
    $r = apvReservasi($dosen, $lab, ReservationStatus::PendingAssistantReview);

    $this->actingAs($asisten)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    $r->refresh();
    $a = $r->approvals()->first();

    expect($r->status)->toBe(ReservationStatus::PendingFinalApproval);
    expect($a->stage)->toBe('assistant');
    expect($a->decision)->toBe('approved');
    expect($a->decided_by)->toEqual($asisten->id);
    expect($a->is_override)->toBeFalse();
    expect(LabTimeBlock::count())->toBe(6);
});

test('asisten lab lab lain, dosen, dan pembuat tidak dapat memutuskan', function () {
    $ds = apvLab('DS');
    $sd = apvLab('SD');
    $dosen = apvUser(Role::Dosen, null, 'Dosen A');
    $r = apvReservasi($dosen, $ds, ReservationStatus::PendingAssistantReview);

    foreach ([apvUser(Role::AsistenLab, $sd, 'Asisten SD'), apvUser(Role::Dosen, null, 'Dosen B'), $dosen] as $pelaku) {
        $this->actingAs($pelaku)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
            ->assertForbidden();
    }

    expect($r->fresh()->status)->toBe(ReservationStatus::PendingAssistantReview);
    expect($r->approvals()->count())->toBe(0);
});

test('ketua lab di lab yang sama menyetujui final dan tercatat di audit log', function () {
    $lab = apvLab('DS');
    $dosen = apvUser(Role::Dosen, null, 'Dosen A');
    $ketua = apvUser(Role::KetuaLab, $lab, 'Ketua DS');
    $r = apvReservasi($dosen, $lab, ReservationStatus::PendingFinalApproval);

    $this->actingAs($ketua)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    $r->refresh();

    expect($r->status)->toBe(ReservationStatus::Approved);
    expect($r->approvals()->first()->stage)->toBe('final');
    expect($r->approvals()->first()->is_override)->toBeFalse();
    expect(LabTimeBlock::count())->toBe(6);
    expect(AuditLog::where('action', 'reservation.decided')->exists())->toBeTrue();
});

test('revisi dan penolakan wajib disertai catatan', function (string $keputusan) {
    $lab = apvLab('DS');
    $dosen = apvUser(Role::Dosen, null, 'Dosen A');
    $asisten = apvUser(Role::AsistenLab, $lab, 'Asisten A');
    $r = apvReservasi($dosen, $lab, ReservationStatus::PendingAssistantReview);

    $this->actingAs($asisten)->post("/reservations/{$r->id}/decide", ['decision' => $keputusan, 'note' => '   '])
        ->assertSessionHasErrors('note');

    expect($r->fresh()->status)->toBe(ReservationStatus::PendingAssistantReview);
    expect(LabTimeBlock::count())->toBe(6);
})->with(['revision_requested', 'rejected']);

test('minta revisi melepas slot dan pembuat dapat memperbaiki lalu mengirim ulang', function () {
    $lab = apvLab('DS');
    $dosen = apvUser(Role::Dosen, null, 'Dosen R');
    $asisten = apvUser(Role::AsistenLab, $lab, 'Asisten R');
    $r = apvReservasi($dosen, $lab, ReservationStatus::PendingAssistantReview, apvKelas($dosen));

    $this->actingAs($asisten)->post("/reservations/{$r->id}/decide", [
        'decision' => 'revision_requested',
        'note' => 'Modul belum lengkap.',
    ])->assertSessionHasNoErrors();

    expect($r->fresh()->status)->toBe(ReservationStatus::RevisionRequested);
    expect(LabTimeBlock::count())->toBe(0);

    $this->actingAs($dosen)->get("/reservations/{$r->id}/edit")->assertOk();
    $this->actingAs($dosen)->post("/reservations/{$r->id}/send")->assertSessionHasNoErrors();

    $r->refresh();

    expect($r->status)->toBe(ReservationStatus::PendingAssistantReview);
    expect($r->version)->toEqual(2);
    expect(LabTimeBlock::count())->toBe(6);

    $this->actingAs($asisten)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    expect($r->approvals()->pluck('round')->all())->toEqual([1, 2]);
    expect($r->approvals()->pluck('decision')->all())->toBe(['revision_requested', 'approved']);
});

test('penolakan melepas slot dan pengajuan tidak dapat diubah lagi', function () {
    $lab = apvLab('DS');
    $dosen = apvUser(Role::Dosen, null, 'Dosen A');
    $ketua = apvUser(Role::KetuaLab, $lab, 'Ketua DS');
    $r = apvReservasi($dosen, $lab, ReservationStatus::PendingFinalApproval);

    $this->actingAs($ketua)->post("/reservations/{$r->id}/decide", [
        'decision' => 'rejected',
        'note' => 'Bentrok dengan kegiatan fakultas.',
    ])->assertSessionHasNoErrors();

    expect($r->fresh()->status)->toBe(ReservationStatus::Rejected);
    expect(LabTimeBlock::count())->toBe(0);

    $this->actingAs($dosen)->get("/reservations/{$r->id}/edit")->assertForbidden();
    $this->actingAs($dosen)->post("/reservations/{$r->id}/send")->assertForbidden();
});

test('keputusan kedua pada pengajuan yang sama ditolak, keputusan pertama yang berlaku', function () {
    $lab = apvLab('DS');
    $dosen = apvUser(Role::Dosen, null, 'Dosen A');
    $pertama = apvUser(Role::AsistenLab, $lab, 'Asisten Pertama');
    $kedua = apvUser(Role::AsistenLab, $lab, 'Asisten Kedua');
    $r = apvReservasi($dosen, $lab, ReservationStatus::PendingAssistantReview);

    $this->actingAs($pertama)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    $this->actingAs($kedua)->post("/reservations/{$r->id}/decide", [
        'decision' => 'rejected',
        'note' => 'Terlambat.',
    ])->assertForbidden();

    expect($r->fresh()->status)->toBe(ReservationStatus::PendingFinalApproval);
    expect($r->approvals()->count())->toBe(1);
});

test('ketua lab dari lab lain dapat override dengan alasan wajib dan ditandai override', function () {
    $ds = apvLab('DS');
    $sd = apvLab('SD');
    $dosen = apvUser(Role::Dosen, null, 'Dosen A');
    $ketuaSd = apvUser(Role::KetuaLab, $sd, 'Ketua SD');
    $r = apvReservasi($dosen, $ds, ReservationStatus::PendingFinalApproval);

    $this->actingAs($ketuaSd)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertSessionHasErrors('note');

    expect($r->fresh()->status)->toBe(ReservationStatus::PendingFinalApproval);

    $this->actingAs($ketuaSd)->post("/reservations/{$r->id}/decide", [
        'decision' => 'approved',
        'note' => 'Ketua dan asisten DS berhalangan.',
    ])->assertSessionHasNoErrors();

    expect($r->fresh()->status)->toBe(ReservationStatus::Approved);
    expect($r->approvals()->first()->is_override)->toBeTrue();
});

test('pembuat tidak dapat memutuskan pengajuannya sendiri', function () {
    $lab = apvLab('DS');
    $ketua = apvUser(Role::KetuaLab, $lab, 'Ketua DS');
    $r = apvReservasi($ketua, $lab, ReservationStatus::PendingFinalApproval);

    $this->actingAs($ketua)->post("/reservations/{$r->id}/decide", ['decision' => 'approved'])
        ->assertForbidden();

    expect($r->fresh()->status)->toBe(ReservationStatus::PendingFinalApproval);
});

test('antrean hanya berisi pengajuan yang dapat diputuskan pengguna', function () {
    $ds = apvLab('DS');
    $sd = apvLab('SD');
    $dosen = apvUser(Role::Dosen, null, 'Dosen A');
    $asistenDs = apvUser(Role::AsistenLab, $ds, 'Asisten DS');
    $asistenSd = apvUser(Role::AsistenLab, $sd, 'Asisten SD');
    $ketuaDs = apvUser(Role::KetuaLab, $ds, 'Ketua DS');

    apvReservasi($dosen, $ds, ReservationStatus::PendingAssistantReview, null, 7);   // tahap 1 di DS
    apvReservasi($dosen, $sd, ReservationStatus::PendingFinalApproval, null, 8);     // tahap final di SD
    apvReservasi($ketuaDs, $ds, ReservationStatus::PendingFinalApproval, null, 9);   // milik ketua sendiri
    apvReservasi($dosen, $ds, ReservationStatus::Approved, null, 10);                // sudah selesai diproses

    $jumlah = fn (User $u, int $n) => $this->actingAs($u)->get('/approvals')
        ->assertInertia(fn (Assert $page) => $page
            ->component('approvals/index')
            ->has('items', $n));

    $jumlah($asistenDs, 1);
    $jumlah($asistenSd, 0);
    $jumlah($ketuaDs, 2); // dua pengajuan lain, termasuk satu dari lab lain (override)

    $this->actingAs($dosen)->get('/approvals')->assertForbidden();
});