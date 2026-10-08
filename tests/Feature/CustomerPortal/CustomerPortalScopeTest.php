<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows customer access across the full feedback-loop status set', function (): void {
    $fixture = feedbackFixture();
    $scope = app(CustomerPortalScopeService::class);

    foreach ([PackageStatus::APPROVED, PackageStatus::CUSTOMER_REVIEW, PackageStatus::FEEDBACK_RECEIVED, PackageStatus::REVISION_IN_PROGRESS] as $status) {
        $fixture['package']->update(['status' => $status->value]);
        expect($scope->hasPackageAccess($fixture['customer'], $fixture['package']->fresh()))->toBeTrue("status {$status->value} should be accessible");
    }
});

it('denies customer access for non-customer-facing statuses', function (): void {
    $fixture = feedbackFixture();
    $scope = app(CustomerPortalScopeService::class);

    foreach ([PackageStatus::DRAFT, PackageStatus::SUBMITTED, PackageStatus::UNDER_REVIEW, PackageStatus::REJECTED] as $status) {
        $fixture['package']->update(['status' => $status->value]);
        expect($scope->hasPackageAccess($fixture['customer'], $fixture['package']->fresh()))->toBeFalse("status {$status->value} should be inaccessible");
    }
});

it('still denies access to locked packages inside the loop', function (): void {
    $fixture = feedbackFixture();
    $scope = app(CustomerPortalScopeService::class);
    $fixture['package']->update(['status' => PackageStatus::CUSTOMER_REVIEW->value, 'locked_at' => now()]);

    expect($scope->hasPackageAccess($fixture['customer'], $fixture['package']))->toBeFalse();
});

it('denies access to customers whose access expired mid-round', function (): void {
    $fixture = feedbackFixture();
    $scope = app(CustomerPortalScopeService::class);
    CustomerProjectAccess::query()
        ->where('user_id', $fixture['customer']->id)
        ->update(['expires_at' => now()->subDay()]);

    expect($scope->hasPackageAccess($fixture['customer'], $fixture['package']))->toBeFalse();
});
