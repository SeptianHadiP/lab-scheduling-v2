<?php

namespace App\Console\Commands;

use App\Exceptions\ReservationConflictException;
use App\Models\Reservation;
use App\Services\BlockService;
use Illuminate\Console\Command;

class KlaimUji extends Command
{
    protected $signature = 'lab:klaim-uji {reservation : ID pengajuan} {--start-at= : Waktu mulai (unix microtime)}';

    protected $description = 'Dipakai internal oleh lab:uji-konkurensi';

    public function handle(BlockService $blocks): int
    {
        $reservation = Reservation::findOrFail($this->argument('reservation'));

        // Semua proses menunggu sampai detik yang sama supaya benar-benar berebut.
        $mulai = (float) $this->option('start-at');
        if ($mulai > 0 && ($tunggu = $mulai - microtime(true)) > 0) {
            usleep((int) ($tunggu * 1_000_000));
        }

        try {
            $blocks->claim($reservation);
        } catch (ReservationConflictException) {
            $this->line('BENTROK');

            return 2;
        }

        $this->line('BERHASIL');

        return self::SUCCESS;
    }
}