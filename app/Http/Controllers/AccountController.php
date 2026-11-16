<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Lab;
use Inertia\Inertia;
use App\Models\User;
use Inertia\Response;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;


class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = User::with('lab:id,name')->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role->value,
            'role_label' => $u->role->label(),
            'lab' => $u->lab?->name,
            'is_active' => $u->is_active,
        ]);

        return Inertia::render('accounts/index', [
            'accounts' => $accounts,
            'meId' => $request->user()->id,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounts/form', [
            'account' => null,
            'roles' => $this->roles(),
            'labs' => $this->labs(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->role = Role::from($data['role']);
        $user->lab_id = $data['lab_id'];
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        Audit::record('account.created', $user, [
            'email' => $user->email,
            'role' => $user->role->value,
            'lab_id' => $user->lab_id,
        ]);

        return redirect('/accounts');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('accounts/form', [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'lab_id' => $user->lab_id,
            ],
            'roles' => $this->roles(),
            'labs' => $this->labs(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $newRole = Role::from($data['role']);

        if ($user->isKetuaLab() && $user->is_active
            && $newRole !== Role::KetuaLab && $this->activeKetuaCount() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Tidak dapat mengubah role Ketua Lab aktif yang terakhir.',
            ]);
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        // if (! empty($data['password'])) {
        //     $user->password = $data['password'];
        // }

        $passwordChanged = ! empty($data['password']);
        if ($passwordChanged) {
            $user->password = $data['password'];
        }

        $user->role = $newRole;
        $user->lab_id = $data['lab_id'];

        $changes = [];
        foreach ($user->getDirty() as $field => $new) {
            if ($field === 'password') {
                continue; // hash tidak boleh masuk log
            }
            $changes[$field] = ['from' => $user->getRawOriginal($field), 'to' => $new];
        }

        if ($passwordChanged) {
            $changes['password'] = 'diubah';
        }

        $user->save();

        if ($changes !== []) {
            Audit::record('account.updated', $user, ['changes' => $changes]);
        }

        return redirect('/accounts');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['status' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        if ($user->is_active && $user->isKetuaLab() && $this->activeKetuaCount() <= 1) {
            return back()->withErrors(['status' => 'Ketua Lab aktif yang terakhir tidak dapat dinonaktifkan.']);
        }

        if (! $user->is_active && $user->isKetuaLab()
            && $this->labHasActiveKetua((int) $user->lab_id, $user->id)) {
            return back()->withErrors(['status' => 'Laboratorium ini sudah memiliki Ketua Lab aktif.']);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        Audit::record($user->is_active ? 'account.activated' : 'account.deactivated', $user);

        return back();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(Role::class)],
            'lab_id' => ['nullable', 'integer', 'exists:labs,id'],
        ]);

        $role = Role::from($data['role']);

        if ($role === Role::Dosen) {
            $data['lab_id'] = null;

            return $data;
        }

        // Ketua Lab dan Asisten Lab wajib terikat ke satu laboratorium.
        if (empty($data['lab_id'])) {
            throw ValidationException::withMessages([
                'lab_id' => "{$role->label()} wajib terikat ke satu laboratorium.",
            ]);
        }

        // Satu Ketua Lab aktif per lab. Asisten Lab tidak dibatasi.
        $akanAktif = $user === null || $user->is_active;

        if ($role === Role::KetuaLab && $akanAktif
            && $this->labHasActiveKetua((int) $data['lab_id'], $user?->id)) {
            throw ValidationException::withMessages([
                'lab_id' => 'Laboratorium ini sudah memiliki Ketua Lab aktif. Nonaktifkan Ketua Lab yang lama terlebih dahulu.',
            ]);
        }

        return $data;
    }

    private function labHasActiveKetua(int $labId, ?int $exceptUserId = null): bool
    {
        return User::where('role', Role::KetuaLab->value)
            ->where('lab_id', $labId)
            ->where('is_active', true)
            ->when($exceptUserId, fn ($q) => $q->whereKeyNot($exceptUserId))
            ->exists();
    }

    private function activeKetuaCount(): int
    {
        return User::where('role', Role::KetuaLab->value)->where('is_active', true)->count();
    }

    private function roles(): array
    {
        return collect(Role::cases())
            ->map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()])
            ->all();
    }

    private function labs()
    {
        return Lab::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
