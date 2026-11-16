<?php

namespace App\Console\Commands;

use App\Models\Lab;
use App\Models\LabTimeBlock;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class UjiKonkurensi extends Command
{
    protected $signature = 'lab:uji-konkurensi {--proses=8 : Jumlah proses paralel (2-20)}';

    protected $description = 'Membuktikan tidak ada double-booking saat banyak proses berebut slot yang sama (MySQL/MariaDB, non-produksi)';

    private const KODE_LAB = 'UJIKON';

    private const EMAIL = 'uji-konkurensi@example.test';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Tidak boleh dijalankan di produksi.');

            return self::FAILURE;
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('Perintah ini hanya bermakna di MySQL/MariaDB.');

            return self::FAILURE;
        }

        $jumlah = max(2, min(20, (int) $this->option('proses')));

        $this->bersihkan();

        try {
            $lab = Lab::create(['name' => 'Lab Uji Konkurensi', 'code' => self::KODE_LAB]);

            $pembuat = new User(['name' => 'Uji Konkurensi', 'email' => self::EMAIL, 'password' => Str::random(40)]);
            $pembuat->save();

            $ids = [];
            for ($i = 1; $i <= $jumlah; $i++) {
                $r = Reservation::forceCreate([
                    'type' => 'booking',
                    'lab_id' => $lab->id,
                    'created_by' => $pembuat->id,
                    'title' => "Uji konkurensi {$i}",
                    'status' => 'pending',
                ]);
                $r->sessions()->create([
                    'sequence_no' => 1,
                    'date' => '2030-01-07',
                    'start_time' => '08:00',
                    'end_time' => '09:30',
                ]);
                $ids[] = $r->id;
            }

            $this->info("Menjalankan {$jumlah} proses paralel untuk slot yang sama...");

            $mulai = microtime(true) + 3.0;

            $hasil = Process::pool(function (Pool $pool) use ($ids, $mulai) {
                foreach ($ids as $id) {
                    $pool->command([PHP_BINARY, base_path('artisan'), 'lab:klaim-uji', (string) $id, '--start-at='.$mulai]);
                }
            })->start()->wait();

            $berhasil = 0;
            $bentrok = 0;
            $lain = 0;

            foreach ($hasil->collect() as $indeks => $proses) {
                match ($proses->exitCode()) {
                    0 => $berhasil++,
                    2 => $bentrok++,
                    default => $lain++,
                };

                if (! in_array($proses->exitCode(), [0, 2], true)) {
                    $detail = trim($proses->errorOutput() ?: $proses->output());
                    $this->line('Proses '.($indeks + 1).' gagal: '.($detail !== '' ? $detail : 'tanpa output'));
                }
            }

            $blok = LabTimeBlock::where('lab_id', $lab->id)->count();

            $this->table(['Hasil', 'Jumlah'], [
                ['Berhasil mengklaim', $berhasil],
                ['Ditolak karena bentrok', $bentrok],
                ['Error lain', $lain],
                ['Blok tersimpan (harus 6)', $blok],
            ]);

            $lulus = $berhasil === 1 && $bentrok === $jumlah - 1 && $lain === 0 && $blok === 6;

            $lulus
                ? $this->info('LULUS: tepat satu proses menang, tidak ada double-booking.')
                : $this->error('GAGAL: periksa hasil di atas.');

            return $lulus ? self::SUCCESS : self::FAILURE;
        } finally {
            $this->bersihkan();
        }
    }

    private function bersihkan(): void
    {
        $lab = Lab::where('code', self::KODE_LAB)->first();

        if ($lab) {
            LabTimeBlock::where('lab_id', $lab->id)->delete();
            Reservation::where('lab_id', $lab->id)->delete();
        }

        User::where('email', self::EMAIL)->delete();
        $lab?->delete();
    }
}