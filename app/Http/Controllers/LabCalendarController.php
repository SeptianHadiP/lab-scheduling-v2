<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\ReservationSession;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabCalendarController extends Controller
{
    /** Pengajuan yang menahan slot dan masih menunggu keputusan. */
    private const PENDING = ['pending_assistant_review', 'pending_final_approval', 'pending'];

    /** Pengajuan yang pasti terpakai (disetujui atau sudah selesai). */
    private const USED = ['approved', 'completed'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $labs = Lab::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $selected = $labs->firstWhere('id', $request->integer('lab'))
            ?? $labs->firstWhere('id', $user->lab_id)
            ?? $labs->first();

        $month = $this->month($request->string('month')->toString());

        $first = CarbonImmutable::createFromFormat('!Y-m-d', "{$month}-01");
        $gridStart = $first->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $first->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $events = collect();

        if ($selected) {
            $events = ReservationSession::query()
                ->join('reservations', 'reservations.id', '=', 'reservation_sessions.reservation_id')
                ->where('reservations.lab_id', $selected->id)
                ->whereIn('reservations.status', [...self::PENDING, ...self::USED])
                ->whereBetween('reservation_sessions.date', [$gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d')])
                ->orderBy('reservation_sessions.date')
                ->orderBy('reservation_sessions.start_time')
                ->get([
                    'reservation_sessions.date as date',
                    'reservation_sessions.start_time as start_time',
                    'reservation_sessions.end_time as end_time',
                    'reservations.status as status',
                    'reservations.id as reservation_id',
                    'reservations.created_by as created_by',
                    'reservations.booked_for_user_id as booked_for_user_id',
                ])
                ->map(function ($row) use ($user) {
                    $mine = (int) $row->created_by === (int) $user->id
                        || (int) $row->booked_for_user_id === (int) $user->id;

                    // Hanya tanggal, jam, dan status. Tidak ada judul atau identitas pemohon.
                    $event = [
                        'date' => substr((string) $row->date, 0, 10),
                        'start_time' => substr((string) $row->start_time, 0, 5),
                        'end_time' => substr((string) $row->end_time, 0, 5),
                        'status' => in_array($row->status, self::USED, true) ? 'terpakai' : 'menunggu',
                        'mine' => $mine,
                    ];

                    if ($mine) {
                        $event['reservation_id'] = (int) $row->reservation_id;
                    }

                    return $event;
                })
                ->values();
        }

        return Inertia::render('calendar/index', [
            'labs' => $labs,
            'selectedLabId' => $selected?->id,
            'month' => $month,
            'gridStart' => $gridStart->format('Y-m-d'),
            'gridEnd' => $gridEnd->format('Y-m-d'),
            'today' => now()->format('Y-m-d'),
            'events' => $events,
            'slots' => TimeSlot::where('is_active', true)->orderBy('sort_order')->get()->map(fn (TimeSlot $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
            ]),
        ]);
    }

    private function month(string $value): string
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) === 1 && (int) $m[1] >= 2020 && (int) $m[1] <= 2100) {
            return $value;
        }

        return now()->format('Y-m');
    }
}