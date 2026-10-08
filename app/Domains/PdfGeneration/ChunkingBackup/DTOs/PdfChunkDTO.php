<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\DTOs;

/**
 * A bounded slice of the document: one standalone HTML document's worth of
 * pages, spanning specific global page numbers. Chunks never reflow content;
 * page numbering comes from the same computation the legacy path uses.
 */
final class PdfChunkDTO
{
    /**
     * @param  list<string>  $sectionHtmls  ordered section HTML fragments
     *                                      (exactly one is non-empty per chunk)
     */
    public function __construct(
        public readonly string $id,
        public readonly int $firstPage,
        public readonly int $lastPage,
        public readonly array $sectionHtmls,
    ) {}

    public function html(): string
    {
        return implode('', $this->sectionHtmls);
    }
}
