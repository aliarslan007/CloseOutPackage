<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models;

use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Concerns\HasProjectAccessors;
use App\Domains\Projects\Models\Concerns\HasProjectRelationships;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Carbon\Carbon;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Project extends Model implements HasMedia
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasObfuscatedRouteKey, HasProjectAccessors, HasProjectRelationships, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const SITE_PHOTO_COLLECTION = 'site_photo';

    /** REC documents issued by the customer company (Verizon, AT&T, ...). */
    public const REC_DOCUMENTS_COLLECTION = 'rec_documents';

    /** FFR / SOW documents tied to the project scope of work. */
    public const FFR_SOW_DOCUMENTS_COLLECTION = 'ffr_sow_documents';

    /** Compliance documents supplied by third parties (tower co, landlords' agents, ...). */
    public const THIRD_PARTY_COMPLIANCE_COLLECTION = 'third_party_compliance_documents';

    /** Compliance documents issued by the landlord. */
    public const LANDLORD_COMPLIANCE_COLLECTION = 'landlord_compliance_documents';

    /** Change-order (CD) documents. */
    public const CD_DOCUMENTS_COLLECTION = 'cd_documents';

    /** Project-related email threads exported to file. */
    public const PROJECT_EMAIL_DOCUMENTS_COLLECTION = 'project_email_documents';

    /**
     * Project document collections mapped to their route slug — the single
     * source of truth shared by ProjectDocumentController, ProjectQuery and the
     * Project Documents Hub UI. REC keeps its own dedicated controller/routes.
     *
     * @var array<string, string>
     */
    public const DOCUMENT_COLLECTIONS = [
        self::REC_DOCUMENTS_COLLECTION => 'rec',
        self::FFR_SOW_DOCUMENTS_COLLECTION => 'ffr-sow',
        self::THIRD_PARTY_COMPLIANCE_COLLECTION => 'third-party-compliance',
        self::LANDLORD_COMPLIANCE_COLLECTION => 'landlord-compliance',
        self::CD_DOCUMENTS_COLLECTION => 'cd',
        self::PROJECT_EMAIL_DOCUMENTS_COLLECTION => 'project-emails',
    ];

    /** Days without package or photo activity before a project is considered stalled. */
    public const STALLED_ACTIVITY_DAYS = 30;

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * Virtual attributes appended to every serialized Project model.
     *
     * @var list<string>
     */
    protected $appends = [
        'vzw_engineer_name',
        'vzw_engineer_email',
        'vzw_engineer_phone',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'market_id',
        'customer_id',
        'customer_engineer_id',
        'name',
        'ps_number',
        'project_number',
        'address',
        'city',
        'state',
        'zip',
        'site_address',
        'site_access_notes',
        'site_access_directions',
        'vzw_engineer_name',
        'vzw_engineer_email',
        'vzw_engineer_phone',
        'status',
        'progress_percentage',
        'manager_id',
        'primary_supervisor_id',
        'created_by',
        'completed_date',
        'awarded_date',
        'rec_issued_date',
        'construction_started_date',
        'site_completed_date',
        'closeout_package_date',
        'notes',
    ];

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'progress_percentage' => 'integer',
            'primary_supervisor_id' => 'integer',
            'completed_date' => 'date',
            'awarded_date' => 'date',
            'rec_issued_date' => 'date',
            'construction_started_date' => 'date',
            'site_completed_date' => 'date',
            'closeout_package_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'progress_percentage', 'manager_id', 'market_id', 'ps_number', 'project_number'])
            ->useLogName(self::$logName);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::SITE_PHOTO_COLLECTION)->singleFile();

        // Multiple on purpose: RECs get re-issued and amended, and the paper
        // trail matters — replacing an REC should not silently destroy the old one.
        $this->addMediaCollection(self::REC_DOCUMENTS_COLLECTION);

        // The remaining Project Documents Hub categories, all multi-file for the
        // same reason: superseding a document must not erase the earlier copy.
        $this->addMediaCollection(self::FFR_SOW_DOCUMENTS_COLLECTION);
        $this->addMediaCollection(self::THIRD_PARTY_COMPLIANCE_COLLECTION);
        $this->addMediaCollection(self::LANDLORD_COMPLIANCE_COLLECTION);
        $this->addMediaCollection(self::CD_DOCUMENTS_COLLECTION);
        $this->addMediaCollection(self::PROJECT_EMAIL_DOCUMENTS_COLLECTION);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(1000)
            ->height(1000)
            ->sharpen(10);

        $this->addMediaConversion('medium')
            ->width(2000)
            ->height(1500);
    }

    /**
     * Scope to staffed projects whose work has gone quiet: active (not
     * completed or archived), below 100% progress, with at least one package
     * ever created but no package or photo activity within the stalled window.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeStalled(Builder $query, ?Carbon $cutoff = null): Builder
    {
        $cutoff ??= now()->subDays(self::STALLED_ACTIVITY_DAYS);

        return $query
            ->whereNotNull('manager_id')
            ->whereHas('fieldSupervisors')
            ->whereNotIn('status', [ProjectStatus::COMPLETED->value, ProjectStatus::ARCHIVED->value])
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('progress_percentage')
                ->orWhere('progress_percentage', '<', 100))
            ->whereHas('packages')
            ->whereDoesntHave('packages', fn (Builder $q): Builder => $q->where('updated_at', '>=', $cutoff))
            ->whereDoesntHave('packages.photos', fn (Builder $q): Builder => $q->where('created_at', '>=', $cutoff));
    }
}
