<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Database\Factories\CustomerProjectAccessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProjectAccess extends Model
{
    /** @use HasFactory<CustomerProjectAccessFactory> */
    use HasFactory;

    protected $table = 'customer_project_access';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'project_id',
        'granted_by',
        'expires_at',
    ];

    protected static function newFactory(): CustomerProjectAccessFactory
    {
        return CustomerProjectAccessFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
