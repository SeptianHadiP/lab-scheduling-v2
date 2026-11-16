<?php

namespace App\Http\Controllers;

use App\Enums\ReservationType;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\ReservationSession;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('bookings/form', $this->formProps($request->user(), null));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, $user);

        $reservation = $this->persist(null, $data, $user);

        Audit::record('booking.created', $reservation, ['title' => $reservation->title]);

        return redirect("/reservations/{$reservation->id}");
    }

    public function edit(Request $request, Reservation $reservation): Response
    {
        $this->ensureBooking($reservation);
        Gate::authorize('update', $reservation);

        return Inertia::render('bookings/form', $this->formProps($request->user(), $reservation));
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->ensureBooking($reservation);
        Gate::authorize('update', $reservation);

        $user = $request->user();
        $data = $this->validated($request, $user);

        $this->persist($reservation, $data, $user);

        Audit::record('booking.updated', $reservation, ['title' => $reservation->title]);

        return redirect("/reservations/{$reservation->id}");
    }

    private function ensureBooking(Reservation $reservation): void
    {
        abort_unless($reservation->type === ReservationType::Booking, 404);
    }

    private function validated(Request $request, User $user): array
    {
        $data = $request->validate([
            'lab_id' => ['required', 'integer', Rule::exists('labs', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'tools_software' => ['nullable', 'string', 'max:2000'],
            'party' => ['required', Rule::in(['self', 'user', 'external'])],
            'booked_for_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'external_name' => ['nullable', 'string', 'max:150'],
            'external_contact' => ['nullable', 'string', 'max:150'],
            'grading_enabled' => ['nullable', 'boolean'],
            'certificate_enabled' => ['nullable', 'boolean'],
            'certificate_title' => ['nullable', 'string', 'max:255'],
            'sessions' => ['required', 'array', 'min:1', 'max:16'],
            'sessions.*.id' => ['nullable', 'integer'],
            'sessions.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'sessions.*.start_time' => ['required', 'date_format:H:i'],
            'sessions.*.end_time' => ['required', 'date_format:H:i'],
        ]);

        $errors = [];

        foreach (array_values($data['sessions']) as $i => $s) {
            if ($s['end_time'] <= $s['start_time']) {
                $errors["sessions.{$i}.end_time"] = 'Jam selesai harus setelah jam mulai.';
            }

            foreach (['start_time', 'end_time'] as $field) {
                if ((int) substr($s[$field], 3, 2) % 15 !== 0) {
                    $errors["sessions.{$i}.{$field}"] = 'Jam harus kelipatan 15 menit.';
                }
            }
        }

        $data['grading_enabled'] = $request->boolean('grading_enabled');
        $data['certificate_enabled'] = $request->boolean('certificate_enabled');

        if ($data['certificate_enabled'] && ! $data['grading_enabled']) {
            $errors['certificate_enabled'] = 'Sertifikat hanya dapat dinyalakan jika Nilai menyala.';
        }

        if ($data['certificate_enabled'] && blank($data['certificate_title'] ?? null)) {
            $errors['certificate_title'] = 'Judul materi sertifikat wajib diisi.';
        }

        if (! $data['certificate_enabled']) {
            $data['certificate_title'] = null;
        }

        // Dosen hanya boleh booking untuk dirinya sendiri (FR-050).
        if ($user->isDosen() && $data['party'] !== 'self') {
            $errors['party'] = 'Dosen hanya dapat melakukan booking untuk dirinya sendiri.';
        }

        $bookedFor = null;
        $externalName = null;
        $externalContact = null;

        switch ($data['party']) {
            case 'self':
                $bookedFor = $user->id;
                break;

            case 'user':
                if (blank($data['booked_for_user_id'] ?? null)) {
                    $errors['booked_for_user_id'] = 'Pilih pengguna yang diwakili.';
                } else {
                    $bookedFor = (int) $data['booked_for_user_id'];
                }
                break;

            case 'external':
                $externalName = trim((string) ($data['external_name'] ?? ''));
                $externalContact = trim((string) ($data['external_contact'] ?? ''));

                if ($externalName === '') {
                    $errors['external_name'] = 'Nama pihak eksternal wajib diisi.';
                }

                if ($externalContact === '') {
                    $errors['external_contact'] = 'Kontak pihak eksternal wajib diisi.';
                }
                break;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $data['booked_for_user_id'] = $bookedFor;
        $data['external_name'] = $externalName;
        $data['external_contact'] = $externalContact;

        return $data;
    }

    private function persist(?Reservation $reservation, array $data, User $user): Reservation
    {
        return DB::transaction(function () use ($reservation, $data, $user) {
            $r = $reservation ?? new Reservation;

            $r->fill([
                'lab_id' => $data['lab_id'],
                'course_class_id' => null,
                'booked_for_user_id' => $data['booked_for_user_id'],
                'external_name' => $data['external_name'],
                'external_contact' => $data['external_contact'],
                'title' => $data['title'],
                'tools_software' => $data['tools_software'] ?? null,
                'grading_enabled' => $data['grading_enabled'],
                'certificate_enabled' => $data['certificate_enabled'],
                'certificate_title' => $data['certificate_title'],
            ]);

            $r->type = ReservationType::Booking;
            // Penanggung jawab nilai (FR-049): pihak terdaftar yang diwakili, atau pembuat jika pihak eksternal.
            $r->grade_owner_id = $data['grading_enabled'] ? ($data['booked_for_user_id'] ?? $user->id) : null;

            if (! $r->exists) {
                $r->created_by = $user->id;
            }

            $r->save();

            $this->syncSessions($r, $data['sessions']);

            return $r;
        });
    }

    private function syncSessions(Reservation $r, array $incoming): void
    {
        // Geser nomor urut lama dulu supaya tidak menabrak unique (reservation_id, sequence_no).
        $r->sessions()->update(['sequence_no' => DB::raw('sequence_no + 1000')]);

        $existing = $r->sessions()->get()->keyBy('id');
        $keptIds = [];

        foreach (array_values($incoming) as $i => $row) {
            $session = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            $session ??= $r->sessions()->make();

            $session->fill([
                'sequence_no' => $i + 1,
                'date' => $row['date'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
            ]);

            $session->save();
            $keptIds[] = $session->id;
        }

        foreach ($existing as $id => $old) {
            if (! in_array($id, $keptIds, true)) {
                $old->delete();
            }
        }
    }

    private function formProps(User $user, ?Reservation $reservation): array
    {
        $canBookForOthers = ! $user->isDosen();

        $party = 'self';
        if ($reservation) {
            if (filled($reservation->external_name)) {
                $party = 'external';
            } elseif ($reservation->booked_for_user_id && (int) $reservation->booked_for_user_id !== (int) $reservation->created_by) {
                $party = 'user';
            }
        }

        return [
            'reservation' => $reservation ? [
                'id' => $reservation->id,
                'lab_id' => $reservation->lab_id,
                'title' => $reservation->title,
                'tools_software' => $reservation->tools_software ?? '',
                'party' => $party,
                'booked_for_user_id' => $party === 'user' ? $reservation->booked_for_user_id : null,
                'external_name' => $reservation->external_name ?? '',
                'external_contact' => $reservation->external_contact ?? '',
                'grading_enabled' => $reservation->grading_enabled,
                'certificate_enabled' => $reservation->certificate_enabled,
                'certificate_title' => $reservation->certificate_title ?? '',
                'sessions' => $reservation->sessions()->get()->map(fn (ReservationSession $s) => [
                    'id' => $s->id,
                    'date' => substr((string) $s->date, 0, 10),
                    'start_time' => substr((string) $s->start_time, 0, 5),
                    'end_time' => substr((string) $s->end_time, 0, 5),
                ])->values(),
            ] : null,
            'labs' => Lab::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'slots' => TimeSlot::where('is_active', true)->orderBy('sort_order')->get()->map(fn (TimeSlot $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
            ]),
            'defaultLabId' => $user->lab_id,
            'canBookForOthers' => $canBookForOthers,
            // Data minimum untuk memilih pengguna terdaftar (FR-052): hanya id, nama, dan peran.
            'users' => $canBookForOthers
                ? User::where('is_active', true)
                    ->where('id', '!=', $user->id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'role'])
                    ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'role_label' => $u->role->label()])
                    ->values()
                : [],
        ];
    }
}