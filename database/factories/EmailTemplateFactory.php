<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Enums\EmailTemplateStatus;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EmailTemplate>
     */
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'email_type' => EmailType::FINAL_PDF_EMAIL,
            'name' => $this->faker->unique()->words(3, true),
            'subject_template' => 'Update for {{site_name}}',
            'body_template' => "Dear {{recipient_name}},\n\nAn update for {{site_name}}.\n\nBest regards,\nSoluComp",
            'status' => EmailTemplateStatus::ACTIVE,
            'is_default' => false,
        ];
    }
}
