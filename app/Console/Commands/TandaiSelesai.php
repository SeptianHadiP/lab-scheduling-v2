<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\Audit;
use Illuminate\Console\Command;

class TandaiSelesai extends Command
{
    protected $signature = 'lab:tandai-selesai';

    protected $description = 'Menandai pengajuan disetujui sebagai selesai setelah semua pertemuannya lewat';

    public function handle(): int
    {
        $hariIni = now()->format('Y-m-d');
        $jamSekarang = now()->format('H:i:s');
        $jumlah = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Approved->value)
            ->whereHas('sessions')
            // Tidak ada pertemuan yang masih akan datang.
            ->whereDoesntHave('sessions', fn ($q) => $q->where(
                fn ($q) => $q->where('date', '>', $hariIni)
                    ->orWhere(fn ($q) => $q->where('date', $hariIni)->where('end_time', '>', $jamSekarang)),
            ))
            ->chunkById(100, function ($pengajuan) use (&$jumlah) {
                foreach ($pengajuan as $r) {
                    // Update bersyarat: aman bila dibatalkan di saat yang sama.
                    $updated = Reservation::whereKey($r->id)
                        ->where('status', ReservationStatus::Approved->value)
                        ->update(['status' => ReservationStatus::Completed->value]);

                    if ($updated === 1) {
                        $jumlah++;
                        Audit::record('reservation.completed', $r, ['by' => 'sistem']);
                    }
                }
            });

        $this->info("{$jumlah} pengajuan ditandai selesai.");

        return self::SUCCESS;
    }
}