<div class="page">
    @include('pdf.partials.header', [
        'logoPath' => $logoPath,
        'partnerLogoPath' => $partnerLogoPath,
        'siteInfo' => $siteInfo,
        'project' => $project,
        'package' => $package
    ])

    <div class="page-title">Required Pictures</div>

    @php $counter = $startCounter ?? 1; @endphp
    @foreach ($sections as $section)
        @php
            $categories = is_array($section) ? ($section['categories'] ?? []) : $section->categories;
            $heading = is_array($section) ? ($section['heading'] ?? '') : $section->heading;
        @endphp
        @if (count($categories) > 0)
            <div class="required-stage-group">
                <div class="required-stage-header">{{ $heading }}</div>
                <ul class="required-pictures-list">
                    @foreach ($categories as $category)
                        @php
                            $catName = is_array($category) ? ($category['name'] ?? '') : $category->name;
                        @endphp
                        <li><span class="num-bold">{{ $counter++ }}.</span>{{ $catName }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach

    @include('pdf.partials.footer', ['page' => $page])
</div>
