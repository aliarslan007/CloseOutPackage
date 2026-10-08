<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Actions;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Services\EmailTemplateResolver;

/**
 * Builds the per-recipient customer email for a change order at the moment
 * of delivery: mints a fresh decision token (the raw token is returned once
 * and embedded only into the queued job's in-memory DTO — never persisted),
 * flips the change order to SENT_TO_CUSTOMER with a real sent_at and a
 * decision deadline aligned with the token expiry, and composes the DTO.
 */
final class BuildChangeOrderCustomerEmailAction
{
    public function __construct(
        private readonly EmailTemplateResolver $resolver,
    ) {}

    /**
     * Mint a fresh token for the given recipient and mark delivery on the
     * change order exactly once (the first recipient delivered wins the
     * race; every later call sees sent_at already set and skips).
     */
    public function mintToken(ChangeOrder $changeOrder, ChangeOrderRecipient $recipient): string
    {
        $this->markDelivered($changeOrder);

        $token = ChangeOrderRecipient::generateToken();

        $recipient->update([
            'token_hash' => ChangeOrderRecipient::hashToken($token),
            'token_expires_at' => now()->addDays(ChangeOrderRecipient::TOKEN_TTL_DAYS),
            'responded_at' => null,
        ]);

        // Raw token lives only in the caller's in-memory DTO from here.
        return $token;
    }

    /**
     * Build the email DTO for one recipient. The change order must have been
     * marked delivered first (see mintToken) so the body shows the real
     * sent_at and the link TTL matches the decision deadline.
     */
    public function buildDTO(ChangeOrder $changeOrder, object $recipientUser, string $respondUrl): SendEmailDTO
    {
        $lines = $changeOrder->items
            ->where('is_included', true)
            ->values();

        $table = '';
        foreach ($lines as $index => $item) {
            $vendors = $item->vendor_name;
            $vendor = is_array($vendors) && $vendors !== [] ? implode(', ', $vendors) : '—';
            $amountDisplay = $item->cost_absorbed_by_solucomp
                ? '$0.00 (Cost Absorbed by Solucomp)'
                : '$'.number_format((float) $item->amount, 2);
            $table .= ($index + 1).'. '.str_replace("\n", ' ', $item->description)." ({$vendor}) — {$amountDisplay}\n";
        }

        $fieldDateLine = $changeOrder->change_order_date !== null
            ? "Date of Occurrence: {$changeOrder->change_order_date->format('M j, Y')}\n"
            : '';

        $body = "Hi {$recipientUser->name},\n\n"
            ."A change order has been submitted for your review by {$changeOrder->creator?->name}.\n\n"
            ."Project: {$changeOrder->project->name}\n"
            ."Title: {$changeOrder->title}\n"
            .$fieldDateLine
            ."Submitted: {$changeOrder->sent_at?->format('M j, Y')}\n\n"
            ."Items:\n{$table}\n"
            .'TOTAL: $'.number_format((float) $changeOrder->total_amount, 2)."\n\n"
            ."Review and approve or reject here:\n{$respondUrl}\n\n"
            ."Questions? Reply to this email — {$changeOrder->creator?->name} is CC'd.\n\n"
            .'— SoluComp';

        $dto = new SendEmailDTO(
            to: $recipientUser->email,
            toName: $recipientUser->name,
            cc: $changeOrder->creator?->email !== null ? [$changeOrder->creator->email] : [],
            subject: $this->subject($changeOrder),
            type: EmailType::CHANGE_ORDER,
            body: $body,
            projectId: $changeOrder->project_id,
            contextId: (string) $changeOrder->id,
            actionUrl: $respondUrl,
            actionLabel: 'Review Change Order',
        );

        return $this->resolver->resolveDefault($dto, EmailType::CHANGE_ORDER, [
            'change_order_number' => $changeOrder->hash_id ?? (string) $changeOrder->id,
            'change_order_title' => $changeOrder->title,
            'change_order_date' => $changeOrder->change_order_date?->format('M j, Y') ?? '—',
            'total_amount' => '$'.number_format((float) $changeOrder->total_amount, 2),
            'review_link' => $respondUrl,
        ]);
    }

    public function subject(ChangeOrder $changeOrder): string
    {
        return "Change Order Request — {$changeOrder->project->name} — {$changeOrder->title}";
    }

    /**
     * Exactly-once status flip, decided inside the DB: the first caller sets
     * sent_at/deadline/status and returns true; later callers read the
     * winner's values and get false. Shared by the manager's send intent
     * (SendChangeOrderAction) and the actual delivery (SendChangeOrderEmailJob)
     * so the flip happens exactly once no matter which arrives first. Keeps
     * the customer's deadline equal to the token expiry when delivery runs it.
     *
     * @return bool true when this call performed the flip (the first send)
     */
    public function markDelivered(ChangeOrder $changeOrder): bool
    {
        if ($changeOrder->sent_at !== null) {
            $changeOrder->decision_deadline_at ??= now()->addDays(ChangeOrderRecipient::TOKEN_TTL_DAYS);

            return false;
        }

        $changed = ChangeOrder::query()
            ->whereKey($changeOrder->id)
            ->whereNull('sent_at')
            ->update([
                'status' => ChangeOrderStatus::SENT_TO_CUSTOMER->value,
                'sent_at' => now(),
                'decision_deadline_at' => now()->addDays(ChangeOrderRecipient::TOKEN_TTL_DAYS),
            ]);

        if ($changed === 1) {
            $changeOrder->setRawAttributes($changeOrder->fresh()?->getAttributes() ?? $changeOrder->getAttributes());

            return true;
        }

        // Lost the race: reload the winner's timestamps.
        $changeOrder->refresh();

        return false;
    }
}
