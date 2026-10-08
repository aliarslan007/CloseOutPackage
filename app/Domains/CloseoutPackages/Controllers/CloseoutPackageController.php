<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\Approval\DTOs\ReadinessFailure;
use App\Domains\Approval\Services\ApprovalReadinessService;
use App\Domains\CloseoutPackages\Actions\CreatePackageAction;
use App\Domains\CloseoutPackages\Actions\DeletePackageAction;
use App\Domains\CloseoutPackages\Actions\MarkPackageUnderReviewAction;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Queries\PackageQuery;
use App\Domains\CloseoutPackages\Queries\PackageStatusFilter;
use App\Domains\CloseoutPackages\Requests\StoreCloseoutPackageRequest;
use App\Domains\CloseoutPackages\Services\TemplateDriftService;
use App\Domains\CloseoutPackages\Support\PackageLockFlags;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Http\Controllers\Controller;
use App\Shared\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CloseoutPackageController extends Controller
{
    public function __construct(
        private readonly PackageQuery $packageQuery,
        private readonly PackageStatusFilter $packageStatusFilter,
        private readonly TemplateQuery $templateQuery,
        private readonly CreatePackageAction $createPackageAction,
        private readonly DeletePackageAction $deletePackageAction,
        private readonly ApprovalReadinessService $readinessService,
        private readonly MarkPackageUnderReviewAction $markUnderReviewAction,
        private readonly TemplateDriftService $driftService,
        private readonly FeedbackRoundPresenter $presenter,
    ) {}

    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        /** @var string|null $status */
        $status = request()->query('status');
        $status = is_string($status) && $status !== '' && $this->packageStatusFilter->supports($status)
            ? $status
            : null;

        $packages = $this->packageQuery->forProject($project, $status);

        foreach ($packages->items() as $package) {
            $package->setAttribute('can_delete', Gate::allows('delete', $package));
        }

        PackageLockFlags::attach($packages->items(), $this->user());

        $favorites = $this->templateQuery->favoriteOptions($this->user(), $project->market_id);

        return Inertia::render('Packages/Index', [
            'project' => $project,
            'packages' => $packages,
            'status' => $status,
            'favorites' => $favorites,
            // Mirrors the create() policy so the UI never shows the button to users
            // who would be rejected on submit (admin is intentionally excluded).
            'can_create' => Gate::allows('create', [CloseoutPackage::class, $project]),
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('create', [CloseoutPackage::class, $project]);

        return Inertia::render('Packages/Create', [
            'project' => $project,
            'templates' => $this->templateQuery->forMarket($project->market_id),
        ]);
    }

    public function store(StoreCloseoutPackageRequest $request, Project $project): RedirectResponse
    {
        $package = $this->createPackageAction->execute($request->toDTO(), $this->user());

        return redirect()->route('packages.show', $package)->with('success', 'Package created.');
    }

    public function show(CloseoutPackage $package): Response
    {
        $this->authorize('view', $package);

        if ($package->status === PackageStatus::DRAFT) {
            $this->driftService->applySafeChanges($package);
            $package->unsetRelations();
        }

        $package = $this->packageQuery->show($package);

        if (! Gate::allows('update', $package)) {
            if (! Gate::allows('viewGps', Photo::class)) {
                $package->photos->each(
                    fn (Photo $photo): Photo => $photo->setHidden(['gps_latitude', 'gps_longitude', 'gps_accuracy'])
                );
            }

            $this->markUnderReviewAction->execute($package);

            $report = $this->readinessService->check($package);

            $latestPartial = $package->relationLoaded('packageRevisions')
                ? $package->packageRevisions
                    ->filter(fn (PackageRevision $r): bool => $r->type === PdfType::PARTIAL && $r->storage_path !== null)
                    ->sortByDesc('id')
                    ->first()
                : $package->latestPartialRevision()
                    ->whereNotNull('storage_path')
                    ->first();

            $latestFinal = $package->relationLoaded('packageRevisions')
                ? $package->packageRevisions
                    ->filter(fn (PackageRevision $r): bool => $r->type === PdfType::FINAL)
                    ->sortByDesc('id')
                    ->first()
                : $package->latestFinalRevision()->first();

            return Inertia::render('Packages/Review', [
                'package' => $package,
                'readiness' => [
                    'is_ready' => $report->isReady,
                    'failures' => array_map(
                        fn (ReadinessFailure $failure): array => ['message' => $failure->message],
                        $report->failures,
                    ),
                ],
                'revisions' => $this->revisionHistory($package),
                'partial_pdf_available' => $latestPartial !== null,
                'partial_pdf_generated_at' => $latestPartial?->generated_at?->toISOString(),
                'final_pdf_generated_at' => $latestFinal?->generated_at?->toISOString(),
                'can_lock' => Gate::allows('lock', $package),
                'can_unlock' => Gate::allows('unlock', $package),
                'can_update_site_notes' => Gate::allows('updateSiteNotes', $package),
                'primary_technician' => $this->primaryTechnician($package),
                // Customer feedback waiting on the manager — surfaces as a pulsing
                // nudge on the Customer Feedback button.
                'feedbackRound' => $this->presenter->present($package->latestActiveFeedbackRound(), $this->user()),
                'feedback_round' => $this->presenter->present($package->latestActiveFeedbackRound(), $this->user()),
                'feedbackRoundHistory' => $this->presenter->presentAll($package->feedbackRoundHistory(), $this->user()),
                'feedback_round_history' => $this->presenter->presentAll($package->feedbackRoundHistory(), $this->user()),
                'technicians' => $package->project->fieldSupervisors()
                    ->select(['users.id', 'users.name', 'users.email'])->get(),
                'has_pending_feedback' => $package->feedbackRounds()
                    ->awaitingReply()
                    ->exists(),
            ]);
        }

        $latestRound = $package->latestActiveFeedbackRound();
        $presentedRound = $this->presenter->present($latestRound, $this->user());
        $presentedHistory = $this->presenter->presentAll($package->feedbackRoundHistory(), $this->user());
        $isTech = $this->user()->hasRole(Role::FIELD_SUPERVISOR->value) && ! $this->user()->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
        $hasPendingFeedback = $isTech
            ? ($presentedRound !== null && ($presentedRound['status'] ?? null) === FeedbackRoundStatus::SUBMITTED->value)
            : $package->feedbackRounds()->awaitingReply()->exists();
        $hasFeedback = $isTech
            ? ($presentedRound !== null || count($presentedHistory) > 0)
            : $package->feedbackRounds()->exists();

        $drift = $this->driftService->detect($package);

        return Inertia::render('Packages/Show', [
            'package' => $package,
            'favorites' => $this->templateQuery->favoritesFor($this->user()),
            'drift' => $drift,
            'can_lock' => Gate::allows('lock', $package),
            'can_unlock' => Gate::allows('unlock', $package),
            'can_update_site_notes' => Gate::allows('updateSiteNotes', $package),
            'primary_technician' => $this->primaryTechnician($package),
            'has_feedback' => $hasFeedback,
            'feedbackRound' => $presentedRound,
            'feedback_round' => $presentedRound,
            'feedbackRoundHistory' => $presentedHistory,
            'feedback_round_history' => $presentedHistory,
            'technicians' => $package->project->fieldSupervisors()
                ->select(['users.id', 'users.name', 'users.email'])->get(),
            'has_pending_feedback' => $hasPendingFeedback,
        ]);
    }

    /**
     * The project's starred primary field technician, so anyone on the package
     * can see who to contact without hunting through the project team. Null
     * until a manager stars one; the strip then says so rather than hiding.
     *
     * @return array{id: int, name: string, email: string|null, profile_photo_url: string|null}|null
     */
    private function primaryTechnician(CloseoutPackage $package): ?array
    {
        $technician = $package->project?->loadMissing('primarySupervisor.media')->primarySupervisor;

        if ($technician === null) {
            return null;
        }

        return [
            'id' => $technician->id,
            'name' => $technician->name,
            'email' => $technician->email,
            'profile_photo_url' => $technician->profile_photo_url,
        ];
    }

    /**
     * Serialize the package's revision history for display.
     *
     * @return list<array{id: int, revisionNumber: string, generatedAt: ?string, generatedBy: string, type: string, downloadUrl: string}>
     */
    private function revisionHistory(CloseoutPackage $package): array
    {
        return array_values(
            $package->packageRevisions
                ->sortByDesc('id')
                ->map(fn (PackageRevision $revision): array => [
                    'id' => $revision->id,
                    'revisionNumber' => $revision->revision_label ?? ('R'.$revision->revision_major.'.'.$revision->revision_minor),
                    'generatedAt' => $revision->generated_at?->toIso8601String(),
                    // generated_by is a nullable FK; `??` handles a missing user.
                    'generatedBy' => $revision->generatedBy->name ?? '—',
                    'type' => $revision->type->value,
                    'downloadUrl' => route('packages.pdf.revision-download', [$package->getRouteKey(), $revision->id]),
                ])
                ->all(),
        );
    }

    public function destroy(CloseoutPackage $package): RedirectResponse
    {
        $this->authorize('delete', $package);

        $this->deletePackageAction->execute($package);

        return redirect()->route('packages.index', $package->project)->with('success', 'Package deleted.');
    }
}
