<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\MediaLibrary\Actions\CopySitePhotoToLibraryAction;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\DTOs\CreateSowReportDTO;
use App\Domains\ScopeOfWork\Enums\CompositeStatus;
use App\Domains\ScopeOfWork\Enums\SowPdfGenerationStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Notifications\SowReportCreatedNotification;
use App\Domains\ScopeOfWork\Services\SowNotificationRecipientResolver;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\MarketAccessDeniedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateSowReportAction
{
    public function __construct(
        private readonly CopySitePhotoToLibraryAction $copySitePhoto,
        private readonly SowNotificationRecipientResolver $recipientResolver,
    ) {}

    public function execute(CreateSowReportDTO $dto, User $creator): SowReport
    {
        $report = DB::transaction(function () use ($dto, $creator): SowReport {
            $project = $dto->projectId !== null ? Project::query()->find($dto->projectId) : null;

            if ($project !== null && $project->market_id !== $dto->marketId) {
                throw new MarketAccessDeniedException('The selected project belongs to a different market.');
            }

            if ($project === null && blank($dto->siteName)) {
                throw ValidationException::withMessages([
                    'site_name' => 'A site name is required for standalone (project-less) SOWs.',
                ]);
            }

            $report = SowReport::create($this->payload($dto, $project, $creator));

            $this->attachCoverPhoto($report, $dto, $creator, $project);

            return $report;
        });

        if ($creator->hasRole(Role::FIELD_SUPERVISOR->value)) {
            $managers = $this->recipientResolver->resolveManagersFor($report, $creator);
            foreach ($managers as $manager) {
                $manager->notify(new SowReportCreatedNotification($report, $creator));
            }
        }

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CreateSowReportDTO $dto, ?Project $project, User $creator): array
    {
        // §4.3 step 2 — auto-prefill any missing fields from the linked project.
        $data = [
            'project_id' => $dto->projectId,
            'market_id' => $dto->marketId,
            'created_by' => $creator->id,
            'title' => $dto->title,
            'site_name' => $dto->siteName ?? $project?->name,
            'site_address' => $dto->siteAddress ?? $project?->address,
            'site_city' => $dto->siteCity ?? $project?->city,
            'site_county' => $dto->siteCounty ?? $project?->county,
            'latitude' => $dto->latitude ?? $project?->latitude,
            'longitude' => $dto->longitude ?? $project?->longitude,
            'ps_number' => $dto->psNumber ?? $project?->ps_number,
            'project_description' => $dto->projectDescription ?? $project?->description,
            'status' => SowStatus::DRAFT,
            'pdf_generation_status' => SowPdfGenerationStatus::IDLE,
            'revision' => 1,
            'revision_date' => now()->toDateString(),
        ];

        foreach ([
            'report_date' => $dto->reportDate,
            'fiber_provider' => $dto->fiberProvider,
            'field_survey_date' => $dto->fieldSurveyDate,
            'provider_contact_name' => $dto->providerContactName,
            'provider_contact_phone' => $dto->providerContactPhone,
            'provider_contact_email' => $dto->providerContactEmail,
            'demarcation' => $dto->demarcation,
            'survey_contractor_name' => $dto->surveyContractorName,
            'survey_contact_name' => $dto->surveyContactName,
            'survey_contact_phone' => $dto->surveyContactPhone,
            'survey_contact_email' => $dto->surveyContactEmail,
            'scope_of_work_description' => $dto->scopeOfWorkDescription,
            'site_access_notes' => $dto->siteAccessNotes,
        ] as $column => $value) {
            if ($value !== null) {
                $data[$column] = $value;
            }
        }

        return $data;
    }

    /**
     * Attach the cover photo as the first annotated image (is_cover +
     * include_in_pdf, so it lands in the PDF). An explicit library pick
     * replaces the auto-pulled site photo; otherwise the site photo is
     * copied in when the caller opted in. Photo problems never fail the
     * report itself — the user can import photos after creation.
     */
    private function attachCoverPhoto(
        SowReport $report,
        CreateSowReportDTO $dto,
        User $creator,
        ?Project $project,
    ): void {
        $coverAsset = null;

        if ($dto->coverMediaAssetId !== null) {
            $coverAsset = MediaAsset::query()->find($dto->coverMediaAssetId);

            if ($coverAsset === null) {
                throw new InvalidStateTransitionException('The selected cover photo could not be found.');
            }

            if ($coverAsset->market_id !== null && $coverAsset->market_id !== $report->market_id) {
                throw new MarketAccessDeniedException('The selected cover photo belongs to a different market than this SOW.');
            }

            if ($coverAsset->status !== MediaAssetStatus::READY) {
                throw ValidationException::withMessages([
                    'cover_media_asset_id' => 'The selected cover photo is still processing. Try again shortly.',
                ]);
            }
        } elseif ($project !== null && $dto->includeProjectSitePhoto === true) {
            try {
                $coverAsset = $this->copySitePhoto->execute($project, $creator, $report->market_id);
            } catch (Throwable $exception) {
                Log::warning('SOW auto-attached site photo skipped.', [
                    'report_id' => $report->id,
                    'project_id' => $project->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($coverAsset === null) {
            return;
        }

        SowAnnotatedImage::create([
            'sow_report_id' => $report->id,
            'media_asset_id' => $coverAsset->id,
            'annotation_layer' => [],
            'composite_status' => CompositeStatus::NOT_NEEDED,
            'sort_order' => 1,
            'is_cover' => true,
            'include_in_pdf' => true,
        ]);

        $report->update(['cover_media_asset_id' => $coverAsset->id]);
    }
}
