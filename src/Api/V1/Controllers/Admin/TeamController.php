<?php

namespace Meva\Api\V1\Controllers\Admin;

use Database\Seeders\AccessSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Meva\Api\V1\Controllers\Controller;
use Meva\Entities\Role\Models\Role;
use Meva\Entities\User\Models\User;

/**
 * Who works on the shop, and what each of them may do.
 *
 * Customers have accounts too; this only lists the people with a role that
 * grants access to the back office.
 */
class TeamController extends Controller
{
    /**
     * Everyone with back-office access.
     */
    public function index(Request $request)
    {
        $staffRoles = array_diff(array_keys(AccessSeeder::ROLES), ['customer']);

        $users = User::query()
            ->with('roles')
            ->when($request->boolean('customers'), fn ($q) => $q->role('customer'), fn ($q) => $q->role($staffRoles))
            ->orderBy('first_name')
            ->paginate(min((int) $request->input('per_page', 50), 200));

        return $this->response->array([
            'data' => $users->map(fn (User $user): array => $this->present($user))->all(),
            'meta' => ['total' => $users->total(), 'current_page' => $users->currentPage(), 'last_page' => $users->lastPage()],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Add someone to the team.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:60',
            'last_name' => 'required|string|max:60',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:10',
            'role' => 'required|string|in:'.implode(',', array_keys(AccessSeeder::ROLES)),
        ]);

        $user = User::query()->create(collect($data)->except('role')->all());
        $user->syncRoles($data['role']);

        return $this->response->array(['data' => $this->present($user->refresh())])
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change someone's details, role or password.
     */
    public function update(Request $request, int $id)
    {
        $user = User::query()->findOrFail($id);

        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:60',
            'last_name' => 'sometimes|required|string|max:60',
            'email' => 'sometimes|required|email|max:190|unique:users,email,'.$id,
            'password' => 'sometimes|required|string|min:10',
            'role' => 'sometimes|required|string|in:'.implode(',', array_keys(AccessSeeder::ROLES)),
        ]);

        $user->fill(collect($data)->except('role')->all())->save();

        if (isset($data['role'])) {
            // The shop must never be left without an owner.
            if ($user->hasRole('super-admin') && $data['role'] !== 'super-admin' && $this->owners() <= 1) {
                return response()->json(['message' => 'Mora postojati bar jedan vlasnik.'], 422);
            }

            $user->syncRoles($data['role']);
        }

        return $this->response->array(['data' => $this->present($user->refresh())])
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Remove someone's access.
     */
    public function destroy(int $id)
    {
        $user = User::query()->findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Ne možete obrisati sopstveni nalog.'], 422);
        }

        if ($user->hasRole('super-admin') && $this->owners() <= 1) {
            return response()->json(['message' => 'Mora postojati bar jedan vlasnik.'], 422);
        }

        $user->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The roles that may be handed out, and what each one allows.
     */
    public function roles()
    {
        return $this->response->array([
            'data' => Role::query()->with('permissions')->get()->map(fn (Role $role): array => [
                'name' => $role->name,
                'label' => $role->label ?: $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ])->all(),
            'meta' => ['permissions' => AccessSeeder::PERMISSIONS],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
            'role_label' => $user->roles->first()?->label,
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    protected function owners(): int
    {
        return User::query()->role('super-admin')->count();
    }
}
