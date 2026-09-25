<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\BulkSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Utenti: clienti, aziende e collaboratori dell'amministrazione.
 *
 * Le tre cose stanno nella stessa tabella e si distinguono per `user_type`.
 * Il ruolo, e quindi i permessi, riguarda solo chi sta in amministrazione.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $users = $this->filters(User::query()->with('role'), $request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'types' => User::TYPES,
            'counts' => [
                'admin' => User::where('user_type', 'admin')->count(),
                'vendor' => User::where('user_type', 'vendor')->count(),
                'buyer' => User::where('user_type', 'buyer')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', $this->formData(new User(['user_type' => 'admin', 'is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = User::create($data);

        return redirect()->route('admin.users.edit', $user)
            ->with('success', __('Utente creato. Comunica la password a :name.', ['name' => $user->name]));
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.edit', $user);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        // Nessuno si toglie da solo l'amministrazione: senza questo,
        // l'ultima persona che puo' gestire gli utenti puo' sparire.
        if ($user->is($request->user())) {
            unset($data['user_type'], $data['role_id'], $data['is_active']);
        }

        $user->update($data);

        return back()->with('success', __('Modifiche salvate.'));
    }

    /** I filtri dell'elenco, gli stessi per la pagina e per "tutti i risultati". */
    public function filters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->string('cerca')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%")
            ))
            ->when($request->string('tipo')->toString(), fn ($q, $type) => $q->where('user_type', $type))
            ->when($request->string('ruolo')->toString(), fn ($q, $role) => $q->where('role_id', $role));
    }

    /**
     * Riattiva, sospende o elimina piu' utenti insieme.
     *
     * Il proprio accesso resta sempre fuori, come nei bottoni di riga; chi ha
     * un'azienda collegata non si elimina da qui, e l'eliminazione vale solo
     * sulle righe spuntate.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate(
            BulkSelection::rules(['activate', 'deactivate', 'delete'], onlySelected: ['delete']),
            BulkSelection::messages()
        );

        $query = BulkSelection::query($request, User::query(), $this->filters(...))
            ->whereKeyNot($request->user()->getKey());

        $message = match ($request->input('action')) {
            'activate' => trans_choice(':count accesso riattivato.|:count accessi riattivati.', $query->update(['is_active' => true])),
            'deactivate' => trans_choice(':count accesso sospeso.|:count accessi sospesi.', $query->update(['is_active' => false])),
            'delete' => $this->deleteUsers($query),
        };

        return back()->with('success', $message);
    }

    private function deleteUsers(Builder $query): string
    {
        $kept = (clone $query)->whereHas('company')->count();
        $message = trans_choice(':count utente eliminato.|:count utenti eliminati.', BulkSelection::deleteEach($query->whereDoesntHave('company')));

        if ($kept) {
            $message .= ' '.trans_choice(':count lasciato: ha un\'azienda collegata.|:count lasciati: hanno un\'azienda collegata.', $kept);
        }

        return $message;
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', __('Non puoi sospendere te stesso.'));
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active
            ? __('Accesso riattivato.')
            : __('Accesso sospeso.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', __('Non puoi eliminare il tuo stesso accesso.'));
        }

        if ($user->company()->exists()) {
            return back()->with('error', __('Questo utente ha un azienda collegata: elimina prima l azienda.'));
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', __('Utente eliminato.'));
    }

    /**
     * Regole del modulo.
     *
     * Il ruolo si assegna solo a chi sta in amministrazione, e il ruolo
     * di sistema lo puo' dare solo chi ce l'ha: altrimenti bastano i
     * permessi sugli utenti per farsi amministratori da soli.
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $assignable = $this->assignableRoles($request)->pluck('id');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'user_type' => ['required', Rule::in(array_keys(User::TYPES))],
            // Senza ruolo chi sta in amministrazione trova solo il 403.
            'role_id' => ['nullable', 'required_if:user_type,admin', Rule::in($assignable)],
            'is_active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ], [
            'role_id.required_if' => __('Scegli un ruolo: senza ruolo non si entra nel pannello di amministrazione.'),
        ]);

        if ($data['user_type'] !== 'admin') {
            $data['role_id'] = null;
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => $this->assignableRoles(request()),
            'types' => User::TYPES,
        ];
    }

    /** @return Collection<int, Role> */
    private function assignableRoles(Request $request)
    {
        $roles = Role::orderByDesc('is_system')->orderBy('name')->get();

        if ($request->user()?->role?->isSuperAdmin()) {
            return $roles;
        }

        return $roles->reject->is_system->values();
    }
}
