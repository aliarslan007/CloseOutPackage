<?php

declare(strict_types=1);

use App\Domains\Markets\Models\Market;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowChecklistItem;
use App\Domains\ScopeOfWork\Models\SowChecklistResponse;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Services\SowPdfRenderService;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows a field technician to add a survey checklist question to an SOW', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $tech->markets()->attach($market->id);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $tech->id,
        'title' => 'Test SOW',
        'site_name' => 'Site Alpha',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($tech)
        ->postJson(route('sow.reports.checklist.items.store', $report->id), [
            'label' => 'Was the fiber demarcation clearly identified?',
            'has_explain_field' => false,
            'is_required' => true,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'item' => [
                'label' => 'Was the fiber demarcation clearly identified?',
                'has_explain_field' => false,
            ],
        ]);

    $this->assertDatabaseHas('sow_checklist_items', [
        'sow_report_id' => $report->id,
        'label' => 'Was the fiber demarcation clearly identified?',
        'has_explain_field' => false,
    ]);

    $this->assertDatabaseHas('sow_checklist_responses', [
        'sow_report_id' => $report->id,
        'response' => null,
    ]);
});

it('allows updating a checklist question', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Test SOW',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    $item = SowChecklistItem::create([
        'sow_report_id' => $report->id,
        'market_id' => $market->id,
        'label' => 'Original Question',
        'has_explain_field' => false,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($manager)
        ->putJson(route('sow.reports.checklist.items.update', [$report->id, $item->id]), [
            'label' => 'Updated Question with explanation requirement',
            'has_explain_field' => true,
            'is_required' => false,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'item' => [
                'label' => 'Updated Question with explanation requirement',
                'has_explain_field' => true,
                'is_required' => false,
            ],
        ]);

    $this->assertDatabaseHas('sow_checklist_items', [
        'id' => $item->id,
        'label' => 'Updated Question with explanation requirement',
        'has_explain_field' => true,
        'is_required' => false,
    ]);
});

it('allows deleting a checklist question and cascades its response', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $admin->id,
        'title' => 'Test SOW',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    $item = SowChecklistItem::create([
        'sow_report_id' => $report->id,
        'market_id' => $market->id,
        'label' => 'Question To Delete',
        'has_explain_field' => false,
        'sort_order' => 1,
    ]);

    $resp = SowChecklistResponse::create([
        'sow_report_id' => $report->id,
        'sow_checklist_item_id' => $item->id,
        'response' => 'yes',
    ]);

    $response = $this->actingAs($admin)
        ->deleteJson(route('sow.reports.checklist.items.destroy', [$report->id, $item->id]));

    $response->assertOk()->assertJson(['success' => true]);

    $this->assertDatabaseMissing('sow_checklist_items', ['id' => $item->id]);
    $this->assertDatabaseMissing('sow_checklist_responses', ['id' => $resp->id]);
});

it('saves responses and sanitizes explanation when response is not no', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $tech->markets()->attach($market->id);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $tech->id,
        'title' => 'Test SOW',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    $item1 = SowChecklistItem::create([
        'sow_report_id' => $report->id,
        'market_id' => $market->id,
        'label' => 'Question 1',
        'has_explain_field' => true,
        'sort_order' => 1,
    ]);

    $item2 = SowChecklistItem::create([
        'sow_report_id' => $report->id,
        'market_id' => $market->id,
        'label' => 'Question 2',
        'has_explain_field' => false,
        'sort_order' => 2,
    ]);

    $response = $this->actingAs($tech)
        ->putJson(route('sow.reports.checklist.update', $report->id), [
            'responses' => [
                [
                    'sow_checklist_item_id' => $item1->id,
                    'response' => 'no',
                    'explanation' => 'Demarc was blocked by lockbox.',
                ],
                [
                    'sow_checklist_item_id' => $item2->id,
                    'response' => 'yes',
                    'explanation' => 'Should be cleared because response is yes',
                ],
            ],
        ]);

    $response->assertOk()->assertJson(['success' => true]);

    $this->assertDatabaseHas('sow_checklist_responses', [
        'sow_report_id' => $report->id,
        'sow_checklist_item_id' => $item1->id,
        'response' => 'no',
        'explanation' => 'Demarc was blocked by lockbox.',
    ]);

    $this->assertDatabaseHas('sow_checklist_responses', [
        'sow_report_id' => $report->id,
        'sow_checklist_item_id' => $item2->id,
        'response' => 'yes',
        'explanation' => null,
    ]);
});

it('renders survey checklist on SOW show page', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create(['is_active' => true]);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $admin->id,
        'title' => 'Test SOW',
        'site_name' => 'Site Beta',
        'site_county' => 'Orange',
        'ps_number' => 'PS-9988',
        'fiber_provider' => 'VZB',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    SowChecklistItem::create([
        'sow_report_id' => $report->id,
        'market_id' => $market->id,
        'label' => 'Dynamic Survey Question',
        'has_explain_field' => false,
        'sort_order' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('sow.reports.show', $report->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScopeOfWork/Show')
            ->where('report.site_name', 'Site Beta')
            ->where('report.site_county', 'Orange')
            ->where('report.ps_number', 'PS-9988')
            ->where('report.fiber_provider', 'VZB')
            ->has('checklist', 1)
            ->where('checklist.0.label', 'Dynamic Survey Question')
            ->where('checklist.0.has_explain_field', false));
});

it('renders the COP-style survey checklist table in the SOW PDF', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $admin->id,
        'title' => 'Checklist SOW',
        'site_name' => 'Site Gamma',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    $yesItem = SowChecklistItem::create([
        'sow_report_id' => $report->id,
        'market_id' => $market->id,
        'label' => 'Was demarcation identified?',
        'has_explain_field' => false,
        'sort_order' => 1,
    ]);

    $noItem = SowChecklistItem::create([
        'sow_report_id' => $report->id,
        'market_id' => $market->id,
        'label' => 'Was conduit clear?',
        'has_explain_field' => true,
        'sort_order' => 2,
    ]);

    $naItem = SowChecklistItem::create([
        'sow_report_id' => $report->id,
        'market_id' => $market->id,
        'label' => 'Roof access required?',
        'has_explain_field' => false,
        'sort_order' => 3,
    ]);

    SowChecklistResponse::create([
        'sow_report_id' => $report->id,
        'sow_checklist_item_id' => $yesItem->id,
        'response' => 'yes',
    ]);

    SowChecklistResponse::create([
        'sow_report_id' => $report->id,
        'sow_checklist_item_id' => $noItem->id,
        'response' => 'no',
        'explanation' => 'Conduit blocked by debris.',
    ]);

    SowChecklistResponse::create([
        'sow_report_id' => $report->id,
        'sow_checklist_item_id' => $naItem->id,
        'response' => 'n_a',
    ]);

    $html = app(SowPdfRenderService::class)->render($report)['html'];

    expect($html)->toContain('checklist-table')
        ->and($html)->toContain('Was demarcation identified?')
        ->and($html)->toContain('Was conduit clear?')
        ->and($html)->toContain('Roof access required?')
        // Explanation renders as its own full-width row, not a table column
        ->and($html)->toContain('EXPLANATION:')
        ->and($html)->toContain('Conduit blocked by debris.')
        ->and(substr_count($html, 'EXPLANATION:'))->toBe(1)
        // NO answers use the red checkbox stroke, YES / N/A stay black
        ->and($html)->toContain('stroke="#ff0000"')
        // YES / NO / N/A text is not bold
        ->and($html)->toMatch('/checklist-choice\s*\{[^}]*font-weight:\s*400/')
        // The previous unstyled table is gone
        ->and($html)->not->toContain('table-custom')
        ->and($html)->not->toContain('CHECKLIST QUESTION');
});
