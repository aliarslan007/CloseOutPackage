<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Services;

use App\Domains\ScopeOfWork\DTOs\SowTextBoxLayoutDTO;

final class SowTextBoxLayoutService
{
    private const PADDING_X = 16.0;

    private const PADDING_Y = 14.0;

    private const LINE_HEIGHT_RATIO = 1.28;

    private const CHAR_WIDTH_RATIO = 0.50;

    private const SAFETY_ROWS = 0.85;

    private const MIN_AVAILABLE_WIDTH = 20.0;

    private const DEFAULT_FONT_SIZE = 26.0;

    public function compute(
        string $text,
        float $width,
        float $height,
        float $targetFontSize,
        float $y,
        ?float $maxHeight = null,
        float $portraitScale = 1.0,
    ): SowTextBoxLayoutDTO {
        $cleanText = trim($text);
        if ($cleanText === '') {
            return new SowTextBoxLayoutDTO(
                fontSize: $targetFontSize,
                height: $height,
                y: $y,
                linesCount: 0,
                isExpanded: false,
            );
        }

        // Limit text length to 200 characters so callouts have ample room without covering the photo
        if (mb_strlen($cleanText) > 200) {
            $cleanText = mb_substr($cleanText, 0, 197).'...';
        }

        $padX = self::PADDING_X * $portraitScale;
        $padY = self::PADDING_Y * $portraitScale;
        $availW = max(self::MIN_AVAILABLE_WIDTH * $portraitScale, $width - $padX);
        $selectedFs = $this->determineOptimalFontSize(
            $targetFontSize,
            $portraitScale,
        );

        $lines = $this->estimateLines($cleanText, $availW, $selectedFs);
        $lineHeight = $selectedFs * self::LINE_HEIGHT_RATIO;
        $textHeight = ($lines * $lineHeight) + $padY;
        $safetyBuffer = $lineHeight * self::SAFETY_ROWS;
        $fittedHeight = ceil($textHeight + $safetyBuffer);

        // A callout box with text must snugly fit its content without leaving
        // massive empty white areas below the text.
        $selectedHeight = (float) $fittedHeight;
        if ($maxHeight !== null) {
            $selectedHeight = min(max(30.0, $maxHeight - 10.0), $selectedHeight);
        }

        $adjustedY = $y;
        if ($maxHeight !== null && ($adjustedY + $selectedHeight) > $maxHeight) {
            $adjustedY = max(0.0, $maxHeight - $selectedHeight - 4.0);
        }

        return new SowTextBoxLayoutDTO(
            fontSize: $selectedFs,
            height: $selectedHeight,
            y: $adjustedY,
            linesCount: $lines,
            isExpanded: $selectedHeight !== $height,
        );
    }

    /**
     * Resolve overlaps between callout boxes so no two boxes cover each other on the PDF.
     *
     * @param  list<array{x: float, y: float, w: float, h: float, node: array<string, mixed>, layout: SowTextBoxLayoutDTO}>  $boxes
     * @return list<array{x: float, y: float, w: float, h: float, node: array<string, mixed>, layout: SowTextBoxLayoutDTO}>
     */
    public function resolveCollisions(
        array $boxes,
        int $baseWidth,
        ?int $logicalHeight,
        float $portraitScale = 1.0,
    ): array {
        $count = count($boxes);
        if ($count <= 1) {
            return $boxes;
        }

        $gap = 10.0 * $portraitScale;
        $maxH = $logicalHeight !== null ? (float) $logicalHeight : ($baseWidth * 0.75);

        for ($iter = 0; $iter < 4; $iter++) {
            $hadCollision = false;

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $b1 = &$boxes[$i];
                    $b2 = &$boxes[$j];

                    $overlapX = min($b1['x'] + $b1['w'] + $gap, $b2['x'] + $b2['w'] + $gap) - max($b1['x'], $b2['x']);
                    $overlapY = min($b1['y'] + $b1['h'] + $gap, $b2['y'] + $b2['h'] + $gap) - max($b1['y'], $b2['y']);

                    if ($overlapX > 0 && $overlapY > 0) {
                        $hadCollision = true;

                        if ($b1['y'] <= $b2['y']) {
                            $b2['y'] = $b1['y'] + $b1['h'] + $gap;
                            if (($b2['y'] + $b2['h']) > $maxH) {
                                $b2['y'] = max(0.0, $b1['y'] - $b2['h'] - $gap);
                            }
                        } else {
                            $b1['y'] = $b2['y'] + $b2['h'] + $gap;
                            if (($b1['y'] + $b1['h']) > $maxH) {
                                $b1['y'] = max(0.0, $b2['y'] - $b1['h'] - $gap);
                            }
                        }

                        $b1['y'] = max(0.0, min($maxH - $b1['h'], $b1['y']));
                        $b2['y'] = max(0.0, min($maxH - $b2['h'], $b2['y']));
                        $b1['x'] = max(0.0, min((float) $baseWidth - $b1['w'], $b1['x']));
                        $b2['x'] = max(0.0, min((float) $baseWidth - $b2['w'], $b2['x']));
                    }
                }
            }

            if (! $hadCollision) {
                break;
            }
        }

        return $boxes;
    }

    private function determineOptimalFontSize(
        float $targetFontSize,
        float $portraitScale,
    ): float {
        $target = $targetFontSize > 0 ? $targetFontSize : self::DEFAULT_FONT_SIZE;
        $scaled = $target * $portraitScale;

        return max(22.0 * $portraitScale, min(65.0 * $portraitScale, $scaled));
    }

    private function estimateLines(string $text, float $availW, float $fontSize): int
    {
        $charWidth = max(4.0, $fontSize * self::CHAR_WIDTH_RATIO);
        $maxCharsPerLine = max(5, (int) floor($availW / $charWidth));

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = explode("\n", $normalized);
        $totalLines = 0;

        foreach ($paragraphs as $para) {
            $totalLines += $this->countParagraphLines($para, $maxCharsPerLine);
        }

        return max(1, $totalLines);
    }

    private function countParagraphLines(string $para, int $maxCharsPerLine): int
    {
        $trimmed = trim($para);
        if ($trimmed === '') {
            return 1;
        }

        $words = preg_split('/\s+/', $trimmed) ?: [];
        $lineLen = 0;
        $lines = 1;

        foreach ($words as $word) {
            $wordLen = strlen($word);
            if ($lineLen === 0) {
                $lineLen = $wordLen;
            } elseif (($lineLen + 1 + $wordLen) > $maxCharsPerLine) {
                $lines++;
                $lineLen = $wordLen;
            } else {
                $lineLen += 1 + $wordLen;
            }
        }

        return $lines;
    }
}
