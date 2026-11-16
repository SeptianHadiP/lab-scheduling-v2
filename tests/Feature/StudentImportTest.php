<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(fn () => Storage::fake('local'));

function dosenImpor(string $nama): User
{
    $user = User::factory()->create(['name' => $nama]);
    $user->role = Role::Dosen;
    $user->save();

    return $user;
}

function kelasImpor(User $dosen, string $kode = '5A'): CourseClass
{
    $course = Course::firstOrCreate(['code' => 'DS201'], ['name' => 'Pembelajaran Mesin']);

    return CourseClass::create([
        'course_id' => $course->id,
        'dosen_id' => $dosen->id,
        'code' => $kode,
        'academic_year' => '2026/2027',
    ]);
}

function berkasMahasiswa(array $lembar): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $pertama = true;

    foreach ($lembar as $judul => $isi) {
        $ws = $pertama ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        $pertama = false;
        $ws->setTitle($judul);

        $ws->setCellValue('A1', 'Nama Dosen');
        $ws->setCellValue('B1', ':');
        $ws->setCellValue('C1', $isi['dosen']);
        $ws->setCellValue('A2', 'Mata Kuliah');
        $ws->setCellValue('B2', ':');
        $ws->setCellValue('C2', $isi['matkul']);
        $ws->setCellValue('A3', 'Kelas');
        $ws->setCellValue('B3', ':');
        $ws->setCellValue('C3', $isi['kelas']);
        $ws->setCellValue('A5', 'No');
        $ws->setCellValue('B5', 'NPM');
        $ws->setCellValue('C5', 'NAMA');

        $baris = 6;
        foreach ($isi['mahasiswa'] as $i => [$npm, $nama]) {
            $ws->setCellValue("A{$baris}", $i + 1);
            if (is_int($npm)) {
                $ws->setCellValue("B{$baris}", $npm);
            } else {
                $ws->setCellValueExplicit("B{$baris}", $npm, DataType::TYPE_STRING);
            }
            $ws->setCellValue("C{$baris}", $nama);
            $baris++;
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'mhs').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'mahasiswa.xlsx', null, null, true);
}

function sesiImpor(UploadedFile $berkas): array
{
    Storage::disk('local')->put('imports/uji.xlsx', file_get_contents($berkas->getPathname()));

    return ['student_import' => ['path' => 'imports/uji.xlsx']];
}

function lembarStandar(string $dosen, array $mahasiswa, string $kelas = '5A'): array
{
    return ['Sheet1' => [
        'dosen' => $dosen,
        'matkul' => 'Pembelajaran Mesin',
        'kelas' => $kelas,
        'mahasiswa' => $mahasiswa,
    ]];
}

test('unggahan menghasilkan pratinjau dan belum menyimpan apa pun', function () {
    $dosen = dosenImpor('Budi Santoso');
    kelasImpor($dosen);

    $berkas = berkasMahasiswa(lembarStandar('Dr. Budi Santoso, M.Kom.', [
        ['202510715001', 'Ani Lestari'],
        ['202510715002', 'Candra Wijaya'],
    ]));

    $this->actingAs($dosen)->post('/students/import', ['file' => $berkas])
        ->assertRedirect('/students/import')
        ->assertSessionHas('student_import.preview.total_importable', 2)
        ->assertSessionHas('student_import.preview.sheets.0.counts.baru', 2);

    expect(Student::count())->toBe(0);
});

test('konfirmasi menyimpan mahasiswa dan tercatat di audit log', function () {
    $dosen = dosenImpor('Budi Santoso');
    $kelas = kelasImpor($dosen);

    $berkas = berkasMahasiswa(lembarStandar('Budi Santoso', [
        ['202510715001', 'Ani Lestari'],
        ['202510715002', 'Candra Wijaya'],
    ]));

    $this->actingAs($dosen)->withSession(sesiImpor($berkas))
        ->post('/students/import/confirm')
        ->assertRedirect('/students/import');

    expect(Student::count())->toBe(2);
    expect($kelas->students()->count())->toBe(2);
    expect(AuditLog::where('action', 'students.imported')->exists())->toBeTrue();
});

test('nama dosen yang tidak cocok menolak sheet', function () {
    $dosen = dosenImpor('Budi Santoso');
    kelasImpor($dosen);

    $berkas = berkasMahasiswa(lembarStandar('Siti Rahma', [['202510715001', 'Ani Lestari']]));

    $this->actingAs($dosen)->post('/students/import', ['file' => $berkas])
        ->assertSessionHas('student_import.preview.sheets.0.importable', false);

    $this->actingAs($dosen)->withSession(sesiImpor($berkas))->post('/students/import/confirm');

    expect(Student::count())->toBe(0);
});

test('kelas milik dosen lain ditolak', function () {
    $dosen = dosenImpor('Budi Santoso');
    $lain = dosenImpor('Siti Rahma');
    kelasImpor($lain);

    $berkas = berkasMahasiswa(lembarStandar('Budi Santoso', [['202510715001', 'Ani Lestari']]));

    $this->actingAs($dosen)->post('/students/import', ['file' => $berkas])
        ->assertSessionHas('student_import.preview.sheets.0.importable', false);

    $this->actingAs($dosen)->withSession(sesiImpor($berkas))->post('/students/import/confirm');

    expect(Student::count())->toBe(0);
});

test('npm tidak valid atau dobel menolak seluruh sheet', function () {
    $dosen = dosenImpor('Budi Santoso');
    kelasImpor($dosen);

    $berkas = berkasMahasiswa(lembarStandar('Budi Santoso', [
        ['12345', 'Pendek'],
        ['202510715001', 'Ani Lestari'],
        ['202510715001', 'Ani Kembar'],
    ]));

    $this->actingAs($dosen)->post('/students/import', ['file' => $berkas])
        ->assertSessionHas('student_import.preview.sheets.0.importable', false)
        ->assertSessionHas('student_import.preview.sheets.0.counts.error', 2);

    $this->actingAs($dosen)->withSession(sesiImpor($berkas))->post('/students/import/confirm');

    expect(Student::count())->toBe(0);
});

test('npm yang tersimpan sebagai angka tetap terbaca 12 digit', function () {
    $dosen = dosenImpor('Budi Santoso');
    kelasImpor($dosen);

    $berkas = berkasMahasiswa(lembarStandar('Budi Santoso', [[202510715002, 'Candra Wijaya']]));

    $this->actingAs($dosen)->post('/students/import', ['file' => $berkas])
        ->assertSessionHas('student_import.preview.sheets.0.counts.baru', 1)
        ->assertSessionHas('student_import.preview.sheets.0.rows.0.npm', '202510715002');
});

test('mahasiswa yang sudah ada ditautkan dan tidak digandakan', function () {
    $dosen = dosenImpor('Budi Santoso');
    $kelasA = kelasImpor($dosen, '5A');
    $kelasB = kelasImpor($dosen, '5B');

    $mhs = Student::create(['npm' => '202510715001', 'name' => 'Ani Lestari']);
    $kelasB->students()->attach($mhs->id);

    $berkas = berkasMahasiswa(lembarStandar('Budi Santoso', [['202510715001', 'Ani Lestari']], '5A'));

    $this->actingAs($dosen)->post('/students/import', ['file' => $berkas])
        ->assertSessionHas('student_import.preview.sheets.0.counts.tautkan', 1);

    $this->actingAs($dosen)->withSession(sesiImpor($berkas))->post('/students/import/confirm');

    expect(Student::count())->toBe(1);
    expect($kelasA->students()->count())->toBe(1);
    expect($kelasB->students()->count())->toBe(1);
});

test('dosen asisten lab dan ketua lab dapat membuka halaman impor', function () {
    $dosen = User::factory()->create(['role' => Role::Dosen]);
    $asisten = User::factory()->create(['role' => Role::AsistenLab]);
    $ketua = User::factory()->create();
    $ketua->role = Role::KetuaLab;
    $ketua->save();

    $this->actingAs($dosen)->get('/students/import')->assertOk();
    $this->actingAs($asisten)->get('/students/import')->assertOk();
    $this->actingAs($ketua)->get('/students/import')->assertOk();

    $dosen->is_active = false;
    $dosen->save();

    $this->actingAs($dosen)->get('/students/import')
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');
});

test('berkas yang bukan excel ditolak', function () {
    $berkas = UploadedFile::fake()->createWithContent('rusak.xlsx', 'ini bukan excel');

    $this->actingAs(dosenImpor('Budi Santoso'))
        ->post('/students/import', ['file' => $berkas])
        ->assertSessionHasErrors('file');
});

test('ketua lab dan asisten lab dapat mengimpor ke kelas dosen yang tertulis di berkas', function () {
    $kasus = [
        [Role::KetuaLab, 'Budi Santoso Ketua', '5K', '202510715101'],
        [Role::AsistenLab, 'Budi Santoso Asisten', '5S', '202510715102'],
    ];

    foreach ($kasus as [$role, $namaDosen, $kodeKelas, $npm]) {
        $dosen = dosenImpor($namaDosen);
        $kelas = kelasImpor($dosen, $kodeKelas);

        $pengunggah = User::factory()->create();
        $pengunggah->role = $role;
        $pengunggah->save();

        $berkas = berkasMahasiswa(lembarStandar(
            "Dr. {$namaDosen}, M.Kom.",
            [[$npm, 'Ani Lestari']],
            $kodeKelas,
        ));

        $this->actingAs($pengunggah)->withSession(sesiImpor($berkas))
            ->post('/students/import/confirm')
            ->assertRedirect('/students/import');

        expect($kelas->students()->count())->toBe(1);
    }
});

test('nama dosen yang terlalu pendek atau tidak ada ditolak untuk ketua lab', function () {
    $dosen = dosenImpor('Budi Santoso');
    kelasImpor($dosen);

    $ketua = User::factory()->create();
    $ketua->role = Role::KetuaLab;
    $ketua->save();

    foreach (['Budi', 'Siapa Itu'] as $nama) {
        $berkas = berkasMahasiswa(lembarStandar($nama, [['202510715001', 'Ani Lestari']]));

        $this->actingAs($ketua)->post('/students/import', ['file' => $berkas])
            ->assertSessionHas('student_import.preview.sheets.0.importable', false);
    }

    expect(Student::count())->toBe(0);
});

test('dosen tetap tidak dapat mengimpor ke kelas dosen lain', function () {
    $budi = dosenImpor('Budi Santoso');
    $siti = dosenImpor('Siti Rahma');
    kelasImpor($siti);

    $berkas = berkasMahasiswa(lembarStandar('Siti Rahma', [['202510715001', 'Ani Lestari']]));

    $this->actingAs($budi)->post('/students/import', ['file' => $berkas])
        ->assertSessionHas('student_import.preview.sheets.0.importable', false);
});