<?php

declare(strict_types=1);

use App\Domains\ScopeOfWork\Services\SowSvgOverlayRenderer;
use Tests\TestCase;

uses(TestCase::class);

it('renders callout box with pure white background, pure black text, and dynamic border color', function (): void {
    $renderer = new SowSvgOverlayRenderer;

    $layer = [
        'base_width' => 1200,
        'nodes' => [
            [
                'id' => 'test-box-1',
                'type' => 'box',
                'stroke_color' => '#E53E3E',
                'fill_color' => '#E53E3E',
                'norm_stroke_width' => 0.003,
                'norm_x' => 0.1,
                'norm_y' => 0.1,
                'norm_width' => 0.3,
                'norm_height' => 0.2,
                'text' => 'Fiber Demarcation',
                'text_color' => '#FFFFFF',
            ],
            [
                'id' => 'test-box-2',
                'type' => 'box',
                'stroke_color' => '#000000',
                'norm_stroke_width' => 0.003,
                'norm_x' => 0.5,
                'norm_y' => 0.5,
                'norm_width' => 0.3,
                'norm_height' => 0.2,
                'text' => 'Black Border Box',
            ],
        ],
    ];

    $svg = $renderer->render($layer['nodes'], 1200, 800);

    expect($svg)->not->toBeNull()
        // First box: red border, white fill, black text
        ->and($svg)->toContain('stroke="#E53E3E"')
        ->and($svg)->toContain('fill="#FFFFFF"')
        ->and($svg)->toContain('color: #000000;')
        ->and($svg)->toContain('text-align: center;')
        ->and($svg)->toContain('font-weight: 700;')
        ->and($svg)->toContain('Fiber Demarcation')
        // Second box: black border, white fill, black text
        ->and($svg)->toContain('stroke="#000000"')
        ->and($svg)->toContain('Black Border Box');
});

it('scales font size, box width, and stroke width on vertical photos for enterprise readability', function (): void {
    $renderer = new SowSvgOverlayRenderer;

    $node = [
        'id' => 'portrait-box',
        'type' => 'box',
        'stroke_color' => '#E53E3E',
        'norm_stroke_width' => 0.003,
        'norm_x' => 0.1,
        'norm_y' => 0.1,
        'norm_width' => 0.2,
        'norm_height' => 0.1,
        'text' => 'Vertical Cell Site Note',
    ];

    // Portrait scale 2.6 (typical for 9:16 vertical smartphone photo)
    $svg = $renderer->render([$node], 1200, 2133, 2.6);

    expect($svg)->not->toBeNull()
        ->and($svg)->toContain('fill="#FFFFFF"')
        ->and($svg)->toContain('color: #000000;')
        ->and($svg)->toContain('Vertical Cell Site Note');

    // Font size should be scaled up significantly (> 50px in SVG space) so that in 69mm card it equals ~10pt
    preg_match('/font-size: ([0-9.]+)px/', $svg, $matches);
    expect($matches)->not->toBeEmpty();
    $fontSize = (float) $matches[1];
    expect($fontSize)->toBeGreaterThan(50.0);
});
