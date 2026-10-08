<div class="page">
    @include('pdf.partials.header', [
        'logoPath' => $logoPath,
        'partnerLogoPath' => $partnerLogoPath,
        'siteInfo' => $siteInfo,
        'project' => $project,
        'package' => $package
    ])

    @if (!empty($stageHeading))
        <div class="photo-stage-heading">{{ $stageHeading }}</div>
    @endif

    @if (!empty($subHeading))
        <div class="photo-sub-heading">{{ $subHeading }}</div>
    @endif

    @php
        $photoPairs = array_chunk($photos, 2);
    @endphp

    @foreach ($photoPairs as $pair)
        @php
            $pairNotes = array_filter([$pair[0]['notes'] ?? null, $pair[1]['notes'] ?? null]);
            $maxNoteLen = 0;
            foreach ($pairNotes as $note) {
                $maxNoteLen = max($maxNoteLen, mb_strlen(trim((string) $note)));
            }

            if ($maxNoteLen > 240) {
                $tierImageClass = 'has-note-lg';
                $tierNoteClass = 'note-lg';
            } elseif ($maxNoteLen > 100) {
                $tierImageClass = 'has-note-md';
                $tierNoteClass = 'note-md';
            } else {
                $tierImageClass = 'has-note-sm';
                $tierNoteClass = 'note-sm';
            }
        @endphp
        <table class="photo-grid-table">
            <thead>
                <tr>
                    @for ($i = 0; $i < 2; $i++)
                        @php $item = $pair[$i] ?? null; @endphp
                        <th class="photo-cell-header">
                            @if ($item !== null)
                                @php
                                    $itemHeading = !empty($item['heading']) ? (string) $item['heading'] : (!empty($item['category']) ? (string) $item['category'] : 'Photo');
                                    $itemHeading = ucwords(strtolower($itemHeading));
                                    $numberSuffix = isset($item['photoNumber']) ? ' (' . $item['photoNumber'] . ')' : '';
                                @endphp
                                {{ $itemHeading }}{{ $numberSuffix }}
                            @endif
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                <tr>
                    @for ($i = 0; $i < 2; $i++)
                        @php $item = $pair[$i] ?? null; @endphp
                        <td class="photo-cell-td">
                            @if ($item !== null && !empty($item['path']))
                                @php $hasNote = !empty(trim((string) ($item['notes'] ?? ''))); @endphp
                                <div class="photo-cell-image-box {{ $hasNote ? $tierImageClass : '' }}">
                                    <img src="{{ $item['path'] }}" alt="{{ $item['heading'] ?? '' }}">
                                </div>
                                @if ($hasNote)
                                    <div class="photo-cell-note-box {{ $tierNoteClass }}">
                                        <span class="photo-note-label">Note:</span>{{ trim((string) $item['notes']) }}
                                    </div>
                                @endif
                            @else
                                <div class="photo-cell-image-box">
                                    <div style="color: #888888; font-style: italic; font-size: 13.5px; font-weight: bold;">Photo Not Available</div>
                                </div>
                            @endif
                        </td>
                    @endfor
                </tr>
            </tbody>
        </table>
    @endforeach

    @include('pdf.partials.footer', ['page' => $page])
</div>
