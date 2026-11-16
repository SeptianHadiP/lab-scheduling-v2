<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Lab;
use App\Models\LabFacility;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Inertia\Inertia;
use Inertia\Response;

class PublicLaboratoryController extends Controller
{
    public function index(): Response
    {
        $labs = Lab::query()
            ->where('is_active', true)
            ->with([
                'facilities:id,lab_id,name,quantity,unit,note',
                'users' => function (HasMany $query): void {
                    $query->where('is_active', true)
                        ->orderBy('name')
                        ->select(['id', 'lab_id', 'name', 'role']);
                },
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Lab $lab): array => [
                'id' => $lab->id,
                'name' => $lab->name,
                'code' => $lab->code,
                'facilities' => $lab->facilities
                    ->map(fn (LabFacility $facility): array => [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'quantity' => $facility->quantity,
                        'unit' => $facility->unit,
                        'note' => $facility->note,
                    ])
                    ->all(),
                'head' => $lab->users->firstWhere('role', Role::KetuaLab)?->name,
                'assistants' => $lab->users
                    ->where('role', Role::AsistenLab)
                    ->pluck('name')
                    ->values()
                    ->all(),
            ])
            ->values();

        return Inertia::render('laboratorium', [
            'labs' => $labs,
        ]);
    }
}
