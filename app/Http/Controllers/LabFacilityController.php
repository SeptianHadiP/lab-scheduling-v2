<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\LabFacility;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LabFacilityController extends Controller
{
    public function index(Request $request): Response
    {
        $labs = Lab::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $selected = $labs->firstWhere('id', (int) $request->query('lab'))
            ?? $labs->firstWhere('id', $request->user()->lab_id)
            ?? $labs->first();

        return Inertia::render('facilities/index', [
            'labs' => $labs,
            'selectedLabId' => $selected?->id,
            'canManage' => $selected !== null && Gate::allows('manage-lab-facilities', $selected->id),
            'facilities' => $selected
                ? $selected->facilities()->orderBy('name')->get(['id', 'name', 'quantity', 'unit', 'note'])
                : [],
        ]);
    }

    public function store(Request $request, Lab $lab): RedirectResponse
    {
        Gate::authorize('manage-lab-facilities', $lab->id);

        $facility = $lab->facilities()->create($this->validated($request, $lab->id));

        Audit::record('facility.created', $facility, [
            'lab_id' => $lab->id,
            'name' => $facility->name,
            'quantity' => $facility->quantity,
        ]);

        return back();
    }

    public function update(Request $request, LabFacility $facility): RedirectResponse
    {
        Gate::authorize('manage-lab-facilities', $facility->lab_id);

        $facility->fill($this->validated($request, $facility->lab_id, $facility->id));

        $changes = [];
        foreach ($facility->getDirty() as $field => $new) {
            $changes[$field] = ['from' => $facility->getRawOriginal($field), 'to' => $new];
        }

        $facility->save();

        if ($changes !== []) {
            Audit::record('facility.updated', $facility, [
                'lab_id' => $facility->lab_id,
                'changes' => $changes,
            ]);
        }

        return back();
    }

    public function destroy(LabFacility $facility): RedirectResponse
    {
        Gate::authorize('manage-lab-facilities', $facility->lab_id);

        Audit::record('facility.deleted', $facility, [
            'lab_id' => $facility->lab_id,
            'name' => $facility->name,
            'quantity' => $facility->quantity,
        ]);

        $facility->delete();

        return back();
    }

    private function validated(Request $request, int $labId, ?int $ignoreId = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('lab_facilities', 'name')->where('lab_id', $labId)->ignore($ignoreId),
            ],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
