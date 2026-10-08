<div class="page">
    <div class="cover-logo-center">
        @if ($logoPath !== null)
            <img src="{{ $logoPath }}" alt="Company logo">
        @endif
    </div>

    <div class="cover-titles">
        <div class="cover-title-primary" title="{{ $package->template?->name ?? '1F Room Ready Close-Out-Package' }}">{{ ucwords(strtolower((string) ($package->template?->name ?? '1F Room Ready Close-Out-Package'))) }}</div>
        <div class="cover-title-secondary">{{ ucwords(strtolower((string) ($siteInfo?->site_name ?? ($project?->name ?? 'Lomas Peak')))) }}</div>
    </div>

    <div class="cover-photo-box"
        style="border: 1.5px solid #000000; margin-bottom: 16px; background: #ffffff; max-width: 75%; margin-left: auto; margin-right: auto;">
        <div class="table-banner-black"
            style="background-color: #000000; color: #ffffff; font-weight: 900; text-align: center; font-size: 14px; text-transform: uppercase; padding: 5px 8px; border: 1px solid #000000; letter-spacing: 0.5px;">
            OVERALL SITE PHOTO</div>
        <div class="cover-photo-container"
            style="height: 245px; text-align: center; padding: 8px; display: flex; align-items: center; justify-content: center;">
            @if ($sitePhotoPath !== null)
                <img src="{{ $sitePhotoPath }}" alt="Overall site"
                    style="max-height: 229px; max-width: 100%; object-fit: contain;">
            @else
                <div style="color: #666; font-style: italic; font-size: 15px; font-weight: bold;">Overall site photo not
                    provided</div>
            @endif
        </div>
    </div>

    <!-- 1. SITE INFORMATION TABLE -->
    <table class="table-custom">
        <thead>
            <tr>
                <th colspan="6" class="table-banner-red"
                    style="background-color: #ff0000; color: #ffffff; font-weight: 900; text-align: center; font-size: 14px; text-transform: uppercase; padding: 5px 8px; border: 1px solid #000000; letter-spacing: 0.5px;">
                    SITE INFORMATION</th>
            </tr>
        </thead>
        <tbody>
            @php
                $completeDate = $package->project?->completed_date ?? $siteInfo?->complete_date;

                $siteAddress = $siteInfo?->address;
                if (! filled($siteAddress)) {
                    $siteAddress = $project?->address;
                }

                $projectLocation = trim(implode(', ', array_filter([
                    $project?->city,
                    trim(($project?->state ?? '') . ' ' . ($project?->zip ?? '')),
                ])));

                if (filled($siteAddress) && filled($projectLocation) && ! str_contains((string) $siteAddress, (string) ($project?->city ?? ''))) {
                    $displayAddress = $siteAddress . ', ' . $projectLocation;
                } else {
                    $displayAddress = filled($siteAddress) ? $siteAddress : (filled($projectLocation) ? $projectLocation : 'Empty');
                }
            @endphp
            <tr>
                <th scope="row" class="label-cell" style="width: 8%; text-align: left; white-space: nowrap; border-right: none !important;">PS#:</th>
                <td style="width: 20%; border-left: none !important;">{{ filled($siteInfo?->ps_number) ? $siteInfo->ps_number : 'Empty' }}</td>
                <th scope="row" class="label-cell" style="width: 14%; white-space: nowrap; border-right: none !important;">PROJECT #:</th>
                <td style="width: 22%; border-left: none !important;">{{ filled($siteInfo?->project_number) ? $siteInfo->project_number : 'Empty' }}</td>
                <th scope="row" class="label-cell" style="width: 18%; white-space: nowrap; border-right: none !important;">COMPLETE DATE:</th>
                <td style="width: 18%; border-left: none !important;">{{ filled($completeDate) ? \Carbon\Carbon::parse($completeDate)->format('n/j/Y') : 'Empty' }}</td>
            </tr>
            <tr>
                <th scope="row" class="label-cell" style="width: 16%; vertical-align: top; white-space: nowrap; border-right: none !important;">SITE ADDRESS:</th>
                <td colspan="2" style="vertical-align: top; line-height: 1.4; border-left: none !important; word-break: break-word; overflow-wrap: break-word; white-space: normal;">
                    {{ $displayAddress }}
                </td>
                <td colspan="3" style="padding: 0; vertical-align: top;">
                    <table style="width: 100%; border-collapse: collapse; border: none;">
                        <tr>
                            <th scope="row" class="label-cell"
                                style="width: 40%; border-top: none; border-right: none !important; white-space: nowrap;">{{ strtoupper($package->project?->customer?->name ?? 'CUSTOMER') }} ENGINEER:</th>
                            <td style="border-top: none; border-left: none !important; border-right: none; font-weight: bold;">
                                {{ filled($siteInfo?->vzw_engineer_name) ? $siteInfo->vzw_engineer_name : 'Empty' }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="label-cell" style="white-space: nowrap; border-right: none !important;">PHONE:
                            </th>
                            <td style="border-left: none !important; border-right: none;">{{ filled($siteInfo?->vzw_engineer_phone) ? $siteInfo->vzw_engineer_phone : 'Empty' }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="label-cell"
                                style="border-bottom: none; border-right: none !important; white-space: nowrap;">EMAIL:</th>
                            <td style="border-bottom: none; border-left: none !important; border-right: none; font-size: 12.5px;">
                                {{ filled($siteInfo?->vzw_engineer_email) ? $siteInfo->vzw_engineer_email : 'Empty' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- 2. CLOSE-OUT-PACKAGE AUTHOR TABLE -->
    <table class="table-custom">
        <thead>
            <tr>
                <th colspan="4" class="table-banner-black"
                    style="background-color: #000000; color: #ffffff; font-weight: 900; text-align: center; font-size: 14px; text-transform: uppercase; padding: 5px 8px; border: 1px solid #000000; letter-spacing: 0.5px;">
                    CLOSE-OUT-PACKAGE AUTHOR</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th scope="row" class="label-cell" style="width: 16%; white-space: nowrap; border-right: none !important;">COMPANY:</th>
                <td style="width: 34%; border-left: none !important;">{{ filled($siteInfo?->author_company) ? $siteInfo->author_company : 'Empty' }}</td>
                <th scope="row" class="label-cell" style="width: 16%; white-space: nowrap; border-right: none !important;">NAME:</th>
                <td style="width: 34%; border-left: none !important;">{{ filled($siteInfo?->author_name) ? $siteInfo->author_name : 'Empty' }}</td>
            </tr>
            <tr>
                <th scope="row" class="label-cell" style="white-space: nowrap; border-right: none !important;">EMAIL:</th>
                <td style="font-size: 12.5px; border-left: none !important;">{{ filled($siteInfo?->author_email) ? $siteInfo->author_email : 'Empty' }}</td>
                <th scope="row" class="label-cell" style="white-space: nowrap; border-right: none !important;">PHONE:</th>
                <td style="border-left: none !important;">{{ filled($siteInfo?->author_phone) ? $siteInfo->author_phone : 'Empty' }}</td>
            </tr>
        </tbody>
    </table>

    <!-- 3. PROJECT MANAGEMENT INFORMATION TABLE -->
    <table class="table-custom">
        <thead>
            <tr>
                <th colspan="4" class="table-banner-black"
                    style="background-color: #000000; color: #ffffff; font-weight: 900; text-align: center; font-size: 14px; text-transform: uppercase; padding: 5px 8px; border: 1px solid #000000; letter-spacing: 0.5px;">
                    PROJECT MANAGEMENT INFORMATION</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th scope="row" class="label-cell" style="width: 16%; white-space: nowrap; border-right: none !important;">COMPANY:</th>
                @php
                    $pmCompany = $siteInfo?->pm_company ?? $package->project?->manager?->company ?? $siteInfo?->author_company;
                    $pmName = $siteInfo?->pm_name ?? $package->project?->manager?->name;
                    $pmEmail = $siteInfo?->pm_email ?? $package->project?->manager?->email;
                    $pmPhone = $siteInfo?->pm_phone ?? $package->project?->manager?->phone;
                @endphp
                <td style="width: 34%; border-left: none !important;">{{ filled($pmCompany) ? $pmCompany : 'Empty' }}</td>
                <th scope="row" class="label-cell" style="width: 16%; white-space: nowrap; border-right: none !important;">NAME:</th>
                <td style="width: 34%; border-left: none !important;">{{ filled($pmName) ? $pmName : 'Empty' }}</td>
            </tr>
            <tr>
                <th scope="row" class="label-cell" style="white-space: nowrap; border-right: none !important;">EMAIL:</th>
                <td style="font-size: 12.5px; border-left: none !important;">{{ filled($pmEmail) ? $pmEmail : 'Empty' }}</td>
                <th scope="row" class="label-cell" style="white-space: nowrap; border-right: none !important;">PHONE:</th>
                <td style="border-left: none !important;">{{ filled($pmPhone) ? $pmPhone : 'Empty' }}</td>
            </tr>
        </tbody>
    </table>

    @include('pdf.partials.footer', ['page' => $page])
</div>
