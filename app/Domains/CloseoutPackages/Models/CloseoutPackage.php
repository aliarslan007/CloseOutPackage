<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Models;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\Concerns\HasPackageAccessors;
use App\Domains\CloseoutPackages\Models\Concerns\HasPackageRelationships;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\Concerns\HasCustomerFeedback;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\CloseoutPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CloseoutPackage extends Model implements HasMedia
{
    /** @use HasFactory<CloseoutPackageFactory> */
    use HasCustomerFeedback, HasFactory, HasObfuscatedRouteKey, HasPackageAccessors, HasPackageRelationships, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const PACKAGE_SITE_NOTES_COLLECTION = 'package_site_notes';

    /** Maximum images on the package-level site notes; the PDF renders at most this many. */
    public const PACKAGE_SITE_NOTES_IMAGE_LIMIT = 6;

    /**
     * Statuses accessible to customers (the feedback & revision loop extends APPROVED non-destructively).
     *
     * @var list<string>
     */
    public const CUSTOMER_ACCESSIBLE_STATUSES = [
        PackageStatus::APPROVED->value,
        PackageStatus::CUSTOMER_REVIEW->value,
        PackageStatus::FEEDBACK_RECEIVED->value,
        PackageStatus::REVISION_IN_PROGRESS->value,
    ];

    /**
     * Virtual attributes appended to every serialized model.
     * hash_id is appended by HasObfuscatedRouteKey::initializeHasObfuscatedRouteKey().
     *
     * @var list<string>
     */
    protected $appends = [
        'title',
        'revision_number',
        'locked',
    ];

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'template_id',
        'created_by',
        'deleted_by',
        'status',
        'revision_major',
        'revision_minor',
        'revision_date',
        'current_stage',
        'notes',
        'final_pdf_path',
        'final_pdf_generated_at',
        'submitted_at',
        'approved_at',
        'approved_by',
        'locked_at',
        'locked_by',
        'customer_review_opened_at',
        'customer_review_opened_by',
    ];

    protected static function newFactory(): CloseoutPackageFactory
    {
        return CloseoutPackageFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PackageStatus::class,
            'revision_major' => 'integer',
            'revision_minor' => 'integer',
            'current_stage' => StageType::class,
            'revision_date' => 'date',
            'final_pdf_generated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'customer_review_opened_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'current_stage', 'revision_major', 'revision_minor', 'locked_at', 'locked_by', 'customer_review_opened_at', 'customer_review_opened_by'])
            ->useLogName(self::$logName);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PACKAGE_SITE_NOTES_COLLECTION);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Generated inline: note images are uploaded at low volume during
        // drafting and review, and both the checklist grid and the PDF need
        // them deterministically.
        $this->addMediaConversion('thumb')->width(320)->nonQueued();

        $this->addMediaConversion('pdf')->width(1400)->nonQueued();
    }

    public function isApproved(): bool
    {
        /** @var PackageStatus $status */
        $status = $this->status;

        return $status === PackageStatus::APPROVED;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Whether this package was locked by the given user. The locker keeps
     * access even after losing their project assignment.
     */
    public function isLockedBy(User $user): bool
    {
        return $this->locked_by !== null && $this->locked_by === $user->id;
    }

    /**
     * Whether the customer feedback & revision loop is active on this package.
     */
    public function isInCustomerReview(): bool
    {
        /** @var PackageStatus $status */
        $status = $this->status;

        return in_array($status, [
            PackageStatus::CUSTOMER_REVIEW,
            PackageStatus::FEEDBACK_RECEIVED,
            PackageStatus::REVISION_IN_PROGRESS,
        ], true);
    }

    /**
     * Whether a feedback round still asks for work on this package.
     *
     * This is the unlock that keeps a locked package editable during a live
     * review - photo upload, replace, delete, section notes and draft saves all
     * route through here. Once the customer has withdrawn every issue there is
     * nothing left to act on, so the lock holds again and no user can alter an
     * image or section without their consent.
     */
    public function hasPendingFeedback(): bool
    {
        $round = $this->latestActiveFeedbackRound();

        return $round !== null && ! $round->issuesAllWithdrawn();
    }

    public function isApprovedOrInCustomerReview(): bool
    {
        return in_array($this->status, [
            PackageStatus::APPROVED,
            PackageStatus::CUSTOMER_REVIEW,
        ], true);
    }

    public function isApprovedOrInRevision(): bool
    {
        return $this->isApproved() || $this->isInCustomerReview();
    }

    /**
     * Close-out packages carry customer feedback through the same engine as
     * change orders and issues, so packages can be annotated on the image the
     * customer is actually looking at.
     */
    public function feedbackEntityType(): FeedbackEntityType
    {
        return FeedbackEntityType::CLOSEOUT_PACKAGE;
    }
}
