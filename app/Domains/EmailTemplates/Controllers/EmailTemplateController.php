<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Controllers;

use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Actions\CreateEmailTemplateAction;
use App\Domains\EmailTemplates\Actions\DeleteEmailTemplateAction;
use App\Domains\EmailTemplates\Actions\DuplicateEmailTemplateAction;
use App\Domains\EmailTemplates\Actions\SetDefaultEmailTemplateAction;
use App\Domains\EmailTemplates\Actions\UpdateEmailTemplateAction;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\EmailTemplates\Queries\EmailTemplateQuery;
use App\Domains\EmailTemplates\Requests\StoreEmailTemplateRequest;
use App\Domains\EmailTemplates\Requests\UpdateEmailTemplateRequest;
use App\Domains\EmailTemplates\Services\EmailTemplateDefaultDefinitions;
use App\Domains\EmailTemplates\Services\EmailTemplateVariableRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly EmailTemplateQuery $query,
        private readonly EmailTemplateVariableRegistry $registry,
        private readonly CreateEmailTemplateAction $create,
        private readonly UpdateEmailTemplateAction $update,
        private readonly DeleteEmailTemplateAction $delete,
        private readonly SetDefaultEmailTemplateAction $setDefault,
        private readonly DuplicateEmailTemplateAction $duplicate,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $type = EmailType::tryFrom((string) $request->query('type'));

        return Inertia::render('Admin/EmailTemplates/Index', [
            'templates' => $this->query->paginated(
                search: is_string($search) && $search !== '' ? $search : null,
                type: $type,
            ),
            'emailTypes' => $this->emailTypeOptions(),
            // Echoed back so the filter inputs survive full page reloads.
            'filters' => [
                'search' => is_string($search) && $search !== '' ? $search : null,
                'type' => $type?->value,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', EmailTemplate::class);

        return Inertia::render('Admin/EmailTemplates/Show', [
            'template' => null,
            ...$this->editorProps(),
        ]);
    }

    public function store(StoreEmailTemplateRequest $request): RedirectResponse
    {
        $template = $this->create->execute($request->toDTO(), $this->user());

        return redirect()
            ->route('admin.email-templates.show', $template)
            ->with('success', 'Email template created.');
    }

    public function show(EmailTemplate $emailTemplate): Response
    {
        $this->authorize('view', $emailTemplate);

        return Inertia::render('Admin/EmailTemplates/Show', [
            'template' => $emailTemplate,
            ...$this->editorProps(),
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->update->execute($emailTemplate, $request->toDTO());

        return redirect()
            ->route('admin.email-templates.show', $emailTemplate)
            ->with('success', 'Email template updated.');
    }

    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('delete', $emailTemplate);

        $this->delete->execute($emailTemplate);

        return redirect()
            ->route('admin.email-templates.index')
            ->with('success', 'Email template deleted.');
    }

    public function setDefault(EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('setDefault', $emailTemplate);

        $this->setDefault->execute($emailTemplate);

        return redirect()->back()->with('success', 'Default email template updated.');
    }

    public function duplicate(EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('duplicate', $emailTemplate);

        $copy = $this->duplicate->execute($emailTemplate, $this->user());

        return redirect()
            ->route('admin.email-templates.show', $copy)
            ->with('success', 'Email template duplicated.');
    }

    /**
     * Active templates for the send-flow picker (admins and managers).
     */
    public function picker(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmailTemplate::class);

        $type = EmailType::tryFrom((string) $request->query('type'));

        abort_if($type === null, 422, 'Unknown email type.');

        return response()->json([
            'templates' => $this->query
                ->forTypePicker($type)
                ->map(static fn (EmailTemplate $template): array => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'subject_template' => $template->subject_template,
                    'body_template' => $template->body_template,
                    'is_default' => $template->is_default,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function emailTypeOptions(): array
    {
        return array_map(
            static fn (EmailType $type): array => ['value' => $type->value, 'label' => Str::headline($type->value)],
            EmailType::cases(),
        );
    }

    /**
     * @return array{
     *     emailTypes: list<array{value: string, label: string}>,
     *     variables: array<string, list<string>>,
     *     defaultTemplates: array<string, array{name: string, subject: string, body: string}>,
     * }
     */
    private function editorProps(): array
    {
        return [
            'emailTypes' => $this->emailTypeOptions(),
            'variables' => $this->registry->all(),
            'defaultTemplates' => EmailTemplateDefaultDefinitions::all(),
        ];
    }
}
