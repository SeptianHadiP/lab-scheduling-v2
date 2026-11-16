<?php

namespace App\Http\Controllers;

use App\Models\TimeSlot;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TimeSlotController extends Controller
{
    public function index(): Response
    {
        $slots = TimeSlot::orderBy('sort_order')->get()->map(fn (TimeSlot $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'is_active' => $s->is_active,
        ]);

        return Inertia::render('time-slots/index', ['slots' => $slots]);
    }

    public function update(Request $request, TimeSlot $timeSlot): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', Rule::unique('time_slots', 'name')->ignore($timeSlot->id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['required', 'boolean'],
        ]);

        foreach (['start_time', 'end_time'] as $field) {
            if ((int) substr($data[$field], 3, 2) % 15 !== 0) {
                throw ValidationException::withMessages([$field => 'Jam harus kelipatan 15 menit.']);
            }
        }

        // Interval tertutup di awal, terbuka di akhir: 09:30 tidak bentrok dengan slot yang mulai 09:30.
        $tumpangTindih = TimeSlot::where('is_active', true)
            ->whereKeyNot($timeSlot->id)
            ->where('start_time', '<', $data['end_time'].':00')
            ->where('end_time', '>', $data['start_time'].':00')
            ->exists();

        if ($data['is_active'] && $tumpangTindih) {
            throw ValidationException::withMessages([
                'start_time' => 'Jam bertabrakan dengan slot lain yang aktif.',
            ]);
        }

        $timeSlot->update($data);

        Audit::record('timeslot.updated', $timeSlot, ['changes' => $timeSlot->getChanges()]);

        return back();
    }
}