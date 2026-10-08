<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\EmailTemplates\Enums\EmailTemplateStatus;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\EmailTemplates\Services\EmailTemplateDefaultDefinitions;
use App\Domains\Users\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultEmailTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $adminId = User::where('email', 'testing@mail.com')->value('id')
            ?? User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->value('id');

        $templates = EmailTemplateDefaultDefinitions::all();

        foreach ($templates as $type => $template) {
            EmailTemplate::updateOrCreate(
                ['email_type' => $type, 'is_default' => true],
                [
                    'name' => $template['name'],
                    'subject_template' => $template['subject'],
                    'body_template' => $template['body'],
                    'status' => EmailTemplateStatus::ACTIVE->value,
                    'created_by' => $adminId,
                ],
            );
        }

        $this->command?->info('Seeded default email templates for '.count($templates).' email types.');
    }
}
