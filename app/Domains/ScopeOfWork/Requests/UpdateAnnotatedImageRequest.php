<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Requests;

use App\Domains\ScopeOfWork\Enums\AnnotationShape;
use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAnnotatedImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SowReport $report */
        $report = $this->route('report');

        return Gate::allows('update', $report);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'layer' => ['nullable', 'array'],
            'layer.base_width' => ['required_with:layer', 'integer', 'min:1'],
            'layer.nodes' => ['present_with:layer', 'array', 'max:300'],
            // Common fields — every node
            'layer.nodes.*.id' => ['required', 'string', 'max:36'],
            'layer.nodes.*.type' => ['required', Rule::enum(AnnotationShape::class)],
            'layer.nodes.*.z_index' => ['nullable', 'integer', 'min:0'],
            'layer.nodes.*.opacity' => ['nullable', 'numeric', 'between:0.2,1.0'],
            'layer.nodes.*.stroke_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'layer.nodes.*.fill_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'layer.nodes.*.norm_stroke_width' => ['nullable', 'numeric', 'between:0,0.02'],
            // Point-anchored coordinates (box, dot, text_label)
            'layer.nodes.*.norm_x' => ['nullable', 'numeric', 'between:0,1'],
            'layer.nodes.*.norm_y' => ['nullable', 'numeric', 'between:0,5'],
            // Box
            'layer.nodes.*.norm_width' => ['nullable', 'numeric', 'between:0,1'],
            'layer.nodes.*.norm_height' => ['nullable', 'numeric', 'between:0,5'],
            'layer.nodes.*.border_radius' => ['nullable', 'integer', 'between:0,50'], // Imagick only
            'layer.nodes.*.dashed' => ['nullable', 'boolean'],
            // Text fields
            'layer.nodes.*.text' => ['nullable', 'string', 'max:200'],
            'layer.nodes.*.norm_font_size' => ['nullable', 'numeric', 'between:0,0.1'],
            'layer.nodes.*.text_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'layer.nodes.*.font_weight' => ['nullable', 'string', Rule::in(['normal', 'bold'])],
            // Arrow / dashed line
            'layer.nodes.*.arrowhead_style' => ['nullable', 'string', Rule::in(['solid', 'open'])],
            'layer.nodes.*.norm_points' => ['nullable', 'array', 'max:40'],
            'layer.nodes.*.norm_points.*' => ['numeric', 'between:0,5'],
            'layer.nodes.*.dash_pattern' => ['nullable', 'array', 'size:2'],
            'layer.nodes.*.dash_pattern.*' => ['integer', 'between:1,50'],
            // Dot
            'layer.nodes.*.norm_radius' => ['nullable', 'numeric', 'between:0,0.1'],
            // Photo flags & concurrency
            'annotations_updated_at' => ['nullable', 'string', 'max:40'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'is_cover' => ['nullable', 'boolean'],
            'include_in_pdf' => ['nullable', 'boolean'],
        ];
    }

    /**
     * §4.5 shape-conditional norm_x / norm_y: line-based shapes (arrow, dashed_line)
     * derive position from norm_points; point-anchored shapes require norm_x/norm_y.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $nodes = data_get($this->validationData(), 'layer.nodes', []);
                if (! is_iterable($nodes)) {
                    return;
                }

                $boxCount = 0;
                foreach ($nodes as $index => $node) {
                    if (is_array($node) && ($node['type'] ?? '') === AnnotationShape::BOX->value) {
                        $boxCount++;
                    }
                    $this->validateNode($validator, $index, $node);
                }

                if ($boxCount > 5) {
                    $validator->errors()->add('layer.nodes', 'A maximum of 5 callout boxes is allowed per photo to maintain photo visibility.');
                }
            },
        ];
    }

    private function validateNode(Validator $validator, mixed $index, mixed $node): void
    {
        if (! is_array($node)) {
            return;
        }

        $indexStr = is_string($index) || is_int($index) ? (string) $index : '0';
        $type = is_string($node['type'] ?? null) ? $node['type'] : '';

        $this->validateNodeCoordinates($validator, $node, $indexStr, $type);
    }

    /**
     * @param  array<mixed, mixed>  $node
     */
    private function validateNodeCoordinates(
        Validator $validator,
        array $node,
        string $indexStr,
        string $type,
    ): void {
        $isLineBased = in_array($type, [AnnotationShape::ARROW->value, AnnotationShape::DASHED_LINE->value], true);

        foreach (['norm_x', 'norm_y'] as $key) {
            $hasValue = array_key_exists($key, $node) && $node[$key] !== null;
            $attr = "layer.nodes.{$indexStr}.{$key}";

            if ($isLineBased) {
                if ($hasValue) {
                    $validator->errors()->add($attr, 'Line-based annotations position via norm_points and must not carry '.$key.'.');
                }

                continue;
            }

            $max = $key === 'norm_x' ? 1.0 : 5.0;
            if (! $this->isValidCoordinate($node[$key] ?? null, $max)) {
                $validator->errors()->add($attr, $key.' is required and must be between 0 and '.$max.' for '.$type.' annotations.');
            }
        }
    }

    private function isValidCoordinate(mixed $value, float $max = 1.0): bool
    {
        return is_numeric($value) && (float) $value >= 0.0 && (float) $value <= $max;
    }
}
