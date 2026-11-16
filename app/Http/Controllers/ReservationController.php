<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Enums\ReservationType;
use App\Exceptions\ReservationConflictException;
use App\Models\CourseClass;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\ReservationApproval;
use App\Models\ReservationSession;
use App\Models\TimeSlot;
use App\Models\User;
use App\Policies\ReservationPolicy;
use App\Services\BlockService;
use App\Support\Audit;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Reservation::with(['lab:id,name', 'creator:id,name', 'sessions:id,reservation_id,sequence_no,date'])
            ->latest('id')
            // Draft hanya terlihat oleh pembuatnya.
            ->where(fn ($q) => $q
                ->where('status', '!=', ReservationStatus::Draft->value)
                ->orWhere('created_by', $user->id));

        if (! $user->isKetuaLab()) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('booked_for_user_id', $user->id)
                    ->orWhere('grade_owner_id', $user->id);

                if ($user->isAsistenLab() && $user->lab_id) {
                    $q->orWhere('lab_id', $user->lab_id);
                }
            });
        }

        $reservations = $query->limit(200)->get()->map(function (Reservation $r) {
            $dates = $r->sessions
                ->pluck('date')
                ->map(fn ($d) => substr((string) $d, 0, 10))
                ->sort()
                ->values();

            return [
                'id' => $r->id,
                'title' => $r->title,
                'type_label' => $r->type->label(),
                'lab' => $r->lab->name,
                'creator' => $r->creator->name,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'first_date' => $dates->first(),
                'last_date' => $dates->last(),
                'sessions' => $r->sessions->count(),
            ];
        });

        return Inertia::render('reservations/index', ['reservations' => $reservations]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('reservations/form', $this->formProps($request->user(), null));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$data, $class] = $this->validated($request, $user);

        $reservation = $this->persist(null, $data, $class, $user);

        Audit::record('reservation.created', $reservation, ['title' => $reservation->title]);

        return redirect("/reservations/{$reservation->id}");
    }

    public function show(Request $request, Reservation $reservation): Response
    {
        Gate::authorize('view', $reservation);

        $user = $request->user();
        $reservation->load(['lab', 'creator', 'bookedFor', 'courseClass.course', 'sessions', 'approvals.decider']);

        $canDecide = Gate::allows('decide', $reservation);
        $isBooking = $reservation->type === ReservationType::Booking;

        return Inertia::render('reservations/show', [
            'reservation' => [
                'id' => $reservation->id,
                'title' => $reservation->title,
                'type' => $reservation->type->value,
                'type_label' => $reservation->type->label(),
                'edit_url' => $isBooking
                    ? "/bookings/{$reservation->id}/edit"
                    : "/reservations/{$reservation->id}/edit",
                'status' => $reservation->status->value,
                'status_label' => $reservation->status->label(),
                'lab' => $reservation->lab->name,
                'creator' => $reservation->creator->name,
                'booked_for' => $reservation->bookedFor?->name,
                'external_name' => $reservation->external_name,
                'external_contact' => $reservation->external_contact,
                'class' => $reservation->courseClass
                    ? "{$reservation->courseClass->course->code} - {$reservation->courseClass->course->name} / {$reservation->courseClass->code} ({$reservation->courseClass->academic_year})"
                    : null,
                'tools_software' => $reservation->tools_software,
                'grading_enabled' => $reservation->grading_enabled,
                'certificate_enabled' => $reservation->certificate_enabled,
                'certificate_title' => $reservation->certificate_title,
                'sessions' => $reservation->sessions->map(fn (ReservationSession $s) => [
                    'id' => $s->id,
                    'sequence_no' => $s->sequence_no,
                    'date' => substr((string) $s->date, 0, 10),
                    'start_time' => substr((string) $s->start_time, 0, 5),
                    'end_time' => substr((string) $s->end_time, 0, 5),
                    'has_module' => filled($s->module_path),
                ])->values(),
                'approvals' => $reservation->approvals->map(fn (ReservationApproval $a) => [
                    'id' => $a->id,
                    'round' => $a->round,
                    'stage_label' => $a->stageLabel(),
                    'decision' => $a->decision,
                    'decision_label' => $a->decisionLabel(),
                    'override' => (bool) $a->is_override,
                    'by' => $a->decider?->name,
                    'note' => $a->note,
                    'at' => $a->created_at->format('d/m/Y H:i'),
                ])->values(),
            ],
            'can' => [
                'edit' => Gate::allows('update', $reservation),
                'send' => Gate::allows('send', $reservation),
                'cancel' => Gate::allows('cancel', $reservation),
                'decide' => $canDecide,
            ],
            'decision' => $canDecide ? [
                'stage_label' => match ($reservation->status) {
                    ReservationStatus::PendingAssistantReview => 'Verifikasi Asisten Lab',
                    ReservationStatus::PendingFinalApproval => 'Persetujuan final Ketua Lab',
                    default => 'Persetujuan booking',
                },
                'override' => ReservationPolicy::requiresOverride($user, $reservation),
            ] : null,
            'revision_note' => $reservation->status === ReservationStatus::RevisionRequested
                ? $reservation->approvals->where('decision', 'revision_requested')->last()?->note
                : null,
            'conflicts' => session('conflicts'),
            'problems' => session('problems'),
        ]);
    }

    public function edit(Request $request, Reservation $reservation): Response
    {
        abort_unless($reservation->type === ReservationType::Schedule, 404);
        Gate::authorize('update', $reservation);

        return Inertia::render('reservations/form', $this->formProps($request->user(), $reservation));
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === ReservationType::Schedule, 404);
        Gate::authorize('update', $reservation);

        $user = $request->user();
        [$data, $class] = $this->validated($request, $user);

        $this->persist($reservation, $data, $class, $user);

        Audit::record('reservation.updated', $reservation, ['title' => $reservation->title]);

        return redirect("/reservations/{$reservation->id}");
    }

    public function send(Request $request, Reservation $reservation, BlockService $blocks): RedirectResponse
    {
        Gate::authorize('send', $reservation);

        $user = $request->user();
        $isBooking = $reservation->type === ReservationType::Booking;
        $reservation->load(['sessions', 'courseClass.students']);

        $problems = [];

        if ($reservation->sessions->isEmpty()) {
            $problems[] = 'Pengajuan belum memiliki pertemuan.';
        }

        foreach ($reservation->sessions as $session) {
            if (! $isBooking && blank($session->module_path)) {
                $problems[] = "Pertemuan {$session->sequence_no}: modul praktikum belum diunggah.";
            }

            if (substr((string) $session->date, 0, 10) < now()->format('Y-m-d')) {
                $problems[] = "Pertemuan {$session->sequence_no}: tanggal sudah lewat. Ubah tanggalnya.";
            }
        }

        if (! $isBooking && (! $reservation->courseClass || $reservation->courseClass->students->isEmpty())) {
            $problems[] = 'Kelas belum memiliki mahasiswa. Impor mahasiswa terlebih dahulu.';
        }

        if ($problems !== []) {
            return back()->with('problems', $problems);
        }

        $next = match (true) {
            $user->isKetuaLab() => ReservationStatus::Approved,
            $isBooking => ReservationStatus::Pending,
            $user->isAsistenLab() => ReservationStatus::PendingFinalApproval,
            default => ReservationStatus::PendingAssistantReview,
        };

        $studentIds = $isBooking ? [] : $reservation->courseClass->students->pluck('id')->all();
        $fromStatus = $reservation->status->value;
        $version = (int) $reservation->version;
        // Kirim ulang setelah revisi memulai putaran persetujuan baru.
        $resubmission = $reservation->status === ReservationStatus::RevisionRequested;

        try {
            DB::transaction(function () use ($reservation, $user, $next, $studentIds, $fromStatus, $version, $resubmission, $isBooking, $blocks) {
                // Update bersyarat: hanya berhasil jika status dan putaran belum berubah (anti klik ganda).
                $updated = Reservation::whereKey($reservation->id)
                    ->where('status', $fromStatus)
                    ->where('version', $version)
                    ->update([
                        'status' => $next->value,
                        'version' => $resubmission ? $version + 1 : $version,
                    ]);

                if ($updated !== 1) {
                    throw new DomainException('Pengajuan ini sudah dikirim.');
                }

                $reservation->refresh();

                if (! $isBooking) {
                    $reservation->participants()->sync($studentIds);
                }

                $this->recordSkippedStages($reservation, $user);

                $blocks->claim($reservation);
            }, 3);
        } catch (ReservationConflictException $e) {
            return back()->with('conflicts', $e->conflicts);
        } catch (DomainException $e) {
            return back()->with('problems', [$e->getMessage()]);
        }

        Audit::record('reservation.sent', $reservation, [
            'status' => $next->value,
            'round' => $reservation->version,
            'sessions' => $reservation->sessions()->count(),
        ]);

        return redirect("/reservations/{$reservation->id}");
    }

    public function cancel(Request $request, Reservation $reservation, BlockService $blocks): RedirectResponse
    {
        Gate::authorize('cancel', $reservation);

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $cancelable = array_map(fn (ReservationStatus $s) => $s->value, ReservationPolicy::CANCELABLE);

        try {
            DB::transaction(function () use ($reservation, $cancelable, $blocks) {
                $updated = Reservation::whereKey($reservation->id)
                    ->whereIn('status', $cancelable)
                    ->update(['status' => ReservationStatus::Cancelled->value]);

                if ($updated !== 1) {
                    throw new DomainException('Status pengajuan sudah berubah. Muat ulang halaman.');
                }

                $blocks->release($reservation);
            });
        } catch (DomainException $e) {
            return back()->with('problems', [$e->getMessage()]);
        }

        Audit::record('reservation.cancelled', $reservation, reason: $request->input('reason'));

        return redirect("/reservations/{$reservation->id}");
    }

    public function module(Reservation $reservation, ReservationSession $reservationSession)
    {
        Gate::authorize('view', $reservation);

        abort_unless(
            (int) $reservationSession->reservation_id === (int) $reservation->id
                && filled($reservationSession->module_path)
                && Storage::disk('local')->exists($reservationSession->module_path),
            404,
        );

        $ext = pathinfo($reservationSession->module_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $reservationSession->module_path,
            "modul-pertemuan-{$reservationSession->sequence_no}.{$ext}",
        );
    }

    /**
     * Tahap yang dilewati karena peran pembuat dicatat sebagai "dilewati" atau "otomatis",
     * bukan sebagai persetujuan seseorang (BR-APP-04).
     */
    private function recordSkippedStages(Reservation $reservation, User $user): void
    {
        $round = (int) $reservation->version;

        if ($reservation->type === ReservationType::Booking) {
            if ($user->isKetuaLab()) {
                ReservationApproval::create([
                    'reservation_id' => $reservation->id,
                    'round' => $round,
                    'stage' => 'booking',
                    'decision' => 'auto',
                    'note' => 'Disetujui otomatis karena dibuat oleh Ketua Lab.',
                ]);
            }

            return;
        }

        if ($user->isAsistenLab() || $user->isKetuaLab()) {
            ReservationApproval::create([
                'reservation_id' => $reservation->id,
                'round' => $round,
                'stage' => 'assistant',
                'decision' => 'skipped',
                'note' => $user->isKetuaLab() ? 'Dibuat oleh Ketua Lab.' : 'Dibuat oleh Asisten Lab.',
            ]);
        }

        if ($user->isKetuaLab()) {
            ReservationApproval::create([
                'reservation_id' => $reservation->id,
                'round' => $round,
                'stage' => 'final',
                'decision' => 'auto',
                'note' => 'Disetujui otomatis karena dibuat oleh Ketua Lab.',
            ]);
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: CourseClass}
     */
    private function validated(Request $request, User $user): array
    {
        $data = $request->validate([
            'lab_id' => ['required', 'integer', Rule::exists('labs', 'id')->where('is_active', true)],
            'course_class_id' => ['required', 'integer', Rule::exists('course_classes', 'id')->where('is_active', true)],
            'tools_software' => ['nullable', 'string', 'max:2000'],
            'grading_enabled' => ['nullable', 'boolean'],
            'certificate_enabled' => ['nullable', 'boolean'],
            'certificate_title' => ['nullable', 'string', 'max:255'],
            'sessions' => ['required', 'array', 'min:1', 'max:16'],
            'sessions.*.id' => ['nullable', 'integer'],
            'sessions.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'sessions.*.start_time' => ['required', 'date_format:H:i'],
            'sessions.*.end_time' => ['required', 'date_format:H:i'],
            'sessions.*.module' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:10240'],
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

        $class = CourseClass::with(['course', 'dosen'])->findOrFail($data['course_class_id']);

        if ($user->isDosen() && (int) $class->dosen_id !== (int) $user->id) {
            $errors['course_class_id'] = 'Kelas ini bukan kelas Anda.';
        } elseif (! $class->dosen->is_active) {
            $errors['course_class_id'] = 'Dosen pengampu kelas ini tidak aktif.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$data, $class];
    }

    private function persist(?Reservation $reservation, array $data, CourseClass $class, User $user): Reservation
    {
        return DB::transaction(function () use ($reservation, $data, $class, $user) {
            $r = $reservation ?? new Reservation;

            $r->fill([
                'lab_id' => $data['lab_id'],
                'course_class_id' => $class->id,
                'booked_for_user_id' => $class->dosen_id,
                'title' => "Praktikum {$class->course->name} (Kelas {$class->code}, {$class->academic_year})",
                'tools_software' => $data['tools_software'] ?? null,
                'grading_enabled' => $data['grading_enabled'],
                'certificate_enabled' => $data['certificate_enabled'],
                'certificate_title' => $data['certificate_title'],
            ]);

            $r->type = ReservationType::Schedule;
            // Penanggung jawab nilai: pihak yang diwakili (dosen pemilik kelas), bukan pembuat (FR-049).
            $r->grade_owner_id = $data['grading_enabled'] ? $class->dosen_id : null;

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

            if (isset($row['module']) && $row['module'] instanceof UploadedFile) {
                if ($session->module_path) {
                    Storage::disk('local')->delete($session->module_path);
                }

                $session->module_path = $row['module']->store("modules/{$r->id}", 'local');
            }

            $session->save();
            $keptIds[] = $session->id;
        }

        foreach ($existing as $id => $old) {
            if (! in_array($id, $keptIds, true)) {
                if ($old->module_path) {
                    Storage::disk('local')->delete($old->module_path);
                }

                $old->delete();
            }
        }
    }

    private function formProps(User $user, ?Reservation $reservation): array
    {
        $classes = CourseClass::with(['course:id,code,name', 'dosen:id,name'])
            ->where('is_active', true)
            ->when($user->isDosen(), fn ($q) => $q->where('dosen_id', $user->id))
            ->orderByDesc('academic_year')
            ->orderBy('code')
            ->get()
            ->map(fn (CourseClass $c) => [
                'id' => $c->id,
                'label' => "{$c->course->code} - {$c->course->name} / {$c->code} ({$c->academic_year})"
                    .($user->isDosen() ? '' : " · {$c->dosen->name}"),
            ]);

        return [
            'reservation' => $reservation ? [
                'id' => $reservation->id,
                'lab_id' => $reservation->lab_id,
                'course_class_id' => $reservation->course_class_id,
                'tools_software' => $reservation->tools_software ?? '',
                'grading_enabled' => $reservation->grading_enabled,
                'certificate_enabled' => $reservation->certificate_enabled,
                'certificate_title' => $reservation->certificate_title ?? '',
                'sessions' => $reservation->sessions()->get()->map(fn (ReservationSession $s) => [
                    'id' => $s->id,
                    'date' => substr((string) $s->date, 0, 10),
                    'start_time' => substr((string) $s->start_time, 0, 5),
                    'end_time' => substr((string) $s->end_time, 0, 5),
                    'has_module' => filled($s->module_path),
                ])->values(),
            ] : null,
            'classes' => $classes,
            'labs' => Lab::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'slots' => TimeSlot::where('is_active', true)->orderBy('sort_order')->get()->map(fn (TimeSlot $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
            ]),
            'defaultLabId' => $user->lab_id,
        ];
    }
}
