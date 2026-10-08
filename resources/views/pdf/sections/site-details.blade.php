<div class="page">
    @include('pdf.partials.header', [
        'logoPath' => $logoPath,
        'partnerLogoPath' => $partnerLogoPath,
        'siteInfo' => $siteInfo,
        'project' => $project,
        'package' => $package,
    ])

    <div class="page-title">Hub / Site Details</div>

    <table class="site-details-table">
        <colgroup>
            <col style="width: 8%;">
            <col style="width: 42%;">
            <col style="width: 16%;">
            <col style="width: 14%;">
            <col style="width: 10%;">
            <col style="width: 10%;">
        </colgroup>
        <thead>
            <tr>
                <th colspan="6" class="site-details-banner">
                    HUB / SITE DETAILS
                </th>
            </tr>
            <tr>
                <th style="text-align: center;">#</th>
                <th>Site Name</th>
                <th>Type</th>
                <th>Region</th>
                <th>State</th>
                <th style="text-align: center;">Source</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($siteDetails as $index => $detail)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $detail->name }}</td>
                    <td>{{ $detail->site_type->value === 'hub' ? 'Hub Site' : 'Cell Site' }}</td>
                    <td>{{ $detail->region ?? '—' }}</td>
                    <td>{{ $detail->state ?? '—' }}</td>
                    <td style="text-align: center;">{{ $detail->source->value === 'auth_center' ? 'Site Access Info' : 'Manual' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #666666; font-style: italic;">
                        No hub / site details recorded for this package.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('pdf.partials.footer', ['page' => $page])
</div>