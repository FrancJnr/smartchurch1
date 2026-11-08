<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::with('role')->orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        return view('users.form', ['account' => new User(['is_active' => true]), 'roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        User::create($this->validated($request));

        return redirect()->route('users.index')->with('success', 'User created. Share their login details securely.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['account' => $user, 'roles' => Role::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        DB::transaction(function () use ($request, $user, $data): void {
            $adminRole = Role::where('is_system', true)->lockForUpdate()->sole();
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($user->id === $request->user()->id && (! $data['is_active'] || (int) $data['role_id'] !== $user->role_id)) {
                throw ValidationException::withMessages(['role_id' => 'You cannot disable your own account or change your own role. Ask another administrator.']);
            }
            if ($user->role_id === $adminRole->id && $user->is_active &&
                (! $data['is_active'] || (int) $data['role_id'] !== $adminRole->id) &&
                ! User::where('role_id', $adminRole->id)->where('is_active', true)->where('id', '!=', $user->id)->exists()) {
                throw ValidationException::withMessages(['role_id' => 'At least one active Administrator must remain.']);
            }
            $resetPassword = ! empty($data['password']);
            if (! $resetPassword) {
                unset($data['password']);
            }
            $user->fill($data);
            if ($resetPassword || ! $user->is_active) {
                $user->remember_token = Str::random(60);
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
            }
            $user->save();
        });

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:12', 'max:255', 'confirmed'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
