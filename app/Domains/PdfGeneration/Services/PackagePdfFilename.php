<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Models\PackageRevision;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * The client-facing filename of a generated close-out package PDF.
 *
 * One owner for downloads and email attachments, so a package is never called
 * two different things. The format lives in config('cop.pdf_filename_template')
 * and can be changed without code: reorder the {placeholders}, drop one, or add
 * another from {@see self::placeholder()}. Unknown placeholders stay literal so
 * a typo is visible in the name rather than silently disappearing, and the
 * result is always space-free with a .pdf extension.
 */
final class PackagePdfFilename
{
    /** The document's filename, e.g. "17676316-Brrancca-COP-09-20-2026-Rev2.0.pdf". */
    public static function for(CloseoutPackage $package, PackageRevision $revision): string
    {
        $configured = config('cop.pdf_filename_template');
        $template = is_string($configured) && $configured !== '' ? $configured : '{project}';

        $name = preg_replace_callback(
            '/\{([a-z_]+)\}/',
            fn (array $matches): string => self::placeholder($matches[1], $package, $revision) ?? $matches[0],
            $template,
        ) ?? $template;

        return self::sanitize($name).'.pdf';
    }

    /**
     * Headers that serve the stored file under its client-facing name.
     *
     * @return array<string, string>
     */
    public static function downloadHeaders(CloseoutPackage $package, PackageRevision $revision): array
    {
        return [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.self::for($package, $revision).'"',
        ];
    }

    /**
     * One template placeholder. Null means "not a placeholder", which leaves the
     * braces in the name instead of writing an empty string.
     */
    private static function placeholder(string $key, CloseoutPackage $package, PackageRevision $revision): ?string
    {
        $project = $package->project;

        return match ($key) {
            'ps_number' => $project?->ps_number,
            'project_number' => $project?->project_number,
            'project' => $project?->name,
            'market' => $project?->market?->name,
            'customer' => $project?->customer?->name,
            'package_id' => (string) $package->id,
            'revision' => $revision->revision_label ?? 'R'.$revision->revision_major.'.'.$revision->revision_minor,
            'major' => (string) $revision->revision_major,
            'minor' => (string) $revision->revision_minor,
            'date' => self::revisionDate($revision)->format('m-d-Y'),
            default => null,
        };
    }

    /** The date printed on the PDF, so the filename and the document agree. */
    private static function revisionDate(PackageRevision $revision): Carbon
    {
        return $revision->revision_date
            ?? $revision->generated_at
            ?? Carbon::now();
    }

    private static function sanitize(string $name): string
    {
        // Translocate first ("São Paulo" → "Sao Paulo"), then drop everything a
        // filename or a Content-Disposition header cannot carry.
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii($name)) ?? $name;
        // Placeholders that resolved to nothing leave a bare separator behind.
        $name = preg_replace('/-{2,}/', '-', $name) ?? $name;

        $trimmed = trim($name, '-_.');

        return $trimmed !== '' ? $trimmed : 'closeout-package';
    }
}
