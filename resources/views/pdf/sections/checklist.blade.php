<div class="page">
    @include('pdf.partials.header', [
        'logoPath' => $logoPath,
        'partnerLogoPath' => $partnerLogoPath,
        'siteInfo' => $siteInfo,
        'project' => $project,
        'package' => $package,
    ])

    <div class="page-title">{{ ucwords(strtolower((string) ($package->template?->name ?? '1F Room Ready'))) }} Checklist
    </div>

    @php
        $hasNaOption = $hasNaOption ?? (
            collect($responses)->contains(function ($response) {
                return (bool) ($response->is_na ?? false) || (bool) ($response->checklistItem?->allows_na ?? false);
            }) || ($package->template?->checklistItems()->where('allows_na', true)->exists() ?? false)
        );
    @endphp

    <table class="checklist-table">
        <colgroup>
            <col style="width: {{ $hasNaOption ? '73%' : '82%' }};">
            <col style="width: 9%;">
            <col style="width: 9%;">
            @if ($hasNaOption)
                <col style="width: 9%;">
            @endif
        </colgroup>
        <thead>
            <tr>
                <th colspan="{{ $hasNaOption ? 4 : 3 }}" class="checklist-banner">
                    {{ strtoupper($package->template?->name ?? '1F Room Ready') }} CHECKLIST
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach ($responses as $response)
                @php
                    $isNa = (bool) ($response->is_na ?? false);
                    $item = $response->checklistItem;
                    $allowsNa = (bool) ($item?->allows_na ?? false);

                    $isYes = ! $isNa && ($response->answer === true || $response->answer === 'yes' || $response->answer === '1');
                    $isNo = ! $isNa && ($response->answer === false || $response->answer === 'no' || $response->answer === '0');

                    $isBooleanChoice = $isNa || is_bool($response->answer) ||
                        $response->answer === 'yes' || $response->answer === 'no' ||
                        $response->answer === '1' || $response->answer === '0' ||
                        $response->answer === null;

                @endphp
                <tr>
                    <td style="width: {{ $hasNaOption ? '73%' : '82%' }}; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word;">
                        {{ $item?->label }}
                    </td>
                    @if ($isBooleanChoice)
                        <td style="width: 9%; text-align: center; white-space: nowrap; padding: 4px 2px;">
                            <svg width="16" height="16" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 4px; display: inline-block;">
                                <rect x="0.75" y="0.75" width="14.5" height="14.5" fill="#ffffff" stroke="#000000" stroke-width="1.5"/>
                                @if ($isYes)
                                    <line x1="0.75" y1="0.75" x2="15.25" y2="15.25" stroke="#000000" stroke-width="1.5"/>
                                    <line x1="15.25" y1="0.75" x2="0.75" y2="15.25" stroke="#000000" stroke-width="1.5"/>
                                @endif
                            </svg> YES
                        </td>
                        <td style="width: 9%; text-align: center; white-space: nowrap; padding: 4px 2px;">
                            <svg width="16" height="16" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 4px; display: inline-block;">
                                <rect x="0.75" y="0.75" width="14.5" height="14.5" fill="#ffffff" stroke="{{ $isNo ? '#ff0000' : '#000000' }}" stroke-width="1.5"/>
                                @if ($isNo)
                                    <line x1="0.75" y1="0.75" x2="15.25" y2="15.25" stroke="#ff0000" stroke-width="1.5"/>
                                    <line x1="15.25" y1="0.75" x2="0.75" y2="15.25" stroke="#ff0000" stroke-width="1.5"/>
                                @endif
                            </svg> NO
                        </td>
                        @if ($hasNaOption)
                            <td style="width: 9%; text-align: center; white-space: nowrap; padding: 4px 2px;">
                                @if ($allowsNa || $isNa)
                                    <svg width="16" height="16" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 4px; display: inline-block;">
                                        <rect x="0.75" y="0.75" width="14.5" height="14.5" fill="#ffffff" stroke="#000000" stroke-width="1.5"/>
                                        @if ($isNa)
                                            <line x1="0.75" y1="0.75" x2="15.25" y2="15.25" stroke="#000000" stroke-width="1.5"/>
                                            <line x1="15.25" y1="0.75" x2="0.75" y2="15.25" stroke="#000000" stroke-width="1.5"/>
                                        @endif
                                    </svg> N/A
                                @else
                                    <span style="color: #9ca3af; font-size: 13px; font-weight: bold;">—</span>
                                @endif
                            </td>
                        @endif
                    @else
                        <td colspan="{{ $hasNaOption ? 3 : 2 }}"
                            style="width: {{ $hasNaOption ? '27%' : '18%' }}; text-align: center; font-weight: bold; color: #000000; white-space: nowrap;">
                            {{ $response->answer }}
                        </td>
                    @endif
                </tr>
                @if (! $isNa && ($response->explain || $response->quantity))
                    <tr>
                        <td colspan="{{ $hasNaOption ? 4 : 3 }}" style="background-color: #ffffff; padding: 5px 8px; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word;">
                            @if ($response->explain)
                                <div style="word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; line-height: 1.35;">
                                    <span class="explain-text" style="margin-right: 6px;">NOTES:</span>
                                    <span style="color: #000000; font-size: 11.5px; white-space: pre-wrap;">{{ $response->explain }}</span>
                                </div>
                            @endif
                            @if ($response->quantity)
                                <div style="{{ $response->explain ? 'margin-top: 4px;' : '' }}">
                                    <span class="explain-text" style="margin-right: 6px;">QUANTITY:</span>
                                    <span style="color: #000000; font-size: 11.5px; font-weight: bold;">{{ $response->quantity }}</span>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @include('pdf.partials.footer', ['page' => $page])
</div>

