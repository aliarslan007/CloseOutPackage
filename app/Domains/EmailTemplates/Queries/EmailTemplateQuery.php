<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Queries;

use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class EmailTemplateQuery
{
    /**
     * Active templates for the send-flow picker dropdown.
     *
     * @return Collection<int, EmailTemplate>
     */
    public function forTypePicker(EmailType $type): Collection
    {
        return EmailTemplate::active()->forType($type)->orderBy('name')->get();
    }

    /** The single active default template for a type, or null. */
    public function defaultForType(EmailType $type): ?EmailTemplate
    {
        return EmailTemplate::active()->forType($type)->where('is_default', true)->first();
    }

    /**
     * Admin library index: searchable by name, filterable by type.
     *
     * @return LengthAwarePaginator<int, EmailTemplate>
     */
    public function paginated(?string $search = null, ?EmailType $type = null): LengthAwarePaginator
    {
        return EmailTemplate::query()
            ->with('creator')
            ->when($search !== null && $search !== '', fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%"))
            ->when($type !== null, fn (Builder $q): Builder => $q->forType($type))
            ->latest()
            ->paginate(20);
    }
}
