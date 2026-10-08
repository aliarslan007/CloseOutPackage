<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Models;

use App\Domains\Users\Models\User;
use Database\Factories\ChangeOrderRecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeOrderRecipient extends Model
{
    /** @use HasFactory<ChangeOrderRecipientFactory> */
    use HasFactory;

    public const TOKEN_TTL_DAYS = 30;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'change_order_id',
        'user_id',
        'token_hash',
        'token_expires_at',
        'viewed_at',
        'responded_at',
    ];

    protected static function newFactory(): ChangeOrderRecipientFactory
    {
        return ChangeOrderRecipientFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'viewed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** Generate a fresh URL-safe token, store only its SHA-256 hash. */
    public static function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isTokenValid(string $token): bool
    {
        return $this->canDecide()
            && hash_equals((string) $this->token_hash, self::hashToken($token));
    }

    /**
     * A link is usable once: it must exist, be in date, and not have been
     * answered yet.
     */
    public function canDecide(): bool
    {
        return ! $this->hasExpired() && ! $this->hasResponded();
    }

    public function hasExpired(): bool
    {
        // A null expiry means the token was never minted (or was revoked), so
        // it can never be used.
        return $this->token_expires_at === null || ! $this->token_expires_at->isFuture();
    }

    public function hasResponded(): bool
    {
        return $this->responded_at !== null;
    }

    public function hasViewed(): bool
    {
        return $this->viewed_at !== null;
    }

    /**
     * Records the first time the customer opened the change order. The
     * timestamp is written exactly once and never touched afterwards, so
     * re-opening the link or the portal view cannot alter the audit trail.
     */
    public function markViewed(): void
    {
        if ($this->viewed_at !== null) {
            return;
        }

        $this->update(['viewed_at' => now()]);
    }

    /**
     * Records the response and kills the emailed link in the same write, so a
     * decision can never be submitted twice. The hash stays for audit and to
     * explain to the customer why the link is dead.
     */
    public function recordResponse(): void
    {
        $this->update([
            'responded_at' => now(),
            'token_expires_at' => now(),
        ]);
    }

    /**
     * @return BelongsTo<ChangeOrder, $this>
     */
    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(ChangeOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
