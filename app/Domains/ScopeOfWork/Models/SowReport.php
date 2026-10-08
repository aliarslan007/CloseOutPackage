<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Models;

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Enums\SowPdfGenerationStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SowReport extends Model
{
    use SoftDeletes;

    /**
     * §9.20 hard cap — 50 annotated images per SOW report. Raise here if the client
     * wants the full 100-200 field-survey range; PDF page guard (§9.20) stays at 100 pages.
     */
    public const MAX_IMAGES_PER_REPORT = 20;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id', 'market_id', 'created_by', 'title',
        'site_name', 'report_date', 'fiber_provider', 'cover_media_asset_id',
        'site_address', 'site_city', 'site_county', 'latitude', 'longitude', 'project_description',
        'field_survey_date', 'provider_contact_name', 'provider_contact_phone', 'provider_contact_email',
        'demarcation', 'ps_number', 'survey_contractor_name', 'survey_contact_name', 'survey_contact_phone', 'survey_contact_email',
        'scope_of_work_description', 'site_access_notes',
        'status', 'pdf_generation_status', 'revision', 'revision_date', 'submitted_at', 'published_at', 'published_by',
        'reopened_at', 'reopened_by', 'reopen_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SowStatus::class,
            'pdf_generation_status' => SowPdfGenerationStatus::class,
            'report_date' => 'date:Y-m-d',
            'field_survey_date' => 'date:Y-m-d',
            'revision' => 'integer',
            'revision_date' => 'date:Y-m-d',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function isStandalone(): bool
    {
        return $this->project_id === null;
    }

    /**
     * @return HasMany<SowAnnotatedImage, $this>
     */
    public function annotatedImages(): HasMany
    {
        return $this->hasMany(SowAnnotatedImage::class)->orderBy('sort_order');
    }

    /**
     * Named photo groupings that drive the PDF's photo sections.
     *
     * @return HasMany<SowImageGroup, $this>
     */
    public function imageGroups(): HasMany
    {
        return $this->hasMany(SowImageGroup::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_asset_id');
    }

    /**
     * @return HasMany<SowChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(SowChecklistItem::class, 'sow_report_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<SowChecklistResponse, $this>
     */
    public function checklistResponses(): HasMany
    {
        return $this->hasMany(SowChecklistResponse::class);
    }

    /**
     * Survey checklist projected with each item's response for the current report.
     *
     * @return list<array{id: int, label: string, is_required: bool, has_explain_field: bool, sort_order: int, response: string|null, explanation: string|null}>
     */
    public function checklist(): array
    {
        return array_values($this->checklistItems()
            ->with('response')
            ->get()
            ->map(function (SowChecklistItem $item): array {
                $response = $item->response;

                return [
                    'id' => $item->id,
                    'label' => $item->label,
                    'is_required' => $item->is_required,
                    'has_explain_field' => $item->has_explain_field,
                    'sort_order' => $item->sort_order,
                    'response' => $response?->response,
                    'explanation' => $response?->explanation,
                ];
            })
            ->all());
    }

    /**
     * @return HasMany<SowPdfRevision, $this>
     */
    public function pdfRevisions(): HasMany
    {
        return $this->hasMany(SowPdfRevision::class)->orderByDesc('revision_number');
    }

    /**
     * @return BelongsTo<Project, $this> — null for standalone SOWs
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
