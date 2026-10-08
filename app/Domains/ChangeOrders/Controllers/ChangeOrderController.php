<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Controllers;

use App\Domains\ChangeOrders\Actions\CreateChangeOrderAction;
use App\Domains\ChangeOrders\Actions\ReorderChangeOrdersAction;
use App\Domains\ChangeOrders\Actions\SendChangeOrderAction;
use App\Domains\ChangeOrders\Actions\UpdateChangeOrderAction;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\ChangeOrders\Queries\ChangeOrderIndexQuery;
use App\Domains\ChangeOrders\Requests\ReorderChangeOrdersRequest;
use App\Domains\ChangeOrders\Requests\ReorderGlobalChangeOrdersRequest;
use App\Domains\ChangeOrders\Requests\StoreChangeOrderRequest;
use App\Domains\ChangeOrders\Requests\UpdateChangeOrderRequest;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ChangeOrderController extends Controller
{
    public function __construct(
        private readonly CreateChangeOrderAction $createAction,
        private readonly UpdateChangeOrderAction $updateAction,
        private readonly SendChangeOrderAction $sendAction,
        private readonly ReorderChangeOrdersAction $reorderAction,
        private readonly ChangeOrderIndexQuery $indexQuery,
        private readonly FeedbackRoundPresenter $presenter,
    ) {}

    public function globalIndex(): Response
    {
        $index = $this->indexQuery->build($this->user());

        return Inertia::render('ChangeOrders/Index', [
            'changeOrders' => $index['changeOrders'],
            'projects' => $index['projects'],
            'customers' => $index['customers'],
        ]);
    }

    /**
     * Manager/admin drag-to-reorder for a single project's list. The client
     * sends each change order's new position; change orders created after a
     * reorder keep a NULL sort_order and sort to the top until the next one.
     */
    public function reorder(ReorderChangeOrdersRequest $request, Project $project): JsonResponse
    {
        $this->reorderAction->execute($project, $request->items());

        return response()->json(['success' => true]);
    }

    /**
     * Manager/admin drag-to-reorder for the all-projects list. Writes the
     * global sequence only, so a change order's position inside its own
     * project is untouched.
     */
    public function reorderGlobal(ReorderGlobalChangeOrdersRequest $request): JsonResponse
    {
        $this->reorderAction->executeGlobal($request->items());

        return response()->json(['success' => true]);
    }

    public function index(Project $project): Response
    {
        $this->authorize('viewAny', [ChangeOrder::class, $project]);

        $query = $project->changeOrders()
            ->with(['creator.media', 'items', 'recipients.user.media'])
            ->withCount('recipients')
            ->orderBy('sort_order')
            ->orderByDesc('id');

        if ($this->user()->hasRole('customer')) {
            $query->where('status', '!=', 'draft');
        }

        $changeOrders = $query->get();

        $changeOrders->each(function (ChangeOrder $co): void {
            if ($co->creator !== null) {
                $co->creator->setAttribute('profile_photo_url', $co->creator->profilePhotoUrl());
            }
            $co->recipients->each(function (ChangeOrderRecipient $recipient): void {
                if ($recipient->user !== null) {
                    $recipient->user->setAttribute('profile_photo_url', $recipient->user->profilePhotoUrl());
                }
            });
        });

        return Inertia::render('Projects/ChangeOrders/Index', [
            'project' => ['id' => $project->id, 'hash_id' => $project->hash_id, 'name' => $project->name],
            'changeOrders' => $changeOrders,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('create', [ChangeOrder::class, $project]);

        return Inertia::render('Projects/ChangeOrders/Create', [
            'project' => ['id' => $project->id, 'hash_id' => $project->hash_id, 'name' => $project->name],
            'customers' => $this->projectCustomers($project),
            'titleLimit' => ChangeOrder::MAX_TITLE_LENGTH,
            'photoLimit' => ChangeOrder::MAX_PHOTOS,
            'descriptionLimit' => ChangeOrder::MAX_DESCRIPTION_LENGTH,
            'itemVendorLimit' => ChangeOrder::MAX_ITEM_VENDORS,
            'itemDescriptionLimit' => ChangeOrder::MAX_ITEM_DESCRIPTION_LENGTH,
        ]);
    }

    public function store(StoreChangeOrderRequest $request, Project $project): RedirectResponse
    {
        $this->createAction->execute($project, $request->toDTO(), $this->user(), $request->input('recipient_ids', []));

        return redirect()
            ->route('projects.change-orders.index', $project)
            ->with('success', 'Change order saved as draft.');
    }

    public function show(Project $project, ChangeOrder $changeOrder): Response|RedirectResponse
    {
        $this->authorize('view', $changeOrder);
        abort_unless($changeOrder->project_id === $project->id, 404);

        if ($this->user()->hasRole('customer')) {
            return redirect()->route('customer.change-orders.show', [$project, $changeOrder]);
        }

        $changeOrder->load(['creator.media', 'items', 'recipients.user.media', 'photos']);

        if ($changeOrder->creator !== null) {
            $changeOrder->creator->setAttribute('profile_photo_url', $changeOrder->creator->profilePhotoUrl());
        }

        $changeOrder->recipients->each(function (ChangeOrderRecipient $recipient): void {
            if ($recipient->user !== null) {
                $recipient->user->setAttribute('profile_photo_url', $recipient->user->profilePhotoUrl());
            }
        });

        return Inertia::render('Projects/ChangeOrders/Show', [
            'project' => ['id' => $project->id, 'hash_id' => $project->hash_id, 'name' => $project->name],
            'changeOrder' => $changeOrder,
            'photoLimit' => ChangeOrder::MAX_PHOTOS,
            'feedbackRound' => $this->presenter->present($changeOrder->latestActiveFeedbackRound(), $this->user()),
            'feedbackRoundHistory' => $this->presenter->presentAll($changeOrder->feedbackRoundHistory(), $this->user()),
            'technicians' => $project->fieldSupervisors()->select(['users.id', 'users.name', 'users.email'])->get(),
        ]);
    }

    public function edit(Project $project, ChangeOrder $changeOrder): Response
    {
        $this->authorize('view', $changeOrder);
        abort_unless($changeOrder->project_id === $project->id, 404);

        $changeOrder->load(['items', 'recipients.user.media', 'photos']);

        return Inertia::render('Projects/ChangeOrders/Edit', [
            'project' => ['id' => $project->id, 'hash_id' => $project->hash_id, 'name' => $project->name],
            'changeOrder' => $changeOrder,
            'customers' => $this->projectCustomers($project),
            'titleLimit' => ChangeOrder::MAX_TITLE_LENGTH,
            'photoLimit' => ChangeOrder::MAX_PHOTOS,
            'descriptionLimit' => ChangeOrder::MAX_DESCRIPTION_LENGTH,
            'itemVendorLimit' => ChangeOrder::MAX_ITEM_VENDORS,
            'itemDescriptionLimit' => ChangeOrder::MAX_ITEM_DESCRIPTION_LENGTH,
        ]);
    }

    public function update(UpdateChangeOrderRequest $request, Project $project, ChangeOrder $changeOrder): RedirectResponse
    {
        abort_unless($changeOrder->project_id === $project->id, 404);

        $this->updateAction->execute($changeOrder, $request->toDTO(), $request->input('recipient_ids', []));

        return redirect()
            ->route('projects.change-orders.show', [$project, $changeOrder])
            ->with('success', 'Change order updated.');
    }

    public function destroy(Request $request, Project $project, ChangeOrder $changeOrder): RedirectResponse
    {
        $this->authorize('delete', $changeOrder);
        abort_unless($changeOrder->project_id === $project->id, 404);

        $changeOrder->delete();

        return redirect()
            ->route('projects.change-orders.index', $project)
            ->with('success', 'Change order deleted.');
    }

    public function send(Project $project, ChangeOrder $changeOrder): RedirectResponse
    {
        $this->authorize('send', $changeOrder);
        abort_unless($changeOrder->project_id === $project->id, 404);

        try {
            $this->sendAction->execute($changeOrder);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('projects.change-orders.show', [$project, $changeOrder])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('projects.change-orders.show', [$project, $changeOrder])
            ->with('success', 'Change order sent to customer(s).');
    }

    /**
     * Customers with portal access to this project — the recipient pool.
     *
     * @return Collection<int, array{id: int, name: string, email: string}>
     */
    private function projectCustomers(Project $project): Collection
    {
        return $project->customerAccesses()
            ->with(['user.media'])
            ->get()
            ->filter(fn ($access): bool => $access->user !== null)
            ->map(fn ($access): array => [
                'id' => $access->user->id,
                'name' => $access->user->name,
                'email' => $access->user->email,
                'profile_photo_url' => $access->user->profilePhotoUrl(),
            ])
            ->unique('id')
            ->values();
    }
}
