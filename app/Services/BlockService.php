<?php

namespace App\Services;

use App\Exceptions\ReservationConflictException;
use App\Models\LabTimeBlock;
use App\Models\Reservation;
use App\Models\ReservationSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BlockService
{
    public const BLOCK_MINUTES = 15;

    /**
     * Memecah satu pertemuan menjadi blok 15 menit.
     * Awal inklusif, akhir eksklusif: 08:00-09:30 menghasilkan 08:00 sampai 09:15,
     * sehingga pertemuan berikutnya yang mulai 09:30 tidak bentrok (BR-TS-02).
     *
     * @return list<CarbonImmutable>
     */
    public function blocksFor(string $date, string $start, string $end): array
    {
        $from = $this->parse($date, $start);
        $to = $this->parse($date, $end);

        if ($to <= $from) {
            throw new InvalidArgumentException('Jam selesai harus setelah jam mulai.');
        }

        $blocks = [];
        for ($t = $from; $t < $to; $t = $t->addMinutes(self::BLOCK_MINUTES)) {
            $blocks[] = $t;
        }

        return $blocks;
    }

    /**
     * Mengklaim seluruh blok dari seluruh pertemuan: semua atau tidak sama sekali (BR-BOOK-17).
     *
     * @throws ReservationConflictException
     */
    public function claim(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $sessions = $reservation->sessions()->get();

            if ($sessions->isEmpty()) {
                throw new InvalidArgumentException('Pengajuan belum memiliki pertemuan.');
            }

            $rows = [];
            $startsBySession = [];
            $owners = [];

            foreach ($sessions as $session) {
                $starts = $this->startsFor($session);
                $startsBySession[$session->id] = $starts;

                foreach ($starts as $start) {
                    $owners[$start][] = $session->id;
                    $rows[] = [
                        'lab_id' => $reservation->lab_id,
                        'reservation_session_id' => $session->id,
                        'block_start' => $start,
                    ];
                }
            }

            $starts = array_keys($owners);

            $conflicts = $this->detectConflicts(
                $sessions, $startsBySession, $owners, $this->takenStarts($reservation->lab_id, $starts),
            );

            if ($conflicts !== []) {
                throw new ReservationConflictException($conflicts);
            }

            // Urutan tetap supaya dua proses tidak saling mengunci (deadlock).
            usort($rows, fn (array $a, array $b) => strcmp($a['block_start'], $b['block_start']));

            try {
                // Satu perintah INSERT: atomik. Constraint unik adalah pengaman terakhir
                // kalau ada proses lain yang mengambil blok di antara pengecekan dan penyimpanan.
                LabTimeBlock::insert($rows);
            } catch (UniqueConstraintViolationException $e) {
                $conflicts = $this->detectConflicts(
                    $sessions, $startsBySession, $owners, $this->takenStarts($reservation->lab_id, $starts, true),
                );

                throw $conflicts !== [] ? new ReservationConflictException($conflicts) : $e;
            }
        }, 3); // diulang otomatis kalau terjadi deadlock
    }

    /**
     * Melepas semua blok milik pengajuan (ditolak, dibatalkan, minta revisi, selesai).
     */
    public function release(Reservation $reservation): int
    {
        $ids = $reservation->sessions()->pluck('id');

        return LabTimeBlock::whereIn('reservation_session_id', $ids)->delete();
    }

    /**
     * @return list<string> awal tiap blok, format Y-m-d H:i:s
     */
    private function startsFor(ReservationSession $session): array
    {
        return array_map(
            fn (CarbonImmutable $t) => $t->format('Y-m-d H:i:s'),
            $this->blocksFor(substr((string) $session->date, 0, 10), (string) $session->start_time, (string) $session->end_time),
        );
    }

    /**
     * @param  list<string>  $starts
     * @return list<string>
     */
    private function takenStarts(int $labId, array $starts, bool $lockForUpdate = false): array
    {
        $taken = [];

        foreach (array_chunk($starts, 500) as $chunk) {
            $query = LabTimeBlock::where('lab_id', $labId)->whereIn('block_start', $chunk);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $taken = array_merge($taken, $query->pluck('block_start')->all());
        }

        return array_map(fn ($v) => (string) $v, $taken);
    }

    /**
     * @param  Collection<int, ReservationSession>  $sessions
     * @param  array<int, list<string>>  $startsBySession
     * @param  array<string, list<int>>  $owners
     * @param  list<string>  $taken
     * @return list<array{sequence_no:int,date:string,start_time:string,end_time:string}>
     */
    private function detectConflicts(Collection $sessions, array $startsBySession, array $owners, array $taken): array
    {
        $taken = array_flip($taken);
        $conflicts = [];

        foreach ($sessions as $session) {
            foreach ($startsBySession[$session->id] as $start) {
                // Bentrok dengan pengajuan lain, atau dengan pertemuan lain di pengajuan yang sama.
                if (isset($taken[$start]) || count($owners[$start]) > 1) {
                    $conflicts[] = [
                        'sequence_no' => (int) $session->sequence_no,
                        'date' => substr((string) $session->date, 0, 10),
                        'start_time' => substr((string) $session->start_time, 0, 5),
                        'end_time' => substr((string) $session->end_time, 0, 5),
                    ];

                    break;
                }
            }
        }

        return $conflicts;
    }

    private function parse(string $date, string $time): CarbonImmutable
    {
        $text = $date.' '.substr($time, 0, 5);
        $moment = CarbonImmutable::createFromFormat('Y-m-d H:i', $text);

        if ($moment === false || $moment->format('Y-m-d H:i') !== $text) {
            throw new InvalidArgumentException('Format tanggal atau jam tidak valid.');
        }

        if ((int) $moment->format('i') % self::BLOCK_MINUTES !== 0) {
            throw new InvalidArgumentException('Jam harus kelipatan 15 menit.');
        }

        return $moment;
    }
}