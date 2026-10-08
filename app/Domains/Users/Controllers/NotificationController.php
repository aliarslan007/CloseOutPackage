<?php

declare(strict_types=1);

namespace App\Domains\Users\Controllers;

use App\Domains\Approval\Notifications\PackageFinalApprovalNotification;
use App\Domains\Approval\Notifications\PhotoReplacementRequestedNotification;
use App\Domains\ChangeOrders\Notifications\ChangeOrderDecidedNotification;
use App\Domains\ChangeOrders\Notifications\ChangeOrderSentNotification;
use App\Domains\Chat\Notifications\ChatMessageReceivedNotification;
use App\Domains\CloseoutPackages\Notifications\StageUploadedNotification;
use App\Domains\CustomerPortal\Notifications\CustomerPortalAccessNotification;
use App\Domains\CustomerPortal\Notifications\ManagerCustomerAccessNotification;
use App\Domains\EntityFeedback\Notifications\FeedbackForwardedToTechnicianNotification;
use App\Domains\EntityFeedback\Notifications\FeedbackRevisionApprovedNotification;
use App\Domains\EntityFeedback\Notifications\TechnicianRevisionsSubmittedNotification;
use App\Domains\PdfGeneration\Notifications\PdfReadyNotification;
use App\Domains\Projects\Notifications\ProjectCreatedNotification;
use App\Domains\ScopeOfWork\Notifications\SowPdfReadyNotification;
use App\Domains\ScopeOfWork\Notifications\SowReportCreatedNotification;
use App\Domains\ScopeOfWork\Notifications\SowReportPublishedNotification;
use App\Domains\ScopeOfWork\Notifications\SowReportReopenedNotification;
use App\Domains\ScopeOfWork\Notifications\SowReportSubmittedNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Legacy fallback titles for notifications persisted before they became
     * self-describing. Newer rows carry their own 'title' inside the payload,
     * so this map should only shrink over time.
     */
    private const TITLES = [
        ChatMessageReceivedNotification::class => 'New chat message',
        PackageFinalApprovalNotification::class => 'Package approved',
        PhotoReplacementRequestedNotification::class => 'Replacement requested',
        PdfReadyNotification::class => 'PDF ready',
        ProjectCreatedNotification::class => 'New project created',
        StageUploadedNotification::class => 'Stage submitted',
        CustomerPortalAccessNotification::class => 'Portal access granted',
        ManagerCustomerAccessNotification::class => 'Customer access updated',
        ChangeOrderSentNotification::class => 'Change order submitted for review',
        ChangeOrderDecidedNotification::class => 'Change order decision recorded',
    ];

    private const CATEGORIES = [
        ChatMessageReceivedNotification::class => 'chat',
        PdfReadyNotification::class => 'pdf',
        ChangeOrderSentNotification::class => 'change_order',
        ChangeOrderDecidedNotification::class => 'change_order',
        SowReportCreatedNotification::class => 'sow',
        SowReportSubmittedNotification::class => 'sow',
        SowReportReopenedNotification::class => 'sow',
        SowReportPublishedNotification::class => 'sow',
        SowPdfReadyNotification::class => 'pdf',
        ManagerCustomerAccessNotification::class => 'customer_access',
        FeedbackForwardedToTechnicianNotification::class => 'feedback',
        TechnicianRevisionsSubmittedNotification::class => 'feedback',
        FeedbackRevisionApprovedNotification::class => 'feedback',
    ];

    public function index(): JsonResponse
    {
        $user = $this->user();

        $items = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (DatabaseNotification $notification): array {
                $category = $this->categoryFor($notification->type);

                $item = [
                    'id' => (string) $notification->id,
                    'title' => $this->titleFor($notification),
                    'description' => is_string($notification->data['body'] ?? null)
                        ? $notification->data['body']
                        : '',
                    'createdAt' => $notification->created_at?->toISOString(),
                    'read' => $notification->read_at !== null,
                    'category' => $category,
                ];

                if ($category === 'pdf') {
                    $item['package_id'] = is_numeric($notification->data['package_id'] ?? null)
                        ? (int) $notification->data['package_id']
                        : null;
                    $item['pdf_type'] = in_array(
                        $notification->data['pdf_type'] ?? null,
                        ['final', 'partial'],
                        true
                    )
                        ? (string) $notification->data['pdf_type']
                        : null;
                    $item['generated_at'] = is_string($notification->data['generated_at'] ?? null)
                        ? $notification->data['generated_at']
                        : null;
                }

                return $item;
            })
            ->values()
            ->all();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }

    public function markRead(): JsonResponse
    {
        $this->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    private function titleFor(DatabaseNotification $notification): string
    {
        $title = $notification->data['title'] ?? null;

        if (is_string($title) && $title !== '') {
            return $title;
        }

        return self::TITLES[$notification->type] ?? 'Notification';
    }

    private function categoryFor(string $type): string
    {
        return self::CATEGORIES[$type] ?? 'other';
    }
}
