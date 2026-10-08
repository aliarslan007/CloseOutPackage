<?php

use App\Domains\Approval\Controllers\ApprovePhotoController;
use App\Domains\Approval\Controllers\BulkReviewPhotosController;
use App\Domains\Approval\Controllers\FinalApprovePackageController;
use App\Domains\Approval\Controllers\FlagPhotoController;
use App\Domains\Approval\Controllers\RequestPhotoReplacementController;
use App\Domains\ChangeOrders\Controllers\BatchSendChangeOrdersController;
use App\Domains\ChangeOrders\Controllers\ChangeOrderController;
use App\Domains\ChangeOrders\Controllers\ChangeOrderDecisionController;
use App\Domains\ChangeOrders\Controllers\ChangeOrderPhotoController;
use App\Domains\Chat\Controllers\ChatController;
use App\Domains\Chat\Controllers\MarkChatReadController;
use App\Domains\CloseoutPackages\Controllers\ApprovePackageController;
use App\Domains\CloseoutPackages\Controllers\AuthCenterSiteController;
use App\Domains\CloseoutPackages\Controllers\CloseoutPackageController;
use App\Domains\CloseoutPackages\Controllers\GarbagePackageController;
use App\Domains\CloseoutPackages\Controllers\LockPackageController;
use App\Domains\CloseoutPackages\Controllers\PackageChecklistController;
use App\Domains\CloseoutPackages\Controllers\PackageNoteImageController;
use App\Domains\CloseoutPackages\Controllers\PackageNotesController;
use App\Domains\CloseoutPackages\Controllers\PackageSiteInfoController;
use App\Domains\CloseoutPackages\Controllers\PackageStageController;
use App\Domains\CloseoutPackages\Controllers\PurgePackageController;
use App\Domains\CloseoutPackages\Controllers\RejectPackageController;
use App\Domains\CloseoutPackages\Controllers\RestorePackageController;
use App\Domains\CloseoutPackages\Controllers\ResubmitPackageController;
use App\Domains\CloseoutPackages\Controllers\SaveStageDraftController;
use App\Domains\CloseoutPackages\Controllers\SubmitPackageController;
use App\Domains\CloseoutPackages\Controllers\SubmitStageController;
use App\Domains\CloseoutPackages\Controllers\UnlockPackageController;
use App\Domains\Crew\Controllers\CrewController;
use App\Domains\CustomerPortal\Controllers\CustomerAccessController;
use App\Domains\CustomerPortal\Controllers\CustomerPortalController;
use App\Domains\CustomerPortal\Controllers\CustomerPortalPackageController;
use App\Domains\CustomerPortal\Controllers\CustomerPortalPdfController;
use App\Domains\Customers\Controllers\CustomerController;
use App\Domains\Customers\Controllers\CustomerEngineerController;
use App\Domains\Dashboard\Controllers\DashboardController;
use App\Domains\Dashboard\Controllers\DashboardPackagesController;
use App\Domains\Dashboard\Controllers\LiveVisibilityController;
use App\Domains\Email\Controllers\CustomerEmailOutboxController;
use App\Domains\Email\Controllers\EmailLogController;
use App\Domains\Email\Controllers\ManagerEmailLogController;
use App\Domains\EmailTemplates\Controllers\EmailTemplateController;
use App\Domains\EmailTemplates\Controllers\EmailTemplatePreviewController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackAcceptController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackAcknowledgeController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackNoteController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackNoteImageController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackNoteResolveController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackResolveAllController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackRoundController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackRoundDeleteController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackSubmitController;
use App\Domains\EntityFeedback\Controllers\EntityFeedbackWithdrawIssueController;
use App\Domains\EntityFeedback\Controllers\ForwardFeedbackToTechnicianController as EntityForwardFeedbackToTechnicianController;
use App\Domains\EntityFeedback\Controllers\SubmitTechnicianFeedbackController;
use App\Domains\EntityFeedback\Controllers\TokenEntityFeedbackController;
use App\Domains\Help\Controllers\HelpController;
use App\Domains\Markets\Controllers\MarketController;
use App\Domains\Markets\Controllers\MarketUserController;
use App\Domains\MediaLibrary\Controllers\MediaAssetAnnotateController;
use App\Domains\MediaLibrary\Controllers\MediaAssetAssignController;
use App\Domains\MediaLibrary\Controllers\MediaAssetConfigController;
use App\Domains\MediaLibrary\Controllers\MediaAssetController;
use App\Domains\MediaLibrary\Controllers\MediaAssetFileController;
use App\Domains\MediaLibrary\Controllers\MediaAssetPurgeController;
use App\Domains\MediaLibrary\Controllers\MediaAssetUploadController;
use App\Domains\PdfGeneration\Controllers\DownloadPartialPdfController;
use App\Domains\PdfGeneration\Controllers\DownloadPdfController;
use App\Domains\PdfGeneration\Controllers\DownloadRevisionPdfController;
use App\Domains\PdfGeneration\Controllers\EmailFinalPdfController;
use App\Domains\PdfGeneration\Controllers\EmailProgressPdfController;
use App\Domains\PdfGeneration\Controllers\GenerateFinalPdfController;
use App\Domains\PdfGeneration\Controllers\GeneratePartialPdfController;
use App\Domains\PdfGeneration\Controllers\PdfStatusController;
use App\Domains\PdfGeneration\Controllers\SignedPdfDownloadController;
use App\Domains\PdfGeneration\Controllers\StreamPdfTempAssetController;
use App\Domains\Photos\Controllers\GetPhotoSignedUrlController;
use App\Domains\Photos\Controllers\PhotoController;
use App\Domains\Photos\Controllers\ReplacePhotoController;
use App\Domains\Photos\Controllers\UpdatePhotoNotesController;
use App\Domains\Projects\Controllers\ForwardIssuesToCustomerController;
use App\Domains\Projects\Controllers\IssueAttachmentController;
use App\Domains\Projects\Controllers\IssueCommentController;
use App\Domains\Projects\Controllers\IssueHistoryController;
use App\Domains\Projects\Controllers\ProjectController;
use App\Domains\Projects\Controllers\ProjectDocumentController;
use App\Domains\Projects\Controllers\ProjectSitePhotoController;
use App\Domains\Projects\Controllers\ProjectSupervisorController;
use App\Domains\Projects\Controllers\RecDocumentController;
use App\Domains\Projects\Controllers\ResolveIssueController;
use App\Domains\Projects\Controllers\ServeIssueAttachmentController;
use App\Domains\ScopeOfWork\Controllers\SowAnnotatedImageController;
use App\Domains\ScopeOfWork\Controllers\SowChecklistController;
use App\Domains\ScopeOfWork\Controllers\SowChecklistItemController;
use App\Domains\ScopeOfWork\Controllers\SowImageGroupController;
use App\Domains\ScopeOfWork\Controllers\SowReportController;
use App\Domains\Settings\Controllers\AppSettingsController;
use App\Domains\Templates\Controllers\CategoryPresetController;
use App\Domains\Templates\Controllers\ChecklistItemController;
use App\Domains\Templates\Controllers\DuplicateTemplateController;
use App\Domains\Templates\Controllers\FavoriteTemplateController;
use App\Domains\Templates\Controllers\FieldTemplateController;
use App\Domains\Templates\Controllers\ReorderChecklistItemsController;
use App\Domains\Templates\Controllers\ReorderTemplateCategoriesController;
use App\Domains\Templates\Controllers\TemplateAdditionalNoteImageController;
use App\Domains\Templates\Controllers\TemplateAdditionalNotesController;
use App\Domains\Templates\Controllers\TemplateCategoryController;
use App\Domains\Templates\Controllers\TemplateController;
use App\Domains\Templates\Controllers\TemplateSectionController;
use App\Domains\Tracking\Controllers\TrackingController;
use App\Domains\Tracking\Controllers\TrackingOfflineController;
use App\Domains\Tracking\Controllers\TrackingPingController;
use App\Domains\Users\Controllers\Admin\UserManagementController;
use App\Domains\Users\Controllers\Admin\UserProfilePhotoController;
use App\Domains\Users\Controllers\NotificationController;
use App\Domains\Users\Controllers\PasswordController;
use App\Domains\Users\Controllers\ProfileController;
use App\Domains\Users\Controllers\SessionExpiredController;
use App\Domains\Users\Controllers\SsoAuthController;
use App\Domains\Users\Controllers\SsoUserSyncController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('welcome');

Route::get('/login', [SsoAuthController::class, 'redirect'])->name('login');

Route::get('/sso/callback', SsoAuthController::class)->name('sso.callback');

Route::get('/session-expired', SessionExpiredController::class)->name('session.expired');

Route::post('/logout', [SsoAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::post('/api/auth-sync', SsoUserSyncController::class)->name('sso.auth-sync');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::get('/dashboard/packages', DashboardPackagesController::class)
    ->middleware(['auth'])
    ->name('dashboard.packages');

Route::get('/pdf/signed/{package}', SignedPdfDownloadController::class)->middleware('signed')->name('pdf.signed');
Route::get('/pdf-temp-assets/{batch}/{file}', StreamPdfTempAssetController::class)->name('pdf.temp-asset');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    // Any authenticated user may view their own profile photo (sidebar avatar);
    // admins may view any user's photo (admin user table / edit screens).
    Route::get('/users/{user}/profile-photo', [UserProfilePhotoController::class, 'show'])->name('users.profile-photo.show');

    Route::get('/help', HelpController::class)->name('help.index');
});

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('markets', MarketController::class)->except(['show']);
        Route::post('markets/{market}/users', [MarketUserController::class, 'store'])->name('markets.users.store');
        Route::delete('markets/{market}/users/{user}', [MarketUserController::class, 'destroy'])->name('markets.users.destroy');

        Route::resource('users', UserManagementController::class)->except(['show']);
        Route::post('users/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/change-password', [UserManagementController::class, 'changePassword'])->name('users.change-password');

        Route::get('templates/category-presets', [CategoryPresetController::class, 'index'])
            ->name('templates.category-presets.index');
        Route::post('templates/category-presets', [CategoryPresetController::class, 'store'])
            ->name('templates.category-presets.store');
        Route::put('templates/category-presets/{category_preset}', [CategoryPresetController::class, 'update'])
            ->name('templates.category-presets.update');
        Route::delete('templates/category-presets/{category_preset}', [CategoryPresetController::class, 'destroy'])
            ->name('templates.category-presets.destroy');
        Route::post('templates/category-presets/{category_preset}/toggle-active', [CategoryPresetController::class, 'toggleActive'])
            ->name('templates.category-presets.toggle-active');

        // Global app settings writes (mail sending, media quotas). The page
        // itself is in the admin|manager group below — managers may read the
        // customer hierarchy but never write these.
        Route::post('settings/mail', [AppSettingsController::class, 'updateMailSettings'])->name('settings.mail.update');
        Route::post('settings/media-quotas', [AppSettingsController::class, 'updateMediaQuotas'])->name('settings.media-quotas.update');

        Route::resource('templates', TemplateController::class);
        Route::post('templates/{template}/duplicate', DuplicateTemplateController::class)->name('templates.duplicate');
        Route::put('templates/{template}/sections/{section}', [TemplateSectionController::class, 'update'])->name('templates.sections.update');
        Route::post('templates/{template}/sections/{section}/categories', [TemplateCategoryController::class, 'store'])->name('templates.categories.store');
        Route::post('templates/{template}/sections/{section}/categories/from-library', [TemplateCategoryController::class, 'pullFromLibrary'])->name('templates.categories.from-library');
        Route::put('templates/{template}/sections/{section}/categories/{category}', [TemplateCategoryController::class, 'update'])->name('templates.categories.update');
        Route::delete('templates/{template}/sections/{section}/categories/{category}', [TemplateCategoryController::class, 'destroy'])->name('templates.categories.destroy');
        Route::post('templates/{template}/sections/{section}/categories/reorder', ReorderTemplateCategoriesController::class)->name('templates.categories.reorder');
        Route::post('templates/{template}/checklist', [ChecklistItemController::class, 'store'])->name('templates.checklist.store');
        Route::put('templates/{template}/checklist/{item}', [ChecklistItemController::class, 'update'])->name('templates.checklist.update');
        Route::delete('templates/{template}/checklist/{item}', [ChecklistItemController::class, 'destroy'])->name('templates.checklist.destroy');
        Route::post('templates/{template}/checklist/reorder', ReorderChecklistItemsController::class)->name('templates.checklist.reorder');
        Route::put('templates/{template}/additional-notes', [TemplateAdditionalNotesController::class, 'update'])->name('templates.additional-notes.update');
        Route::post('templates/{template}/additional-notes/images', [TemplateAdditionalNoteImageController::class, 'store'])->name('templates.additional-notes.images.store');
        Route::get('templates/{template}/additional-notes/images/{media}', [TemplateAdditionalNoteImageController::class, 'show'])->name('templates.additional-notes.images.show');
        Route::delete('templates/{template}/additional-notes/images/{media}', [TemplateAdditionalNoteImageController::class, 'destroy'])->name('templates.additional-notes.images.destroy');
    });

Route::middleware('auth')->group(function () {
    Route::get('/templates', FieldTemplateController::class)->name('templates.selector');
    Route::get('/templates/{template}/additional-notes/images/{media}', [TemplateAdditionalNoteImageController::class, 'show'])->name('templates.additional-notes.images.show');
});

// Favorites are a field-tech/manager feature; admins have no favorites.
Route::middleware(['auth', 'role:manager|field_supervisor'])->group(function () {
    Route::get('/favorites', [FavoriteTemplateController::class, 'index'])->name('favorites.index');
    Route::post('/favorites', [FavoriteTemplateController::class, 'store'])->name('favorites.store');
    Route::delete('/favorites/{favorite}', [FavoriteTemplateController::class, 'destroy'])->name('favorites.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/projects/{project}/packages', [CloseoutPackageController::class, 'index'])->name('packages.index');
    Route::get('/projects/{project}/packages/create', [CloseoutPackageController::class, 'create'])->name('packages.create');
    Route::post('/projects/{project}/packages', [CloseoutPackageController::class, 'store'])->name('packages.store');
    Route::get('/packages/{package}', [CloseoutPackageController::class, 'show'])->name('packages.show');
    Route::get('/packages/{package}/stages/{stage}', [PackageStageController::class, 'show'])->name('packages.stages.show');
    Route::post('/packages/{package}/stages/{stage}/draft', SaveStageDraftController::class)->name('packages.stages.draft');
    Route::post('/packages/{package}/stages/{stage}/submit', SubmitStageController::class)->name('packages.stages.submit');
    Route::post('/packages/{package}/submit', SubmitPackageController::class)->name('packages.submit');
    Route::post('/packages/{package}/resubmit', ResubmitPackageController::class)->name('packages.resubmit');
    Route::put('/packages/{package}/site-info', [PackageSiteInfoController::class, 'update'])->name('packages.site-info.update');
    Route::put('/packages/{package}/checklist', [PackageChecklistController::class, 'update'])->name('packages.checklist.update');
    Route::put('/packages/{package}/notes', [PackageNotesController::class, 'update'])->name('packages.notes.update');
    Route::post('/packages/{package}/notes-images', [PackageNoteImageController::class, 'store'])->name('packages.notes-images.store');
    Route::post('/packages/{package}/notes-images/library', [PackageNoteImageController::class, 'storeFromLibrary'])->name('packages.notes-images.library');
    Route::get('/packages/{package}/notes-images/{media}', [PackageNoteImageController::class, 'show'])->name('packages.notes-images.show');
    Route::delete('/packages/{package}/notes-images/{media}', [PackageNoteImageController::class, 'destroy'])->name('packages.notes-images.destroy');
    Route::post('/packages/{package}/approve', ApprovePackageController::class)->middleware('role:admin|manager')->name('packages.approve');
    Route::post('/packages/{package}/reject', RejectPackageController::class)->middleware('role:admin|manager')->name('packages.reject');
    Route::post('/packages/{package}/lock', LockPackageController::class)->name('packages.lock');
    Route::post('/packages/{package}/unlock', UnlockPackageController::class)->name('packages.unlock');
    Route::delete('/packages/{package}', [CloseoutPackageController::class, 'destroy'])->name('packages.destroy');

    Route::get('/packages/{package}/stages/{stage}/photos', [PhotoController::class, 'index'])->name('photos.index');
    Route::post('/packages/{package}/stages/{stage}/photos', [PhotoController::class, 'store'])->name('photos.store');
    Route::delete('/packages/{package}/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::post('/packages/{package}/photos/{photo}/replace', ReplacePhotoController::class)->name('photos.replace');
    Route::patch('/packages/{package}/photos/{photo}/notes', UpdatePhotoNotesController::class)->name('photos.notes.update');
    Route::get('/packages/{package}/photos/{photo}/signed-url', GetPhotoSignedUrlController::class)->name('photos.signed-url');
});

Route::middleware(['auth', 'role:admin|manager'])
    ->prefix('manager')
    ->name('manager.')
    ->group(function () {
        Route::post('photos/{photo}/approve', ApprovePhotoController::class)->name('photos.approve');
        Route::post('photos/{photo}/flag', FlagPhotoController::class)->name('photos.flag');
        Route::post('photos/{photo}/request-replacement', RequestPhotoReplacementController::class)->name('photos.request-replacement');
        Route::post('packages/{package}/bulk-review', BulkReviewPhotosController::class)->name('packages.bulk-review');
        Route::post('packages/{package}/final-approve', FinalApprovePackageController::class)->name('packages.final-approve');
        Route::get('packages/auth-sites/filter-options', [AuthCenterSiteController::class, 'filterOptions'])->name('packages.auth-sites.filter-options');
        Route::get('packages/auth-sites', [AuthCenterSiteController::class, 'sites'])->name('packages.auth-sites');
        Route::post('packages/{package}/pdf/final', GenerateFinalPdfController::class)->name('packages.pdf.final');
        Route::post('packages/{package}/pdf/partial', GeneratePartialPdfController::class)->name('packages.pdf.partial');
        Route::post('packages/{package}/pdf/email', EmailFinalPdfController::class)->name('packages.pdf.email');
        Route::post('packages/{package}/pdf/progress-email', EmailProgressPdfController::class)->name('packages.pdf.progress-email');

        Route::get('email-logs', ManagerEmailLogController::class)->name('email-logs.manager');

        // Customer Emails outbox — admins and managers (managers scoped to
        // their projects by EmailLogPolicy, enforced per row on every action).
        Route::get('customer-emails', [CustomerEmailOutboxController::class, 'index'])->name('customer-emails.index');
        Route::post('customer-emails/{email_log}/send', [CustomerEmailOutboxController::class, 'sendNow'])->name('customer-emails.send');
        Route::post('customer-emails/{email_log}/schedule', [CustomerEmailOutboxController::class, 'schedule'])->name('customer-emails.schedule');
        Route::post('customer-emails/{email_log}/cancel', [CustomerEmailOutboxController::class, 'cancel'])->name('customer-emails.cancel');
        Route::post('customer-emails/{email_log}/resend', [CustomerEmailOutboxController::class, 'resend'])->name('customer-emails.resend');
        Route::post('customer-emails/{email_log}/retry', [CustomerEmailOutboxController::class, 'retry'])->name('customer-emails.retry');
        Route::delete('customer-emails', [CustomerEmailOutboxController::class, 'destroyBulk'])->name('customer-emails.destroyBulk');

        Route::get('visibility', [LiveVisibilityController::class, 'index'])->name('visibility');
        Route::get('visibility/projects/{project}', [LiveVisibilityController::class, 'show'])->name('visibility.project');
    });

// Change Orders — create, store, edit, update, delete, send (manager/admin)
Route::middleware(['auth', 'role:admin|manager'])
    ->group(function (): void {
        Route::get('projects/{project}/change-orders/create', [ChangeOrderController::class, 'create'])->name('projects.change-orders.create');
        Route::post('projects/{project}/change-orders', [ChangeOrderController::class, 'store'])->name('projects.change-orders.store');
        Route::get('projects/{project}/change-orders/{change_order}/edit', [ChangeOrderController::class, 'edit'])->name('projects.change-orders.edit');
        Route::put('projects/{project}/change-orders/{change_order}', [ChangeOrderController::class, 'update'])->name('projects.change-orders.update');
        Route::delete('projects/{project}/change-orders/{change_order}', [ChangeOrderController::class, 'destroy'])->name('projects.change-orders.destroy');
        Route::post('projects/{project}/change-orders/{change_order}/send', [ChangeOrderController::class, 'send'])->name('projects.change-orders.send');
        Route::post('projects/{project}/change-orders/{change_order}/photos', [ChangeOrderPhotoController::class, 'store'])->name('projects.change-orders.photos.store');
        Route::delete('projects/{project}/change-orders/{change_order}/photos/{photo}', [ChangeOrderPhotoController::class, 'destroy'])->name('projects.change-orders.photos.destroy');
        Route::post('change-orders/batch-send', BatchSendChangeOrdersController::class)->name('change-orders.batch-send');
        // Two independent sequences: one per project, one across all projects.
        Route::post('projects/{project}/change-orders/reorder', [ChangeOrderController::class, 'reorder'])->name('projects.change-orders.reorder');
        Route::post('change-orders/reorder', [ChangeOrderController::class, 'reorderGlobal'])->name('change-orders.reorder');
    });

// Change Orders — accessible to admin, manager, customer
Route::middleware(['auth', 'role:admin|manager|customer'])
    ->group(function (): void {
        Route::get('change-orders', [ChangeOrderController::class, 'globalIndex'])->name('change-orders.index');
        Route::get('projects/{project}/change-orders', [ChangeOrderController::class, 'index'])->name('projects.change-orders.index');
        Route::get('projects/{project}/change-orders/{change_order}', [ChangeOrderController::class, 'show'])->name('projects.change-orders.show');
    });

// Change Orders — customer decisions (portal, logged in)
Route::middleware(['auth', 'role:customer'])
    ->group(function (): void {
        Route::get('customer/projects/{project}/change-orders/{change_order}', [ChangeOrderDecisionController::class, 'show'])->name('customer.change-orders.show');
        Route::post('customer/projects/{project}/change-orders/{change_order}/decide', [ChangeOrderDecisionController::class, 'decide'])->name('customer.change-orders.decide');
    });

// Change Orders — email token link (no auth, plan decision #3)
Route::get('change-orders/respond/{token}', [ChangeOrderDecisionController::class, 'tokenShow'])->name('change-orders.token.show');
Route::post('change-orders/respond/{token}', [ChangeOrderDecisionController::class, 'tokenDecide'])->name('change-orders.token.decide');

// Change Order photos — one stream endpoint for staff, portal customers and
// no-auth token viewers; the controller decides which of the three applies.
Route::get('change-order-photos/{photo}', [ChangeOrderPhotoController::class, 'stream'])->name('change-orders.photos.stream');

// Cross-domain Entity Feedback (Change Orders, Issues, COP, and future features)
Route::middleware(['auth'])->group(function (): void {
    Route::post('entity-feedback/rounds/open', [EntityFeedbackRoundController::class, 'open'])->name('entity-feedback.rounds.open');
    Route::post('entity-feedback/rounds/{round}/notes', [EntityFeedbackNoteController::class, 'store'])->name('entity-feedback.notes.store');
    Route::patch('entity-feedback/notes/{note}', [EntityFeedbackNoteController::class, 'update'])->name('entity-feedback.notes.update');
    Route::delete('entity-feedback/notes/{note}', [EntityFeedbackNoteController::class, 'destroy'])->name('entity-feedback.notes.destroy');
    Route::get('entity-feedback/notes/{note}/image/{kind}', [EntityFeedbackNoteImageController::class, 'show'])
        ->whereIn('kind', ['annotated', 'replacement'])
        ->name('entity-feedback.notes.image');
    Route::post('entity-feedback/rounds/{round}/submit', EntityFeedbackSubmitController::class)->name('entity-feedback.rounds.submit');
    Route::post('entity-feedback/rounds/{round}/accept', EntityFeedbackAcceptController::class)->name('entity-feedback.rounds.accept');
    Route::post('entity-feedback/rounds/{round}/acknowledge', EntityFeedbackAcknowledgeController::class)
        ->name('entity-feedback.rounds.acknowledge');

    // Customer-only: take one issue off the list, any time before the review is
    // closed. The round and the other issues stay live.
    Route::post('entity-feedback/rounds/{round}/withdraw', EntityFeedbackWithdrawIssueController::class)
        ->name('entity-feedback.rounds.withdraw');

    // Manager & Technician Delegation Workflow
    Route::post('entity-feedback/rounds/{round}/forward-technician', EntityForwardFeedbackToTechnicianController::class)
        ->name('entity-feedback.rounds.forward-technician')
        ->middleware('role:admin|manager');
    Route::post('entity-feedback/rounds/{round}/submit-manager', SubmitTechnicianFeedbackController::class)
        ->name('entity-feedback.rounds.submit-manager');
    Route::patch('entity-feedback/notes/{note}/resolve', EntityFeedbackNoteResolveController::class)
        ->name('entity-feedback.notes.resolve');
    Route::post('entity-feedback/rounds/{round}/resolve-all', EntityFeedbackResolveAllController::class)
        ->name('entity-feedback.rounds.resolve-all')
        ->middleware('role:admin|manager');

    // Admin-only archive cleanup: wipes the round, its messages and their images.
    // The policy re-checks the role, so the middleware is not the only gate.
    Route::delete('entity-feedback/rounds/{round}', EntityFeedbackRoundDeleteController::class)
        ->name('entity-feedback.rounds.delete')
        ->middleware('role:admin');
});

// Change Orders — Guest Token feedback routes
Route::post('change-orders/respond/{token}/feedback/notes', [TokenEntityFeedbackController::class, 'storeNote'])->name('change-orders.token.feedback.notes.store');
Route::post('change-orders/respond/{token}/feedback/{round}/submit', [TokenEntityFeedbackController::class, 'submitRound'])->name('change-orders.token.feedback.submit');
Route::post('change-orders/respond/{token}/feedback/{round}/accept', [TokenEntityFeedbackController::class, 'acceptRound'])->name('change-orders.token.feedback.accept');

// Tracking — field tech pings location (any auth, policy checks project assignment)
Route::middleware('auth')->group(function (): void {
    Route::post('projects/{project}/tracking/ping', TrackingPingController::class)->name('projects.tracking.ping');
    Route::post('projects/{project}/tracking/offline', TrackingOfflineController::class)->name('projects.tracking.offline');
});

// Tracking — manager/admin global overview + per-project page + JSON polling endpoints
Route::middleware(['auth', 'role:admin|manager'])
    ->group(function (): void {
        Route::get('tracking', [TrackingController::class, 'index'])->name('tracking.index');
        Route::get('tracking/pings', [TrackingController::class, 'allPings'])->name('tracking.pings');
        Route::get('projects/{project}/tracking', [TrackingController::class, 'show'])->name('projects.tracking.show');
        Route::get('projects/{project}/tracking/pings', [TrackingController::class, 'pings'])->name('projects.tracking.pings');
    });

// Email templates management (accessible to both admin and manager).
// Picker and preview are registered before the resource so they are not captured by {email_template}.
Route::middleware(['auth', 'role:admin|manager'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('email-templates/picker', [EmailTemplateController::class, 'picker'])
        ->name('email-templates.picker');
    Route::post('email-templates/preview', EmailTemplatePreviewController::class)
        ->name('email-templates.preview');

    // Show doubles as the edit page, hence no separate edit route.
    Route::resource('email-templates', EmailTemplateController::class)->except(['edit']);
    Route::post('email-templates/{email_template}/default', [EmailTemplateController::class, 'setDefault'])
        ->name('email-templates.setDefault');
    Route::post('email-templates/{email_template}/duplicate', [EmailTemplateController::class, 'duplicate'])
        ->name('email-templates.duplicate');
});

Route::middleware(['auth', 'role:admin|manager'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('customers', [CustomerAccessController::class, 'index'])->name('customers.index');
    Route::post('customers/{user}/projects', [CustomerAccessController::class, 'store'])->name('customers.access.store');
    Route::delete('customers/{user}/projects/{project}', [CustomerAccessController::class, 'destroy'])->withTrashed()->name('customers.access.destroy');
});

// Customer hierarchy management, driven from the App Settings console.
// Admins manage every customer and engineer; managers are restricted to the
// regions (markets) assigned to them — enforced by the Customer policies.
Route::middleware(['auth', 'role:admin|manager'])->prefix('admin')->name('admin.')->group(function () {
    // The console itself. Managers get the customer hierarchy only — the
    // mail/quota props are withheld by the controller, not hidden in the UI.
    Route::get('settings', [AppSettingsController::class, 'index'])->name('settings.index');

    Route::post('settings/customers', [CustomerController::class, 'store'])->name('settings.customers.store');
    Route::put('settings/customers/{customer}', [CustomerController::class, 'update'])->name('settings.customers.update');
    Route::delete('settings/customers/{customer}', [CustomerController::class, 'destroy'])->name('settings.customers.destroy');

    Route::post('settings/customer-engineers', [CustomerEngineerController::class, 'store'])->name('settings.customer-engineers.store');
    Route::put('settings/customer-engineers/{engineer}', [CustomerEngineerController::class, 'update'])->name('settings.customer-engineers.update');
    Route::delete('settings/customer-engineers/{engineer}', [CustomerEngineerController::class, 'destroy'])->name('settings.customer-engineers.destroy');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('email-logs', [EmailLogController::class, 'index'])->name('email-logs.index');
    Route::delete('email-logs', [EmailLogController::class, 'destroy'])->name('email-logs.destroy');

    Route::get('packages/deleted', [GarbagePackageController::class, 'index'])->name('packages.deleted');
    Route::delete('packages/{package}/purge', PurgePackageController::class)->withTrashed()->name('packages.purge');
    Route::post('packages/{package}/restore', RestorePackageController::class)->withTrashed()->name('packages.restore');
});

Route::middleware('auth')->group(function () {
    Route::get('/projects/{project}/chat', [ChatController::class, 'index'])->name('projects.chat.index');
    Route::post('/projects/{project}/chat', [ChatController::class, 'store'])->name('projects.chat.store');
    Route::put('/projects/{project}/chat/read', MarkChatReadController::class)->name('projects.chat.read');
    Route::get('/chat/projects', [ChatController::class, 'projects'])->name('chat.projects');
    Route::get('/projects/{project}/chat/messages', [ChatController::class, 'messages'])->name('projects.chat.messages');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

Route::middleware(['auth', 'role:customer'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', [CustomerPortalController::class, 'index'])->name('index');
        Route::get('projects/{project}', [CustomerPortalController::class, 'show'])->name('projects.show');
        Route::get('packages/{package}', CustomerPortalPackageController::class)->name('packages.show');
        Route::get('packages/{package}/pdf', CustomerPortalPdfController::class)->name('packages.pdf');
    });

Route::middleware('auth')->group(function () {
    Route::get('/packages/{package}/pdf/download', DownloadPdfController::class)->name('packages.pdf.download');
    Route::get('/packages/{package}/pdf/partial-download', DownloadPartialPdfController::class)->name('packages.pdf.partial-download');
    Route::get('/packages/{package}/pdf/revisions/{revision}/download', DownloadRevisionPdfController::class)->name('packages.pdf.revision-download');
    Route::get('/packages/{package}/pdf/status', PdfStatusController::class)->name('packages.pdf.status');
});

Route::middleware('auth')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->middleware('role:admin|manager')->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('role:admin|manager')->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->middleware('role:admin|manager')->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('role:admin|manager')->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('role:admin|manager')->name('projects.destroy');
    Route::post('/projects/{project}/supervisors', [ProjectSupervisorController::class, 'store'])->middleware('role:admin|manager')->name('projects.supervisors.store');
    Route::put('/projects/{project}/supervisors/primary', [ProjectSupervisorController::class, 'setPrimary'])->middleware('role:admin|manager')->name('projects.supervisors.primary');
    Route::delete('/projects/{project}/supervisors/{user}', [ProjectSupervisorController::class, 'destroy'])->middleware('role:admin|manager')->name('projects.supervisors.destroy');
    Route::post('/projects/{project}/site-photo', [ProjectSitePhotoController::class, 'store'])->middleware('role:admin|manager')->name('projects.site-photo.store');
    Route::get('/projects/{project}/site-photo', [ProjectSitePhotoController::class, 'show'])->name('projects.site-photo.show');
    Route::post('/projects/{project}/rec-documents', [RecDocumentController::class, 'store'])->middleware('role:admin|manager')->name('projects.rec-documents.store');
    Route::get('/projects/{project}/rec-documents/{media}', [RecDocumentController::class, 'show'])->name('projects.rec-documents.show');
    Route::get('/projects/{project}/rec-documents/{media}/download', [RecDocumentController::class, 'download'])->name('projects.rec-documents.download');
    Route::delete('/projects/{project}/rec-documents/{media}', [RecDocumentController::class, 'destroy'])->middleware('role:admin|manager')->name('projects.rec-documents.destroy');
    Route::post('/projects/{project}/documents/{collection}', [ProjectDocumentController::class, 'store'])->middleware('role:admin|manager')->name('projects.documents.store');
    Route::get('/projects/{project}/documents/{collection}/{media}', [ProjectDocumentController::class, 'show'])->name('projects.documents.show');
    Route::get('/projects/{project}/documents/{collection}/{media}/download', [ProjectDocumentController::class, 'download'])->name('projects.documents.download');
    Route::delete('/projects/{project}/documents/{collection}/{media}', [ProjectDocumentController::class, 'destroy'])->middleware('role:admin|manager')->name('projects.documents.destroy');
    Route::get('/projects/{project}/issues', [IssueHistoryController::class, 'index'])->name('projects.issues.index');
    Route::post('/projects/{project}/issues', [IssueHistoryController::class, 'store'])->name('projects.issues.store');
    Route::delete('/projects/{project}/issues/{issue}', [IssueHistoryController::class, 'destroy'])->middleware('role:admin|manager')->name('projects.issues.destroy');
    Route::put('/projects/{project}/issues/{issue}/resolve', ResolveIssueController::class)->middleware('role:admin|manager')->name('projects.issues.resolve');
    Route::post('/projects/{project}/issues/{issue}/comments', IssueCommentController::class)->middleware('role:admin|manager|field_supervisor')->name('projects.issues.comments.store');
    Route::post('/projects/{project}/issues/{issue}/attachments', [IssueAttachmentController::class, 'store'])->middleware('role:admin|manager|field_supervisor')->name('projects.issues.attachments.store');
    Route::delete('/projects/{project}/issues/{issue}/attachments/{media}', [IssueAttachmentController::class, 'destroy'])->middleware('role:admin|manager|field_supervisor')->name('projects.issues.attachments.destroy');
    Route::post('/projects/{project}/issues/forward', ForwardIssuesToCustomerController::class)->middleware('role:admin|manager')->name('projects.issues.forward');
    Route::get('/issues/attachments/{media}', ServeIssueAttachmentController::class)->name('issues.attachments.show');
    Route::post('/projects/{project}/crew', [CrewController::class, 'store'])->middleware('role:admin|manager')->name('projects.crew.store');
    Route::delete('/projects/{project}/crew/{user}', [CrewController::class, 'destroy'])->middleware('role:admin|manager')->name('projects.crew.destroy');
});

// Media Library — chunked upload, shared config, CRUD/picker/assign. Static
// segments are registered before the {asset} parameter so route resolution
// never mistakes /config, /stats or /picker for an asset id.
Route::middleware('auth')
    ->prefix('media-library')
    ->name('media-library.')
    ->group(function (): void {
        // System-wide purge. Its own role:admin group, kept separate from the
        // auth-only group above so widening library access can never widen this.
        Route::middleware('role:admin')->group(function (): void {
            Route::get('/purge/preview', [MediaAssetPurgeController::class, 'preview'])->name('purge.preview');
            Route::get('/purge/status', [MediaAssetPurgeController::class, 'status'])->name('purge.status');
            Route::post('/purge', [MediaAssetPurgeController::class, 'store'])->name('purge.store');
        });

        Route::get('/config', MediaAssetConfigController::class)->name('config');
        Route::get('/stats', [MediaAssetController::class, 'stats'])->name('stats');
        Route::get('/team', [MediaAssetController::class, 'team'])->name('team');
        Route::get('/projects', [MediaAssetController::class, 'projects'])->name('projects');
        Route::get('/picker', [MediaAssetController::class, 'picker'])->name('picker');

        Route::post('/assign', MediaAssetAssignController::class)->name('assign');

        Route::post('/upload/initiate', [MediaAssetUploadController::class, 'initiate'])
            ->middleware('throttle:media-upload-initiate')
            ->name('upload.initiate');
        Route::match(['post', 'put'], '/upload/{asset}/chunk', [MediaAssetUploadController::class, 'chunk'])
            ->middleware('throttle:media-upload-chunk')
            ->name('upload.chunk');
        Route::post('/upload/{asset}/finalize', [MediaAssetUploadController::class, 'finalize'])
            ->name('upload.finalize');
        Route::delete('/upload/{asset}/abort', [MediaAssetUploadController::class, 'abort'])
            ->name('upload.abort');

        Route::get('/', [MediaAssetController::class, 'index'])->name('index');
        Route::get('/{asset}/file', MediaAssetFileController::class)->name('file');
        Route::get('/{asset}', [MediaAssetController::class, 'show'])->name('show');
        Route::patch('/{asset}', [MediaAssetController::class, 'update'])->name('update');
        Route::post('/{asset}/annotate', MediaAssetAnnotateController::class)->name('annotate');
        Route::delete('/{asset}', [MediaAssetController::class, 'destroy'])->name('destroy');
    });

// Scope of Work (SOW) reports — Phase 1: CRUD, status transitions (submit/reopen/archive),
// survey checklist, and annotated-image management. Publish/PDF routes land with the
// GenerateSowPdfAction in Phase 4.
Route::middleware(['auth', 'verified'])
    ->prefix('sow')
    ->name('sow.')
    ->group(function (): void {
        Route::get('/', [SowReportController::class, 'index'])->name('reports.index');
        Route::resource('reports', SowReportController::class)->except(['index', 'edit']);

        // Status transitions
        Route::post('reports/{report}/submit', [SowReportController::class, 'submit'])->name('reports.submit');
        Route::post('reports/{report}/publish', [SowReportController::class, 'publish'])->name('reports.publish');
        Route::post('reports/{report}/reopen', [SowReportController::class, 'reopen'])->name('reports.reopen');
        Route::post('reports/{report}/archive', [SowReportController::class, 'archive'])->name('reports.archive');
        Route::post('reports/{report}/unarchive', [SowReportController::class, 'unarchive'])->name('reports.unarchive');
        Route::post('reports/{report}/attach-project', [SowReportController::class, 'attachProject'])->name('reports.attach-project');

        // PDF download & regeneration
        Route::get('reports/{report}/pdf', [SowReportController::class, 'downloadPdf'])->name('reports.pdf');
        Route::post('reports/{report}/pdf/regenerate', [SowReportController::class, 'regeneratePdf'])->name('reports.pdf.regenerate');

        // Survey Checklist
        Route::put('reports/{report}/checklist', [SowChecklistController::class, 'update'])->name('reports.checklist.update');
        Route::prefix('reports/{report}/checklist/items')->name('reports.checklist.items.')->group(function (): void {
            Route::post('/', [SowChecklistItemController::class, 'store'])->name('store');
            Route::put('{item}', [SowChecklistItemController::class, 'update'])->name('update');
            Route::delete('{item}', [SowChecklistItemController::class, 'destroy'])->name('destroy');
            Route::post('reorder', [SowChecklistItemController::class, 'reorder'])->name('reorder');
        });

        // Annotated images & Media Library bulk import
        Route::prefix('reports/{report}/images')->name('images.')->group(function (): void {
            Route::post('/', [SowAnnotatedImageController::class, 'store'])->name('store');
            Route::post('import-media', [SowAnnotatedImageController::class, 'importMedia'])->name('import-media');
            Route::put('{image}', [SowAnnotatedImageController::class, 'update'])->name('update');
            Route::delete('{image}', [SowAnnotatedImageController::class, 'destroy'])->name('destroy');
            Route::post('reorder', [SowAnnotatedImageController::class, 'reorder'])->name('reorder');
            Route::get('statuses', [SowAnnotatedImageController::class, 'compositeStatuses'])->name('statuses');
        });

        // Named photo groups — drive the PDF's photo sections.
        Route::prefix('reports/{report}/image-groups')->name('image-groups.')->group(function (): void {
            Route::post('/', [SowImageGroupController::class, 'store'])->name('store');
            Route::put('{group}', [SowImageGroupController::class, 'update'])->name('update');
            Route::delete('{group}', [SowImageGroupController::class, 'destroy'])->name('destroy');
        });

        // One atomic write for group order + image membership + image order.
        Route::patch('reports/{report}/images/layout', [SowImageGroupController::class, 'saveLayout'])
            ->name('images.layout');
    });
