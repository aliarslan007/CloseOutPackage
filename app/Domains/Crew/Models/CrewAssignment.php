<?php

declare(strict_types=1);

namespace App\Domains\Crew\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Database\Factories\CrewAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewAssignment extends Model
{
    /** @use HasFactory<CrewAssignmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'user_id',
        'role_description',
        'stage',
    ];

    protected static function newFactory(): CrewAssignmentFactory
    {
        return CrewAssignmentFactory::new();
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
