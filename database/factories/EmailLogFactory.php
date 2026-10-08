<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EmailLog>
     */
    protected $model = EmailLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'closeout_package_id' => null,
            'change_order_id' => null,
            'recipient_email' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'sender_email' => 'noreply@example.com',
            'sender_name' => 'COP System',
            'cc_emails' => [],
            'subject' => 'Test subject',
            'body_preview' => fake()->sentence(),
            'type' => EmailType::GENERAL,
            'status' => EmailStatus::SENT,
            'sent_at' => now(),
            'scheduled_at' => null,
            'cancelled_at' => null,
            'created_by' => null,
            'context_id' => null,
            'payload' => null,
            'failed_reason' => null,
            'idempotency_key' => null,
        ];
    }

    /**
     * An outbox intent awaiting a manager decision.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmailStatus::READY,
            'sent_at' => null,
        ]);
    }

    /**
     * An outbox intent released for a future send.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmailStatus::SCHEDULED,
            'sent_at' => null,
            'scheduled_at' => now()->addHour(),
        ]);
    }
}
