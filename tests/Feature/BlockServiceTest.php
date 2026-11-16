<?php

use App\Exceptions\ReservationConflictException;
use App\Models\Lab;
use App\Models\LabTimeBlock;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BlockService;
use Illuminate\Database\UniqueConstraintViolationException;

function labBlok(string $kode = 'BLK'): Lab
{
    return Lab::create(['name' => "Lab {$kode}", 'code' => $kode]);
}

/**
 * @param  list<array{0:string,1:string,2:string}>  $sesi  [tanggal, mulai, selesai]
 */
function reservasiBlok(Lab $lab, array $sesi): Reservation
{
    $pembuat = User::factory()->create();

    $reservasi = Reservation::forceCreate([
        'type' => 'booking',
        'lab_id' => $lab->id,
        'created_by' => $pembuat->id,
        'title' => 'Uji',
        'status' => 'pending',
    ]);

    foreach ($sesi as $i => [$tanggal, $mulai, $selesai]) {
        $reservasi->sessions()->create([
            'sequence_no' => $i + 1,
            'date' => $tanggal,
            'start_time' => $mulai,
            'end_time' => $selesai,
        ]);
    }

    return $reservasi;
}

test('satu sesi 90 menit dipecah menjadi 6 blok dengan akhir eksklusif', function () {
    $blok = app(BlockService::class)->blocksFor('2026-11-02', '08:00', '09:30');

    expect(array_map(fn ($b) => $b->format('H:i'), $blok))
        ->toBe(['08:00', '08:15', '08:30', '08:45', '09:00', '09:15']);
});

test('jam yang bukan kelipatan 15 menit atau terbalik ditolak', function () {
    $svc = app(BlockService::class);

    expect(fn () => $svc->blocksFor('2026-11-02', '08:10', '09:30'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->blocksFor('2026-11-02', '09:30', '08:00'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->blocksFor('2026-11-02', '08:00', '08:00'))->toThrow(InvalidArgumentException::class);
});

test('klaim menyimpan seluruh blok dari seluruh pertemuan', function () {
    $lab = labBlok();
    $r = reservasiBlok($lab, [['2026-11-02', '08:00', '09:30'], ['2026-11-09', '09:45', '13:15']]);

    app(BlockService::class)->claim($r);

    expect(LabTimeBlock::where('lab_id', $lab->id)->count())->toBe(6 + 14);
});

test('pertemuan yang bersentuhan di batas tidak bentrok', function () {
    $lab = labBlok();
    $svc = app(BlockService::class);

    $svc->claim(reservasiBlok($lab, [['2026-11-02', '08:00', '09:30']]));
    $svc->claim(reservasiBlok($lab, [['2026-11-02', '09:30', '11:00']]));

    expect(LabTimeBlock::where('lab_id', $lab->id)->count())->toBe(6 + 6);
});

test('tumpang tindih ditolak dan menampilkan hanya tanggal dan jam yang bentrok', function () {
    $lab = labBlok();
    $svc = app(BlockService::class);

    $svc->claim(reservasiBlok($lab, [['2026-11-03', '08:00', '09:30']]));

    $b = reservasiBlok($lab, [['2026-11-02', '08:00', '09:30'], ['2026-11-03', '09:00', '10:00']]);

    try {
        $svc->claim($b);
        $this->fail('Seharusnya bentrok.');
    } catch (ReservationConflictException $e) {
        expect($e->conflicts)->toBe([
            ['sequence_no' => 2, 'date' => '2026-11-03', 'start_time' => '09:00', 'end_time' => '10:00'],
        ]);
    }

    // Semua atau tidak sama sekali: pertemuan pertama yang bebas pun tidak tersimpan.
    expect(LabTimeBlock::where('lab_id', $lab->id)->count())->toBe(6);
});

test('dua pertemuan dalam satu pengajuan yang saling tumpang tindih ditolak', function () {
    $lab = labBlok();

    $r = reservasiBlok($lab, [['2026-11-02', '08:00', '09:30'], ['2026-11-02', '09:00', '10:00']]);

    try {
        app(BlockService::class)->claim($r);
        $this->fail('Seharusnya bentrok.');
    } catch (ReservationConflictException $e) {
        expect($e->conflicts)->toHaveCount(2);
    }

    expect(LabTimeBlock::count())->toBe(0);
});

test('lab berbeda tidak saling bentrok', function () {
    $svc = app(BlockService::class);
    $a = labBlok('AAA');
    $b = labBlok('BBB');

    $svc->claim(reservasiBlok($a, [['2026-11-02', '08:00', '09:30']]));
    $svc->claim(reservasiBlok($b, [['2026-11-02', '08:00', '09:30']]));

    expect(LabTimeBlock::count())->toBe(12);
});

test('melepas blok membuat slot dapat dipakai pengajuan lain', function () {
    $lab = labBlok();
    $svc = app(BlockService::class);

    $a = reservasiBlok($lab, [['2026-11-02', '08:00', '09:30']]);
    $b = reservasiBlok($lab, [['2026-11-02', '09:00', '10:00']]);

    $svc->claim($a);
    expect(fn () => $svc->claim($b))->toThrow(ReservationConflictException::class);

    $svc->release($a);
    $svc->claim($b);

    expect(LabTimeBlock::where('lab_id', $lab->id)->count())->toBe(4);
});

test('constraint unik di database menolak blok ganda', function () {
    $lab = labBlok();
    $r = reservasiBlok($lab, [['2026-11-02', '08:00', '09:30']]);

    $baris = [
        'lab_id' => $lab->id,
        'reservation_session_id' => $r->sessions()->first()->id,
        'block_start' => '2026-11-02 08:00:00',
    ];

    LabTimeBlock::insert($baris);

    expect(fn () => LabTimeBlock::insert($baris))->toThrow(UniqueConstraintViolationException::class);
});

test('pengajuan tanpa pertemuan tidak dapat diklaim', function () {
    $lab = labBlok();
    $r = reservasiBlok($lab, []);

    expect(fn () => app(BlockService::class)->claim($r))->toThrow(InvalidArgumentException::class);
});