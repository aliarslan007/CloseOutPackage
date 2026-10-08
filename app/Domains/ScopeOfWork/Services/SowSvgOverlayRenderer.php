<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Services;

final class SowSvgOverlayRenderer
{
    public function __construct(
        private readonly SowSvgBoxRenderer $boxRenderer = new SowSvgBoxRenderer,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    public function render(
        array $nodes,
        int $baseWidth,
        ?int $logicalHeight = null,
        float $portraitScale = 1.0,
    ): string {
        if (empty($nodes)) {
            return '';
        }

        $boxNodes = [];
        $arrowNodes = [];
        $dashedNodes = [];
        $dotNodes = [];
        $labelNodes = [];

        foreach ($nodes as $node) {
            $type = (string) ($node['type'] ?? '');
            match ($type) {
                'box' => $boxNodes[] = $node,
                'arrow' => $arrowNodes[] = $node,
                'dashed_line', 'dashed' => $dashedNodes[] = $node,
                'dot', 'pin' => $dotNodes[] = $node,
                'text_label', 'text' => $labelNodes[] = $node,
                default => null,
            };
        }

        $markers = [
            'default' => '<marker id="sow-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1 L 10 5 L 0 9 z" fill="#EF4444" /></marker>',
        ];

        foreach ($arrowNodes as $arrow) {
            $col = (string) ($arrow['stroke_color'] ?? $arrow['strokeColor'] ?? '#EF4444');
            $key = substr(md5($col), 0, 8);
            if (! isset($markers[$key])) {
                $halo = $this->getContrastHaloColor($col);
                $markers[$key] = sprintf(
                    '<marker id="sow-arrow-%s" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1 L 10 5 L 0 9 z" fill="%s" stroke="%s" stroke-width="0.8" /></marker>',
                    $key,
                    htmlspecialchars($col, ENT_QUOTES),
                    $halo
                );
            }
        }

        $svgElements = array_merge(['<defs>'], array_values($markers), ['</defs>']);

        // 1. Site boundary / dashed lines
        foreach ($dashedNodes as $node) {
            $svgElements[] = $this->renderDashedLine($node, $baseWidth, $portraitScale);
        }

        // 2. Leader arrows (rendered before boxes so arrow lines tuck cleanly under callout boxes)
        foreach ($arrowNodes as $node) {
            $svgElements[] = $this->renderArrow($node, $baseWidth, $portraitScale);
        }

        // 3. Dots / pins
        foreach ($dotNodes as $node) {
            $svgElements[] = $this->renderDot($node, $baseWidth, $portraitScale);
        }

        // 4. Callout boxes with anti-collision layout
        foreach ($this->boxRenderer->renderAll($boxNodes, $baseWidth, $logicalHeight, $portraitScale) as $boxSvg) {
            $svgElements[] = $boxSvg;
        }

        // 5. Standalone text labels
        foreach ($labelNodes as $node) {
            $svgElements[] = $this->renderTextLabel($node, $baseWidth, $portraitScale);
        }

        return implode("\n", array_filter($svgElements, fn ($el) => trim($el) !== ''));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderArrow(array $node, int $baseWidth, float $portraitScale = 1.0): string
    {
        $points = is_array($node['norm_points'] ?? null) ? $node['norm_points'] : ($node['normPoints'] ?? []);
        if (! is_array($points) || count($points) < 4) {
            return '';
        }

        $x0 = (float) $points[0] * $baseWidth;
        $y0 = (float) $points[1] * $baseWidth;
        $x1 = (float) $points[2] * $baseWidth;
        $y1 = (float) $points[3] * $baseWidth;
        $rawStroke = (string) ($node['stroke_color'] ?? $node['strokeColor'] ?? '#EF4444');
        $stroke = htmlspecialchars($rawStroke, ENT_QUOTES);
        $strokeWidth = max(3.0, (float) ($node['norm_stroke_width'] ?? $node['normStrokeWidth'] ?? 0.004) * $baseWidth * $portraitScale);
        $haloColor = $this->getContrastHaloColor($rawStroke);
        $underWidth = $strokeWidth + max(2.5, 2.5 * $portraitScale);
        $markerKey = substr(md5($rawStroke), 0, 8);

        return sprintf(
            '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="%s" stroke-width="%.2f" stroke-linecap="round" stroke-opacity="0.85" />'."\n".
            '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="%s" stroke-width="%.2f" stroke-linecap="round" marker-end="url(#sow-arrow-%s)" />',
            $x0, $y0, $x1, $y1, $haloColor, $underWidth,
            $x0, $y0, $x1, $y1, $stroke, $strokeWidth, $markerKey
        );
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderDashedLine(array $node, int $baseWidth, float $portraitScale = 1.0): string
    {
        $points = is_array($node['norm_points'] ?? null) ? $node['norm_points'] : ($node['normPoints'] ?? []);
        if (! is_array($points) || count($points) < 4) {
            return '';
        }

        $formatted = [];
        for ($i = 0; $i < count($points) - 1; $i += 2) {
            $px = (float) $points[$i] * $baseWidth;
            $py = (float) $points[$i + 1] * $baseWidth;
            $formatted[] = sprintf('%.2f,%.2f', $px, $py);
        }

        $rawStroke = (string) ($node['stroke_color'] ?? $node['strokeColor'] ?? '#F59E0B');
        $stroke = htmlspecialchars($rawStroke, ENT_QUOTES);
        $strokeWidth = max(3.0, (float) ($node['norm_stroke_width'] ?? $node['normStrokeWidth'] ?? 0.003) * $baseWidth * $portraitScale);
        $haloColor = $this->getContrastHaloColor($rawStroke);
        $underWidth = $strokeWidth + max(2.5, 2.5 * $portraitScale);
        $dash = sprintf('%.1f,%.1f', 8.0 * $portraitScale, 6.0 * $portraitScale);
        $pointsStr = implode(' ', $formatted);

        return sprintf(
            '<polyline points="%s" stroke="%s" stroke-width="%.2f" stroke-dasharray="%s" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.85" fill="none" />'."\n".
            '<polyline points="%s" stroke="%s" stroke-width="%.2f" stroke-dasharray="%s" stroke-linecap="round" stroke-linejoin="round" fill="none" />',
            $pointsStr, $haloColor, $underWidth, $dash,
            $pointsStr, $stroke, $strokeWidth, $dash
        );
    }

    private function getContrastHaloColor(string $hexColor): string
    {
        $clean = ltrim($hexColor, '#');
        if (strlen($clean) === 6) {
            $r = hexdec(substr($clean, 0, 2));
            $g = hexdec(substr($clean, 2, 2));
            $b = hexdec(substr($clean, 4, 2));
            $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255.0;

            return $luminance < 0.25 ? '#FFFFFF' : '#000000';
        }

        return '#000000';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderDot(array $node, int $baseWidth, float $portraitScale = 1.0): string
    {
        $cx = (float) ($node['norm_x'] ?? $node['normX'] ?? 0) * $baseWidth;
        $cy = (float) ($node['norm_y'] ?? $node['normY'] ?? 0) * $baseWidth;
        $r = max(4.0, (float) ($node['norm_radius'] ?? $node['normRadius'] ?? 0.008) * $baseWidth * $portraitScale);
        $color = htmlspecialchars((string) ($node['stroke_color'] ?? $node['strokeColor'] ?? '#10B981'), ENT_QUOTES);
        $strokeW = max(2.0, 2.0 * $portraitScale);

        return sprintf(
            '<circle cx="%.2f" cy="%.2f" r="%.2f" fill="%s" stroke="#FFFFFF" stroke-width="%.2f" />',
            $cx,
            $cy,
            $r,
            $color,
            $strokeW
        );
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderTextLabel(array $node, int $baseWidth, float $portraitScale = 1.0): string
    {
        $x = (float) ($node['norm_x'] ?? $node['normX'] ?? 0) * $baseWidth;
        $y = (float) ($node['norm_y'] ?? $node['normY'] ?? 0) * $baseWidth;
        $text = trim((string) ($node['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $fontSize = max(20.0 * $portraitScale, (float) ($node['norm_font_size'] ?? $node['normFontSize'] ?? 0.018) * $baseWidth * $portraitScale);
        $color = htmlspecialchars((string) ($node['text_color'] ?? $node['textColor'] ?? '#FFFFFF'), ENT_QUOTES);
        $safeText = htmlspecialchars($text, ENT_QUOTES);
        $strokeW = max(2.0, 2.0 * $portraitScale);

        return sprintf(
            '<text x="%.2f" y="%.2f" font-size="%.2f" fill="%s" stroke="#000000" stroke-width="%.2f" paint-order="stroke" stroke-linejoin="round" font-family="\'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif" font-weight="600">%s</text>',
            $x,
            $y + $fontSize,
            $fontSize,
            $color,
            $strokeW,
            $safeText
        );
    }
}
