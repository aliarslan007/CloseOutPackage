<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\FavoriteTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoriteTemplate extends Model
{
    /** @use HasFactory<FavoriteTemplateFactory> */
    use HasFactory, HasObfuscatedRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'template_id',
        'name',
        'snapshot',
    ];

    protected static function newFactory(): FavoriteTemplateFactory
    {
        return FavoriteTemplateFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
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
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
