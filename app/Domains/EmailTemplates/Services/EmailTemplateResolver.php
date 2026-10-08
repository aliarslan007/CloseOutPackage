<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Services;

use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\EmailTemplates\Queries\EmailTemplateQuery;
use App\Domains\Projects\Models\Project;

/**
 * Resolves {{token}} placeholders in template subject/body against real
 * context. Plain str_replace interpolation — no eval, no Blade, no HTML.
 *
 * Unresolvable tokens are left verbatim (e.g. {{stage_name}} when the caller
 * supplies no stage) so a missing value never crashes or blanks the email.
 */
final class EmailTemplateResolver
{
    public function __construct(private readonly ?EmailTemplateQuery $query = null) {}

    /**
     * Resolves the active default template for the given email type, if one exists.
     * If no default template exists, returns the input DTO unchanged (safe fallback).
     *
     * @param  array<string, string|null>  $context
     */
    public function resolveDefault(SendEmailDTO $dto, EmailType $type, array $context = []): SendEmailDTO
    {
        $query = $this->query ?? app(EmailTemplateQuery::class);
        $template = $query->defaultForType($type);

        if ($template === null) {
            return $dto;
        }

        return $this->resolve($dto, $template, null, null, $context);
    }

    /**
     * Resolve tokens in the template (or the UI "edit before send" overrides)
     * and return a NEW SendEmailDTO — the input is never mutated, mirroring
     * the existing withAttachment() immutability pattern.
     *
     * @param  array<string, string|null>  $context  call-site values for
     *                                               type-specific tokens, e.g. ['revision_label' => $revision->revision_label]
     */
    public function resolve(
        SendEmailDTO $dto,
        EmailTemplate $template,
        ?string $subjectOverride = null,
        ?string $bodyOverride = null,
        array $context = [],
    ): SendEmailDTO {
        $vars = $this->vars(
            $this->projectFor($dto->projectId),
            $dto->toName,
            $context + ['package_link' => $dto->actionUrl],
        );

        return new SendEmailDTO(
            to: $dto->to,
            toName: $dto->toName,
            cc: $dto->cc,
            subject: $this->interpolate($subjectOverride !== null && $subjectOverride !== ''
                ? $subjectOverride : $template->subject_template, $vars),
            type: $dto->type,
            body: $this->interpolate($bodyOverride !== null && $bodyOverride !== ''
                ? $bodyOverride : $template->body_template, $vars),
            closeoutPackageId: $dto->closeoutPackageId,
            projectId: $dto->projectId,
            contextId: $dto->contextId,
            attachmentPath: $dto->attachmentPath,
            attachmentUrl: $dto->attachmentUrl,
            actionUrl: $dto->actionUrl,
            actionLabel: $dto->actionLabel,
            managerNote: $dto->managerNote,
            forwardedByName: $dto->forwardedByName,
            forwardedByRole: $dto->forwardedByRole,
        );
    }

    /**
     * Side-effect-free resolution for the admin preview endpoint. Works for
     * unsaved drafts (raw strings) and saved templates alike. When a project
     * id is given, project-level tokens resolve against real data; type
     * specific tokens only resolve from $context.
     *
     * @param  array<string, string|null>  $context
     * @return array{subject: string, body: string}
     */
    public function preview(string $subjectTemplate, string $bodyTemplate, ?int $projectId = null, array $context = []): array
    {
        $vars = $this->vars($this->projectFor($projectId), null, $context);

        return [
            'subject' => $this->interpolate($subjectTemplate, $vars),
            'body' => $this->interpolate($bodyTemplate, $vars),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function vars(?Project $project, ?string $recipientName, array $context): array
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $normalized[$key] = (string) $value;
            }
        }

        $vars = array_merge([
            'site_name' => $normalized['site_name'] ?? $project?->name,
            'market_name' => $normalized['market_name'] ?? $project?->market?->name,
            'ps_number' => $normalized['ps_number'] ?? $project?->ps_number,
            'manager_name' => $normalized['manager_name'] ?? $project?->manager?->name,
            'recipient_name' => $normalized['recipient_name'] ?? $recipientName,
            'vzw_engineer_name' => $normalized['vzw_engineer_name'] ?? $project?->vzw_engineer_name,
            'current_date' => $normalized['current_date'] ?? now()->format('M j, Y'),
            'uploaded_at' => $normalized['uploaded_at'] ?? now()->format('M j, Y \a\t g:i A'),
        ], $normalized);

        // A token with no value is left verbatim in the template rather than
        // blanked, so a missing project never silently produces an empty line.
        return array_filter(
            $vars,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        );
    }

    private function projectFor(?int $projectId): ?Project
    {
        return $projectId !== null
            ? Project::query()->with(['market', 'manager'])->find($projectId)
            : null;
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function interpolate(string $template, array $vars): string
    {
        $search = array_map(static fn (string $name): string => '{{'.$name.'}}', array_keys($vars));

        return str_replace($search, array_values($vars), $template);
    }
}
