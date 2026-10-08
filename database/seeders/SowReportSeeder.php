<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SowReportSeeder extends Seeder
{
    use WithoutModelEvents;

    private const DRAFT_COUNT = 70;

    private const IN_REVIEW_COUNT = 80;

    public function run(): void
    {
        DB::transaction(function (): void {
            $ohioMarket = Market::where('name', 'Ohio Market')
                ->orWhere('slug', 'ohio-market')
                ->first();

            $dcMarket = Market::where('name', 'Washington, D.C.')
                ->orWhere('slug', 'washington-dc')
                ->first();

            if (! $ohioMarket || ! $dcMarket) {
                $this->command?->error('Run ProjectSeeder first to create Ohio and DC markets.');

                return;
            }

            $admin = User::where('email', 'testing@mail.com')->first()
                ?? User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
                ?? User::first();

            if (! $admin) {
                $this->command?->error('No admin user found. Run AdminUserSeeder first.');

                return;
            }

            // Existing projects (one per market)
            $ohioProject = Project::where('market_id', $ohioMarket->id)->first();
            $dcProject = Project::where('market_id', $dcMarket->id)->first();

            $reports = [];

            // --- Ohio: 35 drafts + 40 in_review ---
            $reports = array_merge($reports, $this->generateReports(
                market: $ohioMarket,
                project: $ohioProject,
                creator: $admin,
                prefix: 'OH',
                count: 35,
                status: SowStatus::DRAFT,
                offset: 0,
            ));
            $reports = array_merge($reports, $this->generateReports(
                market: $ohioMarket,
                project: $ohioProject,
                creator: $admin,
                prefix: 'OH',
                count: 40,
                status: SowStatus::IN_REVIEW,
                offset: 35,
            ));

            // --- DC: 35 drafts + 40 in_review ---
            $reports = array_merge($reports, $this->generateReports(
                market: $dcMarket,
                project: $dcProject,
                creator: $admin,
                prefix: 'DC',
                count: 35,
                status: SowStatus::DRAFT,
                offset: 70,
            ));
            $reports = array_merge($reports, $this->generateReports(
                market: $dcMarket,
                project: $dcProject,
                creator: $admin,
                prefix: 'DC',
                count: 40,
                status: SowStatus::IN_REVIEW,
                offset: 105,
            ));

            foreach ($reports as $data) {
                SowReport::create($data);
            }

            $this->command?->info('Created '.count($reports).' SOW reports (70 draft, 80 in_review).');
        });
    }

    private function generateReports(
        Market $market,
        ?Project $project,
        User $creator,
        string $prefix,
        int $count,
        SowStatus $status,
        int $offset,
    ): array {
        $reports = [];
        $siteNames = $this->siteNames($prefix);
        $cities = $this->cities($prefix);
        $counties = $this->counties($prefix);
        $streets = $this->streets($prefix);
        $fiberProviders = ['VZB', 'COMCAST', 'LUMEN', 'CHARTER', 'AT&T'];
        $demarcations = ['Building demarc in lobby utility closet', 'Outdoor cabinet at rear of building', 'Pedestal near parking lot entrance', 'Basement telecommunications room', 'Rooftop enclosure with antenna mounts', 'Side-mounted enclosure on east wall', 'Ground-level NID near utility pole', 'Interior MDF on second floor', 'Aerial junction box on pole 12', 'Underground vault at sidewalk edge'];
        $scopeDescriptions = [
            'Full fiber buildout from OTN to customer premises including new ONT installation and internal wiring.',
            'Small cell node installation with fiber backhaul connection and power provisioning.',
            'Existing circuit augmentation from 1G to 10G with new SFP+ transceiver installation.',
            'New aerial fiber route spanning 0.3 miles from existing pole to building entrance.',
            'Underground conduit installation and fiber pull for new business account.',
            'Demarc extension from building entry to suite 200 with 200ft of indoor fiber.',
            'Node site upgrade replacing legacy equipment with 5G NR capable hardware.',
            'Fiber splice and OTDR testing at three intermediate manholes along route.',
            'Complete premises wiring for VoIP and data services across four floors.',
            'Emergency repair of severed fiber following construction activity.',
        ];
        $accessNotes = [
            'Contact building manager 24 hours before site visit. Access badge required.',
            'Gate code provided in work order. Park in designated contractor spots only.',
            'No after-hours access. Site visit must occur between 8 AM and 5 PM weekdays.',
            'Escort required from security desk. Allow 30 minutes for check-in process.',
            'Rooftop access via elevator to floor 12, then stairwell. Hard hat required.',
            'Basement access through rear entrance. Lighting may be limited, bring flashlight.',
            'Weekend access available with 48-hour advance notice to facilities team.',
            'Temporary access badge available at front desk. Return upon completion.',
            'Construction zone active. Wear high-vis vest and follow safety officer directions.',
            'Locked telecom room. Key available from on-site manager, call upon arrival.',
        ];
        $contractorNames = ['FiberTech Solutions', 'NetBuild Inc', 'Telco Contractors LLC', 'Summit Fiber Services', 'Atlas Network Services', 'Pinnacle Telecom Corp', 'CrossLink Communications', 'Apex Infrastructure Co'];
        $contactNames = ['James Mitchell', 'Sarah Thompson', 'Michael Chen', 'Jennifer Park', 'Robert Singh', 'Lisa Nguyen', 'David Kowalski', 'Amanda Foster', 'Chris Hoffman', 'Rachel Green'];

        for ($i = 0; $i < $count; $i++) {
            $idx = $offset + $i;
            $cityData = $cities[$idx % count($cities)];
            $street = $streets[$idx % count($streets)];
            $lat = $this->lat($prefix, $idx);
            $lng = $this->lng($prefix, $idx);
            $reportDate = now()->subDays(random_int(1, 60))->toDateString();
            $fieldSurveyDate = now()->subDays(random_int(1, 30))->toDateString();
            $contactName = $contactNames[array_rand($contactNames)];
            $contactPhone = '+1-'.$this->areaCode($prefix).'-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT).'-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $contactEmail = strtolower(str_replace(' ', '.', $contactName)).'@verizon.com';
            $submittedAt = $status === SowStatus::IN_REVIEW ? now()->subDays(random_int(1, 14)) : null;

            $reports[] = [
                'project_id' => $project?->id,
                'market_id' => $market->id,
                'created_by' => $creator->id,
                'title' => "SOW {$prefix}-".str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT)." - {$siteNames[$idx % count($siteNames)]}",
                'site_name' => $siteNames[$idx % count($siteNames)],
                'report_date' => $reportDate,
                'fiber_provider' => $fiberProviders[array_rand($fiberProviders)],
                'site_address' => $street,
                'site_city' => $cityData['name'],
                'site_county' => $counties[$idx % count($counties)],
                'latitude' => $lat,
                'longitude' => $lng,
                'project_description' => "{$siteNames[$idx % count($siteNames)]} - {$cityData['name']}, {$prefix}. Fiber deployment project for new small cell or macro site in the {$cityData['region']} region.",
                'field_survey_date' => $fieldSurveyDate,
                'provider_contact_name' => $contactName,
                'provider_contact_phone' => $contactPhone,
                'provider_contact_email' => $contactEmail,
                'demarcation' => $demarcations[array_rand($demarcations)],
                'ps_number' => "PS-{$prefix}-".str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT),
                'survey_contractor_name' => $contractorNames[array_rand($contractorNames)],
                'survey_contact_name' => $contactNames[array_rand($contactNames)],
                'survey_contact_phone' => $contactPhone,
                'survey_contact_email' => strtolower(str_replace(' ', '.', $contactNames[array_rand($contactNames)])).'@fibertech.com',
                'scope_of_work_description' => $scopeDescriptions[array_rand($scopeDescriptions)],
                'site_access_notes' => $accessNotes[array_rand($accessNotes)],
                'status' => $status->value,
                'pdf_generation_status' => 'idle',
                'revision' => 1,
                'submitted_at' => $submittedAt,
            ];
        }

        return $reports;
    }

    private function siteNames(string $prefix): array
    {
        $ohio = [
            'Columbus North Macro', 'Dublin Small Cell 01', 'Westerville Node East', 'Gahanna Tower Site',
            'Hilliard Fiber Hub', 'Reynoldsburg Pole Mount', 'Grove City Demarc', 'Pickerington ONT Site',
            'Upper Arlington CAB', 'Bexley Building Entry', 'Grandview Heights Node', 'Marble Cliff Annex',
            ' Clintonville Aerial', 'Near East Side NID', 'German Village Splice', 'Italian Village Ped',
            'Short North Junction', 'Arena District Cabinet', 'Scioto Mile Pedestal', 'Franklinton Vault',
            'Lincoln Village Build', 'Georgescape Node 03', 'Prairie Township Hub', 'Clinton Township Pole',
            'Blendon Woods Site', 'Big Walnut Junction', 'Alum Creek Pathway', 'Obetz Road Route',
            'Canal Winchester CB', 'Lithopolis splice point', 'Groves City South', 'Westland Mall Area',
            'Polaris Parkway Node', 'Pickett Road Entry', 'Sawmill Campus Site',
        ];
        $dc = [
            'Georgetown Row Cell', 'Capitol Hill Small Cell', 'Adams Morgan Node', 'Dupont Circle Hub',
            'Navy Yard Pole Site', 'Shaw Neighborhood CAB', 'Columbia Heights Entry', 'U Street Corridor',
            'H Street NE Node', 'Barracks Row Cell', 'Logan Circle Ped', 'Foggy Bottom NID',
            'Woodley Park Vault', 'Cleveland Park Splice', 'Van Ness Junction', 'Tenleytown Hub',
            'Friendship Heights Node', 'Chevy Chase Cabinet', 'Brookland Pole Mount', 'Michigan Park Entry',
            'Petworth Site 01', 'Brightwood Node', 'Trinidad Pedestal', 'Capitol View Build',
            'Congressional Plaza', 'Anacostia Pathway', 'Navy Yard Waterfront', 'SE Federal Center',
            'NoMa Rail Corridor', 'Eckington Junction', 'Bloomingdale NID', 'LeDroit Park Annex',
            'Howard University Area', 'U Street SE Splice', 'Takoma Park Route',
        ];

        return $prefix === 'OH' ? $ohio : $dc;
    }

    private function cities(string $prefix): array
    {
        if ($prefix === 'OH') {
            return [
                ['name' => 'Columbus', 'region' => 'Central'],
                ['name' => 'Dublin', 'region' => 'Northwest'],
                ['name' => 'Westerville', 'region' => 'Northeast'],
                ['name' => 'Gahanna', 'region' => 'East'],
                ['name' => 'Hilliard', 'region' => 'West'],
                ['name' => 'Reynoldsburg', 'region' => 'East'],
                ['name' => 'Grove City', 'region' => 'Southwest'],
                ['name' => 'Pickerington', 'region' => 'Southeast'],
                ['name' => 'Upper Arlington', 'region' => 'West'],
                ['name' => 'Bexley', 'region' => 'Central'],
            ];
        }

        return [
            ['name' => 'Washington', 'region' => 'Northwest'],
            ['name' => 'Washington', 'region' => 'Northeast'],
            ['name' => 'Washington', 'region' => 'Southeast'],
            ['name' => 'Washington', 'region' => 'Southwest'],
            ['name' => 'Georgetown', 'region' => 'Northwest'],
            ['name' => 'Capitol Hill', 'region' => 'Northeast'],
            ['name' => 'Adams Morgan', 'region' => 'Northwest'],
            ['name' => 'Dupont Circle', 'region' => 'Northwest'],
            ['name' => 'Navy Yard', 'region' => 'Southeast'],
            ['name' => 'Shaw', 'region' => 'Northwest'],
        ];
    }

    private function counties(string $prefix): array
    {
        return $prefix === 'OH'
            ? ['Franklin County', 'Delaware County', 'Fairfield County', 'Licking County', 'Pickaway County']
            : ['District of Columbia', 'Arlington County', 'Montgomery County', 'Prince Georges County'];
    }

    private function streets(string $prefix): array
    {
        $ohio = [
            '4500 Polaris Pkwy', '6100 Sawmill Rd', '3200 Fishinger Rd', '5600 Ault Rd',
            '1200 Dublin-Granville Rd', '7800 E Broad St', '2900 Main St', '8500 Cleveland Ave NW',
            '4100 Cemetery Rd', '2400 N High St', '1500 W Lane Ave', '3600 Soldano Blvd',
            '920 King Ave', '1800 NW Goodale Blvd', '500 W Broad St', '620 S High St',
            '1100 Neil Ave', '400 W Town St', '700 S Front St', '2200 N High St',
            '3400 Indianola Ave', '1600 Summit St', '2800 E Main St', '4300 E Livingston Ave',
            '940 S Hamilton Rd', '5200 Chantry Dr', '6800 Recommendation Ln', '3950 Obetz Rd',
            '6300 Winchester Pike', '7406 S High St', '3100 Alum Creek Dr', '2750 Billingsley Rd',
            '1500 Polaris Way', '8900 Pickett Rd', '4400 Tuttle Crossing Blvd',
        ];
        $dc = [
            '1400 Constitution Ave NW', '300 Massachusetts Ave NW', '1800 K St NW',
            '2100 Pennsylvania Ave NW', '1200 4th St SW', '1600 U St NW',
            '1300 H St NE', '800 8th St SE', '1900 14th St NW', '400 8th St SE',
            '1700 17th St NW', '2300 Connecticut Ave NW', '3700 connector Rd NW',
            '4800 Connecticut Ave NW', '3900 Nebraska Ave NW', '4500 41st St NW',
            '5500 Wisconsin Ave NW', '5600 Connecticut Ave NW', '100 Michigan Ave NE',
            '1300 13th St NE', '4700 Georgia Ave NW', '5800 12th St NE',
            '2100 Travis Rd SE', '220 M St SE', '700 2nd St NE',
            '1900 Anacostia Dr SE', '400 M St SE', '1200 New Jersey Ave NW',
            '100 N St NE', '200 Florida Ave NE', '200 Seaton Pl NW',
            '2100 4th St NW', '2400 16th St NW', '410 Riggs Rd NE', '6900 4th St NW',
        ];

        return $prefix === 'OH' ? $ohio : $dc;
    }

    private function lat(string $prefix, int $index): float
    {
        $base = $prefix === 'OH' ? 39.9612 : 38.9072;

        return round($base + ($index * 0.0012) + (random_int(-5, 5) / 10000), 7);
    }

    private function lng(string $prefix, int $index): float
    {
        $base = $prefix === 'OH' ? -82.9988 : -77.0369;

        return round($base + ($index * 0.0015) + (random_int(-5, 5) / 10000), 7);
    }

    private function areaCode(string $prefix): string
    {
        $codes = $prefix === 'OH' ? ['614', '614', '740', '380'] : ['202', '202', '301', '571'];

        return $codes[array_rand($codes)];
    }
}
