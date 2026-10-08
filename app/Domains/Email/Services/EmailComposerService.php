<?php

declare(strict_types=1);

namespace App\Domains\Email\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Services\EmailTemplateResolver;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\PdfGeneration\Services\PackagePdfFilename;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Users\Models\User;

final class EmailComposerService
{
    public function __construct(
        private readonly EmailSubjectBuilderService $subjectBuilder,
        private readonly ?EmailTemplateResolver $resolver = null,
    ) {}

    /**
     * @param  array<string, string|null>  $context
     */
    private function resolve(SendEmailDTO $dto, EmailType $type, array $context = []): SendEmailDTO
    {
        $resolver = $this->resolver ?? app(EmailTemplateResolver::class);

        return $resolver->resolveDefault($dto, $type, $context);
    }

    public function composeStageUpload(PackageStage $stage): ?SendEmailDTO
    {
        $package = $stage->package()->with(['project.manager', 'project.market'])->firstOrFail();

        /** @var Project $project */
        $project = $package->project;
        $manager = $project->manager;

        if ($manager === null) {
            return null;
        }

        /** @var StageType $stageType */
        $stageType = $stage->stage;

        $uploadedAt = ($stage->submitted_at ?? $stage->updated_at) ?? now();

        $dto = new SendEmailDTO(
            to: $manager->email,
            toName: $manager->name,
            subject: $this->subjectBuilder->build($project, 'Stage uploaded'),
            type: EmailType::STAGE_UPLOAD,
            body: "The {$stageType->value} stage for {$project->name} was uploaded by {$this->techName($stage)} on {$uploadedAt->format('M j, Y \a\t g:i A')}.",
            closeoutPackageId: $package->id,
            projectId: $project->id,
            contextId: (string) $stage->id,
            actionUrl: route('packages.show', $package->id),
            actionLabel: 'Open Package',
        );

        return $this->resolve($dto, EmailType::STAGE_UPLOAD, [
            'stage_name' => $stageType->value,
            'uploaded_by' => $this->techName($stage),
            'uploaded_at' => $uploadedAt->format('M j, Y \a\t g:i A'),
        ]);
    }

    public function composePackageSubmitted(CloseoutPackage $package, User $submitter): ?SendEmailDTO
    {
        $package->load(['project.manager', 'project.market']);

        /** @var Project|null $project */
        $project = $package->project;

        if ($project === null) {
            return null;
        }

        $manager = $project->manager;

        if ($manager === null) {
            return null;
        }

        $submittedAt = ($package->submitted_at ?? $package->updated_at) ?? now();

        return new SendEmailDTO(
            to: $manager->email,
            toName: $manager->name,
            subject: $this->subjectBuilder->build($project, 'Package submitted for review'),
            type: EmailType::GENERAL,
            body: "The close-out package #{$package->id} for {$project->name} was submitted for review by {$submitter->name} on {$submittedAt->format('M j, Y \\a\\t g:i A')}.",
            closeoutPackageId: $package->id,
            projectId: $project->id,
            contextId: (string) $package->id,
            actionUrl: route('packages.show', $package->id),
            actionLabel: 'Review Package',
        );
    }

    public function composeReplacementRequest(Photo $photo): ?SendEmailDTO
    {
        $photo->load(['uploader', 'package.project.manager', 'package.project.market', 'categorySelection.templatePictureCategory']);

        $package = $photo->package;
        $uploader = $photo->uploader;

        if ($package === null || $uploader === null) {
            return null;
        }

        /** @var Project $project */
        $project = $package->project;
        $manager = $project->manager;
        $category = $photo->categorySelection?->templatePictureCategory;
        $categoryName = $category !== null ? $category->name : 'Unknown category';

        $dto = new SendEmailDTO(
            to: $uploader->email,
            toName: $uploader->name,
            cc: $manager !== null ? [$manager->email] : [],
            subject: $this->subjectBuilder->build($project, 'Photo replacement requested'),
            type: EmailType::REPLACEMENT_REQUEST,
            body: "The photo for '{$categoryName}' on {$project->name} needs to be replaced.",
            closeoutPackageId: $package->id,
            projectId: $project->id,
            contextId: (string) $photo->id,
        );

        return $this->resolve($dto, EmailType::REPLACEMENT_REQUEST);
    }

    public function composeFinalPdfEmail(PackageRevision $revision, string $recipientEmail): SendEmailDTO
    {
        $package = $revision->package()->with(['project.manager', 'project.market', 'project.customerEngineer'])->firstOrFail();

        /** @var Project $project */
        $project = $package->project;

        return new SendEmailDTO(
            to: $recipientEmail,
            toName: $this->vzwEngineerName($project),
            cc: $project->manager !== null ? [$project->manager->email] : [],
            subject: $this->subjectBuilder->build($project, 'Close-out package'),
            type: EmailType::FINAL_PDF_EMAIL,
            body: "The final close-out package {$revision->revision_label} for {$project->name} is ready.",
            closeoutPackageId: $package->id,
            projectId: $project->id,
            contextId: (string) $revision->id,
            attachmentPath: $revision->storage_path,
            attachmentName: PackagePdfFilename::for($package, $revision),
        );
    }

    public function composeProgressPdf(CloseoutPackage $package, PackageRevision $revision, string $recipientEmail): SendEmailDTO
    {
        /** @var Project $project */
        $project = $package->project()->with(['market', 'manager', 'customerEngineer'])->firstOrFail();

        return new SendEmailDTO(
            to: $recipientEmail,
            toName: $this->vzwEngineerName($project),
            cc: $project->manager !== null ? [$project->manager->email] : [],
            subject: $this->subjectBuilder->build($project, 'Progress report'),
            type: EmailType::PROGRESS_PDF,
            body: "A progress report ({$revision->revision_label}) for {$project->name} is attached.",
            closeoutPackageId: $package->id,
            projectId: $project->id,
            contextId: (string) $revision->id,
            attachmentPath: $revision->storage_path,
            attachmentName: PackagePdfFilename::for($package, $revision),
        );
    }

    public function composeCustomerAccessGranted(CustomerProjectAccess $access): ?SendEmailDTO
    {
        $access->load(['user', 'project.market', 'project.manager']);

        /** @var Project $project */
        $project = $access->project;
        $customer = $access->user;

        if ($customer === null) {
            return null;
        }

        $portalUrl = route('portal.projects.show', $project->hash_id ?? $project->id);

        $dto = new SendEmailDTO(
            to: $customer->email,
            toName: $customer->name,
            cc: $project->manager !== null ? [$project->manager->email] : [],
            subject: $this->subjectBuilder->build($project, 'Portal access granted'),
            type: EmailType::PORTAL_ACCESS_GRANTED,
            body: "You have been granted access to {$project->name} in the close-out package portal.",
            projectId: $project->id,
            actionUrl: $portalUrl,
            actionLabel: 'Open Project Portal',
        );

        return $this->resolve($dto, EmailType::PORTAL_ACCESS_GRANTED, [
            'portal_link' => $portalUrl,
        ]);
    }

    public function composeFinalApproval(CloseoutPackage $package, User $recipient): ?SendEmailDTO
    {
        $package->loadMissing(['project.market']);

        /** @var Project|null $project */
        $project = $package->project;

        if ($project === null) {
            return null;
        }

        return new SendEmailDTO(
            to: $recipient->email,
            toName: $recipient->name,
            subject: $this->subjectBuilder->build($project, 'Package final approval'),
            type: EmailType::GENERAL,
            body: "Close-out package #{$package->id} for {$project->name} has received final manager approval.",
            closeoutPackageId: $package->id,
            projectId: $project->id,
        );
    }

    public function composeProjectCreated(Project $project, User $recipient): SendEmailDTO
    {
        $project->loadMissing('market');

        return new SendEmailDTO(
            to: $recipient->email,
            toName: $recipient->name,
            subject: $this->subjectBuilder->build($project, 'New project created'),
            type: EmailType::GENERAL,
            body: "A new project \"{$project->name}\" has been created.",
            projectId: $project->id,
        );
    }

    public function composeIssueOpened(IssueHistory $issue, User $recipient): ?SendEmailDTO
    {
        $issue->loadMissing(['project.market', 'reporter']);

        /** @var Project|null $project */
        $project = $issue->project;

        if ($project === null) {
            return null;
        }

        $reporter = $issue->reporter;
        $reporterName = $reporter?->name ?? 'Unknown user';
        $reporterEmail = $reporter?->email ?? 'unknown';
        $type = $issue->type instanceof \BackedEnum ? $issue->type->value : (string) $issue->type;
        $description = $issue->description ?? '';
        $guidance = $issue->verizon_guidance !== null && $issue->verizon_guidance !== ''
            ? "\n\nVerizon guidance: {$issue->verizon_guidance}"
            : '';

        $body = "{$reporterName} ({$reporterEmail}) reported a {$type} issue on project \"{$project->name}\".\n\nDescription: {$description}{$guidance}";

        return new SendEmailDTO(
            to: $recipient->email,
            toName: $recipient->name,
            subject: $this->subjectBuilder->build($project, 'Issue reported'),
            type: EmailType::GENERAL,
            body: $body,
            projectId: $project->id,
            contextId: (string) $issue->id,
            actionUrl: route('projects.issues.index', $project),
            actionLabel: 'View Issue',
        );
    }

    /**
     * Compose an issue-forward email for one customer recipient.
     *
     * @param  array<int, array{id:int, type:string, description:string, verizon_guidance:?string, resolved_at:?string, created_at:string, reporter_name:?string, comment_count:int}>  $issueRows
     */
    public function composeIssueForward(
        Project $project,
        string $recipientEmail,
        string $recipientName,
        array $issueRows,
        ?string $managerNote,
        User $forwardedBy,
    ): SendEmailDTO {
        $project->loadMissing(['market', 'manager']);

        $count = count($issueRows);
        $plural = $count === 1 ? 'issue' : 'issues';
        $noteSnippet = $managerNote !== null
            ? " — with a note from {$forwardedBy->name}"
            : '';

        $body = "{$forwardedBy->name} has forwarded {$count} project {$plural} for your review{$noteSnippet}.";

        $forwardedByRole = $forwardedBy->hasRole('admin') ? 'admin' : 'manager';

        $actionUrl = route('portal.projects.show', $project->hash_id ?? $project->id);

        $dto = new SendEmailDTO(
            to: $recipientEmail,
            toName: $recipientName,
            cc: $project->manager !== null ? [$project->manager->email] : [],
            subject: $this->subjectBuilder->build($project, 'Project issues forwarded to you'),
            type: EmailType::ISSUE_FORWARD,
            body: $body,
            projectId: $project->id,
            contextId: implode(',', array_column($issueRows, 'id')),
            actionUrl: $actionUrl,
            actionLabel: 'View Issues on Portal',
            managerNote: $managerNote,
            forwardedByName: $forwardedBy->name,
            forwardedByRole: $forwardedByRole,
        );

        return $this->resolve($dto, EmailType::ISSUE_FORWARD, [
            'issue_type' => $issueRows[0]['type'] ?? 'site',
            'issue_count' => (string) $count,
            'manager_note' => $managerNote ?? '',
            'portal_link' => $actionUrl,
        ]);
    }

    public function composeManagerCustomerAccessChange(
        Project $project,
        User $manager,
        User $customer,
        string $action,
        User $recipient,
    ): SendEmailDTO {
        $project->loadMissing('market');
        $actionVerb = $action === 'granted' ? 'granted' : 'revoked';

        $dto = new SendEmailDTO(
            to: $recipient->email,
            toName: $recipient->name,
            subject: $this->subjectBuilder->build($project, "Customer access {$actionVerb} by manager"),
            type: EmailType::GENERAL,
            body: "Manager {$manager->name} ({$manager->email}) has {$actionVerb} customer portal access for {$customer->name} ({$customer->email}) on project \"{$project->name}\".",
            projectId: $project->id,
            actionUrl: route('admin.customers.index'),
            actionLabel: 'View Customer Access',
        );

        return $this->resolve($dto, EmailType::GENERAL, [
            'manager_name' => $manager->name,
            'customer_name' => $customer->name,
            'action' => $actionVerb,
            'project_name' => $project->name,
        ]);
    }

    private function techName(PackageStage $stage): string
    {
        $submittedBy = $stage->submittedBy()->first();

        return $submittedBy instanceof User ? $submittedBy->name : 'a technician';
    }

    private function vzwEngineerName(Project $project): string
    {
        $engineer = $project->customerEngineer;

        if ($engineer !== null && $engineer->name !== '') {
            return $engineer->name;
        }

        return is_string($project->vzw_engineer_name) && $project->vzw_engineer_name !== ''
            ? $project->vzw_engineer_name
            : 'Verizon Engineer';
    }
}
