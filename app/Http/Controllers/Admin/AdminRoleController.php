<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Ruoli di amministrazione.
 *
 * Non usa il modulo generico delle anagrafiche: i permessi vanno
 * mostrati raggruppati per area, altrimenti sono un muro di spunte.
 */
class AdminRoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount('users')->orderByDesc('is_system')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'role' => new Role,
            'groups' => Permissions::groups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $role = Role::create($data + ['slug' => $this->slug($data['name'])]);

        return redirect()->route('admin.roles.index')
            ->with('success', __('Ruolo :name creato.', ['name' => $role->name]));
    }

    public function show(Role $role): RedirectResponse
    {
        return redirect()->route('admin.roles.edit', $role);
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', [
            'role' => $role,
            'groups' => Permissions::groups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request);

        // Il ruolo di sistema concede tutto per definizione: si puo'
        // rinominare, non svuotare. Altrimenti basta una spunta tolta
        // per restare chiusi fuori dal pannello.
        if ($role->is_system) {
            unset($data['permissions']);
        }

        $role->update($data);

        return back()->with('success', __('Modifiche salvate.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', __('Il ruolo di sistema non si puo eliminare.'));
        }

        if ($role->users()->exists()) {
            return back()->with('error', __('Ci sono persone con questo ruolo: assegna loro un altro ruolo prima di eliminarlo.'));
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', __('Ruolo eliminato.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::keys())],
        ]);

        $data['permissions'] = Permissions::sanitize($data['permissions'] ?? []);

        return $data;
    }

    /** Il nome e' libero, l'identificativo no: due ruoli non possono averlo uguale. */
    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'ruolo';
        $slug = $base;

        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
