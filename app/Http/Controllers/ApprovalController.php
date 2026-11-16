<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Enums\ReservationType;
use App\Enums\Role;
use App\Models\Reservation;
use App\Models\ReservationApproval;
use App\Policies\ReservationPolicy;
use App\Services\BlockService;
use App\Support\Audit;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Reservation::with(['lab:id,name', 'creator:id,name,role', 'sessions:id,reservation_id,date'])
            ->where('created_by', '!=', $user->id)
            ->orderBy('updated_at')
            ->orderBy('id');

        if ($user->isKetuaLab()) {
            $query->whereIn('status', [
                ReservationStatus::PendingAssistantReview->value,
                ReservationStatus::PendingFinalApproval->value,
                ReservationStatus::Pending->value,
            ]);
        } else {
            $query->where('lab_id', $user->lab_id)
                ->where(function ($q) {
                    $q->where('status', ReservationStatus::PendingAssistantReview->value)
                        ->orWhere(function ($q) {
                            // Booking Asisten Lab hanya dapat diputuskan Ketua Lab.
                            $q->where('status', ReservationStatus::Pending->value)
                                ->whereHas('creator', fn ($c) => $c->where('role', Role::Dosen->value));
                        });
                });
        }

        $items = $query->limit(200)->get()->map(function (Reservation $r) use ($user) {
            $dates = $r->sessions
                ->pluck('date')
                ->map(fn ($d) => substr((string) $d, 0, 10))
                ->sort()
                ->values();

            return [
                'id' => $r->id,
                'title' => $r->title,
                'lab' => $r->lab->name,
                'creator' => $r->creator->name,
                'stage_label' => match ($r->status) {
                    ReservationStatus::PendingAssistantReview => 'Verifikasi Asisten Lab',
                    ReservationStatus::PendingFinalApproval => 'Persetujuan final',
                    default => 'Persetujuan booking',
                },
                'override' => ReservationPolicy::requiresOverride($user, $r),
                'first_date' => $dates->first(),
                'sessions' => $r->sessions->count(),
                'waiting_since' => $r->updated_at->format('d/m/Y H:i'),
            ];
        });

        return Inertia::render('approvals/index', ['items' => $items]);
    }

    public function decide(Request $request, Reservation $reservation, BlockService $blocks): RedirectResponse
    {
        Gate::authorize('decide', $reservation);

        $user = $request->user();
        $isBooking = $reservation->type === ReservationType::Booking;

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'revision_requested', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($isBooking && $data['decision'] === 'revision_requested') {
            throw ValidationException::withMessages([
                'decision' => 'Booking tidak memiliki status revisi. Setujui atau tolak.',
            ]);
        }

        $override = ReservationPolicy::requiresOverride($user, $reservation);
        $note = trim((string) ($data['note'] ?? ''));

        if ($note === '' && ($data['decision'] !== 'approved' || $override)) {
            throw ValidationException::withMessages([
                'note' => $override
                    ? 'Alasan wajib diisi untuk keputusan override.'
                    : 'Catatan wajib diisi untuk revisi atau penolakan.',
            ]);
        }

        $stage = match ($reservation->status) {
            ReservationStatus::PendingAssistantReview => 'assistant',
            ReservationStatus::PendingFinalApproval => 'final',
            default => 'booking',
        };

        $next = match ($data['decision']) {
            'approved' => $stage === 'assistant' ? ReservationStatus::PendingFinalApproval : ReservationStatus::Approved,
            'revision_requested' => ReservationStatus::RevisionRequested,
            'rejected' => ReservationStatus::Rejected,
        };

        $fromStatus = $reservation->status->value;
        $round = (int) $reservation->version;

        try {
            DB::transaction(function () use ($reservation, $fromStatus, $round, $next, $stage, $data, $note, $override, $user, $blocks) {
                // Keputusan pertama menang: hanya berhasil jika status dan putaran belum berubah.
                $updated = Reservation::whereKey($reservation->id)
                    ->where('status', $fromStatus)
                    ->where('version', $round)
                    ->update(['status' => $next->value]);

                if ($updated !== 1) {
                    throw new DomainException('Pengajuan ini sudah diputuskan oleh pihak lain. Muat ulang halaman.');
                }

                ReservationApproval::create([
                    'reservation_id' => $reservation->id,
                    'round' => $round,
                    'stage' => $stage,
                    'decision' => $data['decision'],
                    'decided_by' => $user->id,
                    'note' => $note !== '' ? $note : null,
                    'is_override' => $override,
                ]);

                if (! $next->blocksSlots()) {
                    $blocks->release($reservation);
                }
            });
        } catch (DomainException $e) {
            return back()->with('problems', [$e->getMessage()]);
        }

        Audit::record('reservation.decided', $reservation, [
            'stage' => $stage,
            'decision' => $data['decision'],
            'override' => $override,
            'round' => $round,
        ], reason: $note !== '' ? $note : null);

        return redirect("/reservations/{$reservation->id}");
    }
}