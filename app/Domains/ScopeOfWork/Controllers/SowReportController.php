<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Controllers;

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Actions\ArchiveSowReportAction;
use App\Domains\ScopeOfWork\Actions\AttachSowToProjectAction;
use App\Domains\ScopeOfWork\Actions\CreateSowReportAction;
use App\Domains\ScopeOfWork\Actions\DeleteSowReportAction;
use App\Domains\ScopeOfWork\Actions\GenerateSowPdfAction;
use App\Domains\ScopeOfWork\Actions\PublishSowReportAction;
use App\Domains\ScopeOfWork\Actions\ReopenSowReportAction;
use App\Domains\ScopeOfWork\Actions\SubmitSowForReviewAction;
use App\Domains\ScopeOfWork\Actions\UnarchiveSowReportAction;
use App\Domains\ScopeOfWork\Actions\UpdateSowReportAction;
use App\Domains\ScopeOfWork\DTOs\AttachSowToProjectDTO;
use App\Domains\ScopeOfWork\Enums\SowPdfGenerationStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Jobs\GenerateSowPdfJob;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Requests\AttachSowToProjectRequest;
use App\Domains\ScopeOfWork\Requests\ReopenSowReportRequest;
use App\Domains\ScopeOfWork\Requests\StoreSowReportRequest;
use App\Domains\ScopeOfWork\Requests\UpdateSowReportRequest;
use App\Domains\ScopeOfWork\Services\SowReportAccessScope;
use App\Domains\ScopeOfWork\Services\SowReportDtoFactory;
use App\Http\Controllers\Controller;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SowReportController extends Controller
{
    public function __construct(
        private readonly CreateSowReportAction $createAction,
        private readonly UpdateSowReportAction $updateAction,
        private readonly DeleteSowReportAction $deleteAction,
        private readonly SubmitSowForReviewAction $submitAction,
        private readonly ReopenSowReportAction $reopenAction,
        private readonly ArchiveSowReportAction $archiveAction,
        private readonly UnarchiveSowReportAction $unarchiveAction,
        private readonly AttachSowToProjectAction $attachAction,
        private readonly PublishSowReportAction $publishAction,
        private readonly SowReportAccessScope $accessScope,
        private readonly SowReportDtoFactory $dtoFactory,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SowReport::class);

        $user = $this->user();
        $query = SowReport::query()
            ->with(['market', 'creator.roles', 'project'])
            ->withCount('annotatedImages')
            ->latest();

        if (! $user->hasRole(Role::ADMIN->value)) {
            $marketIds = $this->accessScope->marketIdsFor($user);

            $query->where(function (Builder $q) use ($user, $marketIds): void {
                $q->where('created_by', $user->id);

                $q->orWhere(function (Builder $sub) use ($user, $marketIds): void {
                    $sub->whereIn('market_id', $marketIds);

                    if ($user->hasRole(Role::MANAGER->value)) {
                        $sub->where(function (Builder $mgr) use ($user): void {
                            $mgr->whereNull('project_id')
                                ->orWhereHas('project', function (Builder $proj) use ($user): void {
                                    $proj->where('manager_id', $user->id)
                                        ->orWhereHas('fieldSupervisors', fn (Builder $fs): Builder => $fs->whereKey($user->id));
                                });
                        });
                    } elseif ($user->hasRole(Role::FIELD_SUPERVISOR->value)) {
                        $sub->whereHas('project', function (Builder $proj) use ($user): void {
                            $proj->whereHas('fieldSupervisors', fn (Builder $fs): Builder => $fs->whereKey($user->id));
                        })
                            // A manager's or admin's draft is their working copy and
                            // stays private until published. The technician's own
                            // draft stays visible. Mirrors
                            // SowReportAccess::isVisibleToTechnician, which the
                            // show/download policy path uses.
                            ->where(function (Builder $drafts) use ($user): void {
                                $drafts->where('status', '!=', SowStatus::DRAFT->value)
                                    ->orWhere('created_by', $user->id);
                            });
                    } elseif ($user->hasRole(Role::CUSTOMER->value)) {

                        $sub->where(function (Builder $cust) use ($user): void {
                            $cust->whereNull('project_id')
                                ->orWhereHas('project.customerAccesses', fn (Builder $ca): Builder => $ca->where('user_id', $user->id));
                        });
                    } else {
                        $sub->whereRaw('1 = 0');
                    }
                });
            });
        }

        if ($user->hasRole(Role::CUSTOMER->value)) {
            $query->where('status', SowStatus::PUBLISHED->value);
        }

        // Register tab counts — same scoping, ignoring the status filter.
        /** @var array<string, int|string> $statusCounts */
        $statusCounts = (clone $query)->toBase()
            ->reorder()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $status = $request->string('status')->toString();
        if ($status !== '') {
            $query->where('status', $status);
        }

        $countFor = static fn (SowStatus $status): int => (int) ($statusCounts[$status->value] ?? 0);

        return Inertia::render('ScopeOfWork/Index', [
            'reports' => $query->get(),
            'filters' => ['status' => $status !== '' ? $status : null],
            'counts' => [
                'all' => array_sum(array_map(intval(...), $statusCounts)),
                'draft' => $countFor(SowStatus::DRAFT),
                'in_review' => $countFor(SowStatus::IN_REVIEW),
                'published' => $countFor(SowStatus::PUBLISHED),
                'archived' => $countFor(SowStatus::ARCHIVED),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SowReport::class);

        $user = $this->user();
        $marketIds = $this->accessScope->marketIdsFor($user);

        $markets = Market::query()
            ->whereIn('id', $marketIds)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $projects = $this->accessScope->projectsFor($user, $marketIds);

        return Inertia::render('ScopeOfWork/Create', [
            'markets' => $markets,
            'projects' => $projects,
        ]);
    }

    public function store(StoreSowReportRequest $request): RedirectResponse
    {
        $report = $this->createAction->execute($this->dtoFactory->create($request->validated()), $this->user());

        return redirect()
            ->route('sow.reports.show', $report)
            ->with('success', 'SOW report created.');
    }

    public function show(SowReport $report): Response
    {
        $this->authorize('view', $report);

        $report->load([
            'market',
            'project',
            'creator.roles',
            'reopenedBy',
            'annotatedImages.mediaAsset',
            'imageGroups',
            'coverPhoto',
        ]);

        // Per-photo remove flags for the strip (ownership + status rule).
        $viewer = $this->user();
        $report->annotatedImages->each(function (SowAnnotatedImage $image) use ($viewer, $report): void {
            $image->setAttribute('can_delete', $viewer->can('deleteImage', [$report, $image]));
        });

        return Inertia::render('ScopeOfWork/Show', [
            'report' => $report,
            'checklist' => $report->checklist(),
            'can' => $this->abilities($report),
            'maxImages' => SowReport::MAX_IMAGES_PER_REPORT,
            // §9.13 — same-market projects for the standalone→project attach dialog.
            'projects' => $report->project_id === null && $this->user()->can('attach', $report)
                ? Project::query()
                    ->where('market_id', $report->market_id)
                    ->when(
                        ! $this->user()->hasRole(Role::ADMIN->value),
                        fn (Builder $q): Builder => $q->where(function (Builder $sub): void {
                            $sub->where('manager_id', $this->user()->id)
                                ->orWhereHas('fieldSupervisors', fn (Builder $fs): Builder => $fs->whereKey($this->user()->id));
                        })
                    )
                    ->orderBy('name')
                    ->get(['id', 'name', 'ps_number'])
                : collect(),
        ]);
    }

    public function update(UpdateSowReportRequest $request, SowReport $report): RedirectResponse
    {
        $this->updateAction->execute($report, $request->validated());

        return redirect()->back()->with('success', 'SOW report updated.');
    }

    public function destroy(SowReport $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        $this->deleteAction->execute($report);

        return redirect()->route('sow.reports.index')->with('success', 'SOW report deleted.');
    }

    public function submit(SowReport $report): RedirectResponse
    {
        $this->authorize('submit', $report);

        $this->submitAction->execute($report, $this->user());

        return redirect()->back()->with('success', 'SOW submitted for review.');
    }

    public function publish(SowReport $report): RedirectResponse
    {
        $this->authorize('publish', $report);

        $this->publishAction->execute($report, $this->user());

        return redirect()->back()->with('success', 'SOW report approved and finalized.');
    }

    public function reopen(ReopenSowReportRequest $request, SowReport $report): RedirectResponse
    {
        $this->authorize('reopen', $report);

        $notes = $request->string('notes')->trim()->toString();
        $this->reopenAction->execute($report, $this->user(), $notes !== '' ? $notes : null);

        return redirect()->back()->with('success', 'SOW reopened for editing.');
    }

    public function archive(SowReport $report): RedirectResponse
    {
        $this->authorize('archive', $report);

        $this->archiveAction->execute($report);

        return redirect()->back()->with('success', 'SOW archived.');
    }

    public function unarchive(SowReport $report): RedirectResponse
    {
        $this->authorize('unarchive', $report);

        $this->unarchiveAction->execute($report);

        return redirect()->back()->with('success', 'SOW unarchived.');
    }

    public function downloadPdf(SowReport $report, GenerateSowPdfAction $generatePdfAction): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('download', $report);

        $revision = $report->pdfRevisions()->first();
        $path = $revision?->storage_path;
        $disk = Storage::disk('private');

        if ($path === null || ! $disk->exists($path)) {
            if ($report->pdf_generation_status === SowPdfGenerationStatus::GENERATING) {
                return redirect()->back()->with('info', 'PDF is currently generating. Please try again shortly.');
            }

            // Fallback generation if no PDF exists yet
            $path = $generatePdfAction->execute($report, $this->user());
        }

        $slug = Str::slug($report->title ?: 'sow_report');
        $rev = max(1, (int) $report->revision);
        $filename = "{$slug}_R{$rev}.pdf";

        return response()->file($disk->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function regeneratePdf(SowReport $report): RedirectResponse
    {
        $this->authorize('regenerate', $report);

        if ($report->pdf_generation_status === SowPdfGenerationStatus::GENERATING) {
            return redirect()->back()->with('info', 'PDF is already generating. You will be notified when ready.');
        }

        // When resubmitted/reopened or regenerating an existing published PDF, advance the revision
        if ($report->pdfRevisions()->exists() || $report->reopened_at !== null) {
            $nextRev = max(1, (int) $report->revision) + 1;
            $report->update([
                'revision' => $nextRev,
                'revision_date' => today(),
                'reopened_at' => null,
            ]);
        }

        $report->update(['pdf_generation_status' => SowPdfGenerationStatus::GENERATING]);
        GenerateSowPdfJob::dispatch($report, $this->user()->id);

        return redirect()->back()->with('success', 'PDF generation queued. You will be notified when ready.');
    }

    public function attachProject(AttachSowToProjectRequest $request, SowReport $report): RedirectResponse
    {
        $this->attachAction->execute($report, new AttachSowToProjectDTO(
            sowReportId: $report->id,
            projectId: $request->integer('project_id'),
        ));

        return redirect()->back()->with('success', 'SOW linked to project.');
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(SowReport $report): array
    {
        $user = $this->user();

        return [
            'update' => $user->can('update', $report),
            'submit' => $user->can('submit', $report),
            'reopen' => $user->can('reopen', $report),
            'archive' => $user->can('archive', $report),
            'unarchive' => $user->can('unarchive', $report),
            'attach' => $user->can('attach', $report),
            'delete' => $user->can('delete', $report),
            'publish' => $user->can('publish', $report),
            'download' => $user->can('download', $report),
            'regenerate' => $user->can('regenerate', $report),
        ];
    }
}
