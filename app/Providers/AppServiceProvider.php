<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Approval\Models\PhotoApproval;
use App\Domains\Approval\Policies\PhotoApprovalPolicy;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Policies\ChangeOrderPolicy;
use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Chat\Policies\ChatMessagePolicy;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Policies\CloseoutPackagePolicy;
use App\Domains\Crew\Models\CrewAssignment;
use App\Domains\Crew\Policies\CrewAssignmentPolicy;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Policies\CustomerPortalPolicy;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Customers\Policies\CustomerEngineerPolicy;
use App\Domains\Customers\Policies\CustomerPolicy;
use App\Domains\Dashboard\DTOs\DashboardStatsDTO;
use App\Domains\Dashboard\Policies\DashboardPolicy;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Policies\EmailLogPolicy;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\EmailTemplates\Policies\EmailTemplatePolicy;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Policies\EntityFeedbackPolicy;
use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Policies\MarketPolicy;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Policies\MediaAssetPolicy;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\PdfGeneration\Policies\PdfGenerationPolicy;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Policies\PhotoPolicy;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Policies\IssueHistoryPolicy;
use App\Domains\Projects\Policies\ProjectPolicy;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Policies\SowReportPolicy;
use App\Domains\Settings\Models\AppSetting;
use App\Domains\Settings\Policies\SettingsPolicy;
use App\Domains\Templates\Models\FavoriteTemplate;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Policies\FavoriteTemplatePolicy;
use App\Domains\Templates\Policies\TemplatePolicy;
use App\Domains\Tracking\Models\LocationPing;
use App\Domains\Tracking\Policies\TrackingPolicy;
use App\Domains\Users\Models\User;
use App\Domains\Users\Policies\UserManagementPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::policy(Market::class, MarketPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(IssueHistory::class, IssueHistoryPolicy::class);
        Gate::policy(ChangeOrder::class, ChangeOrderPolicy::class);
        Gate::policy(LocationPing::class, TrackingPolicy::class);
        Gate::policy(Template::class, TemplatePolicy::class);
        Gate::policy(FavoriteTemplate::class, FavoriteTemplatePolicy::class);
        Gate::policy(CloseoutPackage::class, CloseoutPackagePolicy::class);
        Gate::policy(Photo::class, PhotoPolicy::class);
        Gate::policy(PhotoApproval::class, PhotoApprovalPolicy::class);
        Gate::policy(PackageRevision::class, PdfGenerationPolicy::class);
        Gate::policy(ChatMessage::class, ChatMessagePolicy::class);
        Gate::policy(CustomerProjectAccess::class, CustomerPortalPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerEngineer::class, CustomerEngineerPolicy::class);
        Gate::policy(DashboardStatsDTO::class, DashboardPolicy::class);
        Gate::policy(EmailLog::class, EmailLogPolicy::class);
        Gate::policy(EmailTemplate::class, EmailTemplatePolicy::class);
        Gate::policy(CrewAssignment::class, CrewAssignmentPolicy::class);

        // Both feedback models map to the same policy; requests resolve the gate
        // through these class-strings (Gate::allows('ability', [ClassString, ...])).
        // EntityFeedback is the single feedback engine. Registered so the
        // abilities in EntityFeedbackPolicy are actually enforced — previously
        // every request authorized with nothing but "is someone logged in".
        Gate::policy(EntityFeedbackRound::class, EntityFeedbackPolicy::class);
        Gate::policy(EntityFeedbackNote::class, EntityFeedbackPolicy::class);

        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);
        Gate::policy(User::class, UserManagementPolicy::class);
        Gate::policy(AppSetting::class, SettingsPolicy::class);
        Gate::policy(SowReport::class, SowReportPolicy::class);

        RateLimiter::for('media-upload-initiate', function (Request $request) {
            $limit = $this->app->runningUnitTests() ? 30 : (int) config('cop.media_rate_limit_initiate', 120);

            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('media-upload-chunk', function (Request $request) {
            $limit = $this->app->runningUnitTests() ? 120 : (int) config('cop.media_rate_limit_chunk', 300);

            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });

        // Event listeners are auto-discovered from app/Domains/*/Listeners
        // (configured via ->withEvents(discover:) in bootstrap/app.php).

        Vite::prefetch(concurrency: 3);
    }
}
