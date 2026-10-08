<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeOrderRecipient>
 */
class ChangeOrderRecipientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChangeOrderRecipient>
     */
    protected $model = ChangeOrderRecipient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = ChangeOrderRecipient::generateToken();

        return [
            'change_order_id' => ChangeOrder::factory(),
            'user_id' => User::factory(),
            'token_hash' => ChangeOrderRecipient::hashToken($token),
            'token_expires_at' => now()->addDays(ChangeOrderRecipient::TOKEN_TTL_DAYS),
            'responded_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'token_expires_at' => now()->subDay(),
        ]);
    }
}
