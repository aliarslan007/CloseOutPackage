<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Controllers;

use App\Domains\CustomerPortal\Actions\GrantCustomerProjectAccessAction;
use App\Domains\CustomerPortal\Actions\RevokeCustomerProjectAccessAction;
use App\Domains\CustomerPortal\Queries\CustomerAccessQuery;
use App\Domains\CustomerPortal\Requests\GrantCustomerAccessRequest;
use App\Domains\CustomerPortal\Requests\RevokeCustomerAccessRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Queries\ProjectQuery;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAccessController extends Controller
{
    public function __construct(
        private readonly CustomerAccessQuery $customerAccessQuery,
        private readonly GrantCustomerProjectAccessAction $grantAccessAction,
        private readonly RevokeCustomerProjectAccessAction $revokeAccessAction,
        private readonly ProjectQuery $projectQuery,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $this->customerAccessQuery->forUser($user)->items(),
            'projects' => $this->projectQuery->accessibleOptions($user),
        ]);
    }

    public function store(GrantCustomerAccessRequest $request, User $user): RedirectResponse
    {
        $this->grantAccessAction->execute($request->toDTO());

        return redirect()->back()->with('success', 'Customer access granted.');
    }

    public function destroy(RevokeCustomerAccessRequest $request, User $user, Project $project): RedirectResponse
    {
        $this->revokeAccessAction->execute($user, $project, $request->user());

        return redirect()->back()->with('success', 'Customer access revoked.');
    }
}
