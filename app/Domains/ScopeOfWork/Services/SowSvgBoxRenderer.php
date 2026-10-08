<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Services;

use App\Domains\ScopeOfWork\DTOs\SowTextBoxLayoutDTO;

final class SowSvgBoxRenderer
{
    public function __construct(
        private readonly SowTextBoxLayoutService $layoutService = new SowTextBoxLayoutService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $boxNodes
     * @return list<string>
     */
    public function renderAll(
        array $boxNodes,
        int $baseWidth,
        ?int $logicalHeight,
        float $portraitScale,
    ): array {
        if (empty($boxNodes)) {
            return [];
        }

        $prepared = [];
        foreach ($boxNodes as $node) {
            $x = (float) ($node['norm_x'] ?? $node['normX'] ?? 0) * $baseWidth;
            $y = (float) ($node['norm_y'] ?? $node['normY'] ?? 0) * $baseWidth;
            $w = max(1.0, (float) ($node['norm_width'] ?? $node['normWidth'] ?? 0.1) * $baseWidth);
            $h = max(1.0, (float) ($node['norm_height'] ?? $node['normHeight'] ?? 0.1) * $baseWidth);
            $text = trim((string) ($node['text'] ?? ''));

            if ($text !== '') {
                $minBoxW = min((float) $baseWidth * 0.15, 120.0 * $portraitScale);
                $w = min((float) $baseWidth - $x, max($w, $minBoxW));
                if (($x + $w) > $baseWidth) {
                    $x = max(0.0, (float) $baseWidth - $w);
                }
            }

            $layout = $this->layoutService->compute(
                text: $text,
                width: $w,
                height: $h,
                targetFontSize: (float) ($node['norm_font_size'] ?? $node['normFontSize'] ?? 0.021667) * $baseWidth,
                y: $y,
                maxHeight: $logicalHeight !== null ? (float) $logicalHeight : null,
                portraitScale: $portraitScale,
            );

            $prepared[] = [
                'x' => $x,
                'y' => $layout->y,
                'w' => $w,
                'h' => $layout->height,
                'node' => $node,
                'layout' => $layout,
            ];
        }

        $resolved = $this->layoutService->resolveCollisions($prepared, $baseWidth, $logicalHeight, $portraitScale);

        $rendered = [];
        foreach ($resolved as $item) {
            $rendered[] = $this->formatBoxSvg($item, $baseWidth, $portraitScale);
        }

        return $rendered;
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float, node: array<string, mixed>, layout: SowTextBoxLayoutDTO}  $item
     */
    private function formatBoxSvg(array $item, int $baseWidth, float $portraitScale): string
    {
        $node = $item['node'];
        $x = $item['x'];
        $y = $item['y'];
        $w = $item['w'];
        $h = $item['h'];
        $layout = $item['layout'];

        $stroke = htmlspecialchars((string) ($node['stroke_color'] ?? $node['strokeColor'] ?? '#EF4444'), ENT_QUOTES);
        $fill = '#FFFFFF';
        $strokeWidth = max(2.0, (float) ($node['norm_stroke_width'] ?? $node['normStrokeWidth'] ?? 0.0033) * $baseWidth * $portraitScale);
        $opacity = (float) ($node['opacity'] ?? 1.0);
        $dashed = ! empty($node['dashed']) ? ' stroke-dasharray="8,6"' : '';
        $text = trim((string) ($node['text'] ?? ''));
        $rx = max(1, (int) round(2 * $portraitScale));

        $out = sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" stroke="%s" stroke-width="%.2f" fill="%s" fill-opacity="%.2f" stroke-opacity="%.2f"%s rx="%d" />',
            $x,
            $y,
            $w,
            $h,
            $stroke,
            $strokeWidth,
            $fill,
            max(0.95, $opacity),
            $opacity,
            $dashed,
            $rx
        );

        if ($text !== '') {
            $displayText = mb_strlen($text) > 200 ? mb_substr($text, 0, 197).'...' : $text;
            $safeText = nl2br(htmlspecialchars($displayText, ENT_QUOTES));
            $padY = max(6, (int) round(8 * $portraitScale));
            $padX = max(8, (int) round(10 * $portraitScale));

            $out .= sprintf(
                '<foreignObject x="%.2f" y="%.2f" width="%.2f" height="%.2f"><div xmlns="http://www.w3.org/1999/xhtml" style="width: 100%%; height: 100%%; padding: %dpx %dpx; box-sizing: border-box; font-family: Arial, \'Helvetica Neue\', Helvetica, \'Segoe UI\', Roboto, sans-serif; font-size: %.2fpx; font-weight: 700; color: #000000; line-height: 1.28; word-break: break-word; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility;">%s</div></foreignObject>',
                $x,
                $y,
                $w,
                $h,
                $padY,
                $padX,
                $layout->fontSize,
                $safeText
            );
        }

        return $out;
    }
}
