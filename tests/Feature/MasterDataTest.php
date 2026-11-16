<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\TimeSlot;
use App\Models\User;

function akunMaster(Role $role): User
{
    $user = User::factory()->create();
    $user->role = $role;
    $user->save();

    return $user;
}

test('hanya ketua lab yang dapat membuka halaman master data', function () {
    $dosen = akunMaster(Role::Dosen);
    $ketua = akunMaster(Role::KetuaLab);

    foreach (['/courses', '/classes', '/time-slots'] as $url) {
        $this->actingAs($dosen)->get($url)->assertForbidden();
        $this->actingAs($ketua)->get($url)->assertOk();
    }
});

test('asisten lab dapat mengelola mata kuliah dan kelas tetapi tidak slot waktu', function () {
    $asisten = akunMaster(Role::AsistenLab);
    $dosen = akunMaster(Role::Dosen);

    $this->actingAs($asisten)->get('/courses')->assertOk();
    $this->actingAs($asisten)->get('/classes')->assertOk();
    $this->actingAs($asisten)->get('/time-slots')->assertForbidden();

    $this->actingAs($asisten)->post('/courses', ['code' => 'ds301', 'name' => 'Visualisasi Data'])
        ->assertSessionHasNoErrors();

    $course = Course::where('code', 'DS301')->firstOrFail();

    $this->actingAs($asisten)->post('/classes', [
        'course_id' => $course->id,
        'dosen_id' => $dosen->id,
        'code' => '3a',
        'academic_year' => '2026/2027',
    ])->assertSessionHasNoErrors();

    expect(CourseClass::where('code', '3A')->exists())->toBeTrue();
    expect(AuditLog::where('action', 'class.created')->exists())->toBeTrue();
});

test('kode mata kuliah dinormalkan dan harus unik', function () {
    $ketua = akunMaster(Role::KetuaLab);

    $this->actingAs($ketua)->post('/courses', ['code' => ' ds101 ', 'name' => 'Data Mining'])
        ->assertSessionHasNoErrors();

    expect(Course::where('code', 'DS101')->exists())->toBeTrue();

    $this->actingAs($ketua)->post('/courses', ['code' => 'DS101', 'name' => 'Lain'])
        ->assertSessionHasErrors('code');

    expect(AuditLog::where('action', 'course.created')->exists())->toBeTrue();
});

test('kelas hanya boleh diampu dosen aktif', function () {
    $ketua = akunMaster(Role::KetuaLab);
    $course = Course::create(['code' => 'DS101', 'name' => 'Data Mining']);

    $this->actingAs($ketua)->post('/classes', [
        'course_id' => $course->id,
        'dosen_id' => akunMaster(Role::AsistenLab)->id,
        'code' => '5A',
        'academic_year' => '2026/2027',
    ])->assertSessionHasErrors('dosen_id');

    $this->actingAs($ketua)->post('/classes', [
        'course_id' => $course->id,
        'dosen_id' => akunMaster(Role::Dosen)->id,
        'code' => '5a',
        'academic_year' => '2026/2027',
    ])->assertSessionHasNoErrors();

    expect(CourseClass::where('code', '5A')->exists())->toBeTrue();
});

test('format tahun akademik harus benar dan berurutan', function () {
    $ketua = akunMaster(Role::KetuaLab);
    $course = Course::create(['code' => 'DS101', 'name' => 'Data Mining']);
    $dosen = akunMaster(Role::Dosen);

    foreach (['2026', '2026/2028', 'abc'] as $tahun) {
        $this->actingAs($ketua)->post('/classes', [
            'course_id' => $course->id,
            'dosen_id' => $dosen->id,
            'code' => '5A',
            'academic_year' => $tahun,
        ])->assertSessionHasErrors('academic_year');
    }
});

test('kode kelas boleh sama antar mata kuliah tetapi unik dalam satu mata kuliah dan tahun', function () {
    $ketua = akunMaster(Role::KetuaLab);
    $dosen = akunMaster(Role::Dosen);
    $a = Course::create(['code' => 'DS101', 'name' => 'Data Mining']);
    $b = Course::create(['code' => 'SD101', 'name' => 'Pemrograman Web']);

    $payload = fn (Course $c) => [
        'course_id' => $c->id,
        'dosen_id' => $dosen->id,
        'code' => '5A',
        'academic_year' => '2026/2027',
    ];

    $this->actingAs($ketua)->post('/classes', $payload($a))->assertSessionHasNoErrors();
    $this->actingAs($ketua)->post('/classes', $payload($b))->assertSessionHasNoErrors();
    $this->actingAs($ketua)->post('/classes', $payload($a))->assertSessionHasErrors('code');
});

test('jam slot harus kelipatan 15 menit', function () {
    $ketua = akunMaster(Role::KetuaLab);
    $slot = TimeSlot::create(['name' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '09:30', 'sort_order' => 1]);

    $this->actingAs($ketua)->put("/time-slots/{$slot->id}", [
        'name' => 'Sesi 1', 'start_time' => '08:10', 'end_time' => '09:30', 'is_active' => true,
    ])->assertSessionHasErrors('start_time');
});

test('slot aktif tidak boleh tumpang tindih tetapi boleh bersentuhan di batas', function () {
    $ketua = akunMaster(Role::KetuaLab);
    TimeSlot::create(['name' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '09:30', 'sort_order' => 1]);
    $dua = TimeSlot::create(['name' => 'Sesi 2', 'start_time' => '09:45', 'end_time' => '13:15', 'sort_order' => 2]);

    $this->actingAs($ketua)->put("/time-slots/{$dua->id}", [
        'name' => 'Sesi 2', 'start_time' => '09:15', 'end_time' => '13:15', 'is_active' => true,
    ])->assertSessionHasErrors('start_time');

    $this->actingAs($ketua)->put("/time-slots/{$dua->id}", [
        'name' => 'Sesi 2', 'start_time' => '09:30', 'end_time' => '13:15', 'is_active' => true,
    ])->assertSessionHasNoErrors();
});