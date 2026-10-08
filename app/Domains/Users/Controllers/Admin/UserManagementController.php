<?php

declare(strict_types=1);

namespace App\Domains\Users\Controllers\Admin;

use App\Domains\Markets\Queries\MarketQuery;
use App\Domains\Users\Actions\ChangeUserPasswordAction;
use App\Domains\Users\Actions\CreateUserAction;
use App\Domains\Users\Actions\DeleteUserAction;
use App\Domains\Users\Actions\ToggleUserActiveAction;
use App\Domains\Users\Actions\UpdateUserAction;
use App\Domains\Users\Models\User;
use App\Domains\Users\Queries\UserQuery;
use App\Domains\Users\Requests\ChangeUserPasswordRequest;
use App\Domains\Users\Requests\StoreUserRequest;
use App\Domains\Users\Requests\UpdateUserRequest;
use App\Http\Controllers\Controller;
use App\Shared\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserQuery $userQuery,
        private readonly MarketQuery $marketQuery,
        private readonly CreateUserAction $createUserAction,
        private readonly UpdateUserAction $updateUserAction,
        private readonly DeleteUserAction $deleteUserAction,
        private readonly ToggleUserActiveAction $toggleUserActiveAction,
        private readonly ChangeUserPasswordAction $changeUserPasswordAction,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('manage', User::class);

        /** @var array<string, mixed> $filters */
        $filters = $request->only(['search', 'role', 'is_active', 'market_filter']);

        return Inertia::render('Admin/Users/Index', [
            'users' => $this->userQuery->listForAdmin($filters),
            'markets' => $this->marketQuery->listActive(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manage', User::class);

        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::internalValues(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->createUserAction->execute($request->toDTO());

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('manage', $user);

        $markets = $this->marketQuery->listActive();

        return Inertia::render('Admin/Users/Edit', [
            'user' => $this->userQuery->show($user),
            'roles' => Role::internalValues(),
            'markets' => $markets,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->updateUserAction->execute($user, $request->toDTO());

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->deleteUserAction->execute($user);

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $this->toggleUserActiveAction->execute($user);

        return redirect()->route('admin.users.index')
            ->with('success', $user->is_active ? 'User activated.' : 'User deactivated.');
    }

    public function changePassword(ChangeUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->changeUserPasswordAction->execute($user, $request->toDTO());

        return redirect()->route('admin.users.index')->with('success', 'Password updated.');
    }
}
