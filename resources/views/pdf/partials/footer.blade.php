<div class="pdf-footer-wrapper">
    <div class="pdf-footer">
        <div class="pdf-footer-left">
            {{ $page->pageNumber }} ({{ $page->totalPages }})
        </div>
        <div class="pdf-footer-center">
            <div class="proprietary-badge">
                <div class="proprietary-title">Verizon Wireless Proprietary Information</div>
                <div class="powered-by">Prepared by <a href="https://www.solucomp.com" target="_blank" rel="noopener noreferrer">www.SoluComp.com</a></div>
            </div>
        </div>
        <div class="pdf-footer-right">
            {{ $page->revisionLabel }} &nbsp;&nbsp; {{ $page->revisionDate->format('n/j/Y') }}
        </div>
    </div>
</div>
