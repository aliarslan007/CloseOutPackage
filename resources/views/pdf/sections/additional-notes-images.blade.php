<div class="page">
    @include('pdf.partials.header', [
        'logoPath' => $logoPath,
        'partnerLogoPath' => $partnerLogoPath,
        'siteInfo' => $siteInfo,
        'project' => $project,
        'package' => $package,
    ])

    <div class="page-title">
        {{ ucwords(strtolower((string) ($package->template?->name ?? 'Closeout'))) }} Additional Notes Supporting Images
        @if ($isContinuation)
            (Cont.)
        @endif
    </div>

    @php
        $photoPairs = $images->chunk(2);
    @endphp

    @foreach ($photoPairs as $pairRowIndex => $pair)
        <table class="photo-grid-table">
            <thead>
                <tr>
                    @for ($i = 0; $i < 2; $i++)
                        @php
                            $img = $pair->values()->get($i);
                            $imgNum = $startImageNumber + ($pairRowIndex * 2) + $i;
                        @endphp
                        <th class="photo-cell-header" style="width: 50%;">
                            @if ($img !== null)
                                Supporting Note Image #{{ $imgNum }}
                            @endif
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                <tr>
                    @for ($i = 0; $i < 2; $i++)
                        @php
                            $img = $pair->values()->get($i);
                        @endphp
                        <td class="photo-cell-td" style="width: 50%;">
                            @if ($img !== null)
                                @php
                                    $imgPath = $img->hasGeneratedConversion('pdf')
                                        ? $img->getPath('pdf')
                                        : $img->getPath();
                                    $isPackageNote = $img->collection_name === \App\Domains\CloseoutPackages\Models\CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION;
                                    $altText = $isPackageNote ? 'Package site note' : 'Additional site note image';
                                @endphp
                                <div class="photo-cell-image-box">
                                    <img src="{{ $imgPath }}" alt="{{ $altText }}" style="max-width: 100%; max-height: 322px; object-fit: contain;">
                                </div>
                            @else
                                <div class="photo-cell-image-box">
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
