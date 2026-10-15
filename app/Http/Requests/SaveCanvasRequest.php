<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A whole canvas in the JSON Canvas 1.0 format. Fields the format doesn't define are kept as
 * they are, so other apps' additions survive a save.
 */
final class SaveCanvasRequest extends FormRequest
{
    private const string COLOR = 'regex:/^([1-6]|#[0-9a-fA-F]{6})$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'base_hash' => ['present', 'nullable', 'string', 'max:64'],
            'nodes' => ['present', 'array', 'max:5000'],
            'nodes.*' => ['array'],
            'nodes.*.id' => ['required', 'string', 'max:128'],
            'nodes.*.type' => ['required', 'in:text,file,link,group'],
            'nodes.*.x' => ['required', 'numeric'],
            'nodes.*.y' => ['required', 'numeric'],
            'nodes.*.width' => ['required', 'numeric', 'min:1'],
            'nodes.*.height' => ['required', 'numeric', 'min:1'],
            'nodes.*.color' => ['nullable', 'string', self::COLOR],
            'nodes.*.text' => ['present_if:nodes.*.type,text', 'nullable', 'string', 'max:200000'],
            'nodes.*.file' => ['required_if:nodes.*.type,file', 'nullable', 'string', 'max:1024'],
            'nodes.*.subpath' => ['nullable', 'string', 'max:1024'],
            'nodes.*.url' => ['required_if:nodes.*.type,link', 'nullable', 'string', 'max:2048'],
            'nodes.*.label' => ['nullable', 'string', 'max:1000'],
            'nodes.*.background' => ['nullable', 'string', 'max:1024'],
            'nodes.*.backgroundStyle' => ['nullable', 'in:cover,ratio,repeat'],
            'edges' => ['present', 'array', 'max:10000'],
            'edges.*' => ['array'],
            'edges.*.id' => ['required', 'string', 'max:128'],
            'edges.*.fromNode' => ['required', 'string', 'max:128'],
            'edges.*.toNode' => ['required', 'string', 'max:128'],
            'edges.*.fromSide' => ['nullable', 'in:top,right,bottom,left'],
            'edges.*.toSide' => ['nullable', 'in:top,right,bottom,left'],
            'edges.*.fromEnd' => ['nullable', 'in:none,arrow'],
            'edges.*.toEnd' => ['nullable', 'in:none,arrow'],
            'edges.*.color' => ['nullable', 'string', self::COLOR],
            'edges.*.label' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Ids are unique, and every edge joins two cards on the canvas.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $nodes = array_values(array_filter(array_column($this->items('nodes'), 'id'), is_string(...)));
                $edges = array_values(array_filter(array_column($this->items('edges'), 'id'), is_string(...)));

                if (count(array_unique([...$nodes, ...$edges])) !== count($nodes) + count($edges)) {
                    $validator->errors()->add('nodes', __('Every card and connection needs its own id.'));
                }

                foreach ($this->items('edges') as $index => $edge) {
                    if ((! in_array($edge['fromNode'] ?? null, $nodes, true) || ! in_array($edge['toNode'] ?? null, $nodes, true))) {
                        $validator->errors()->add("edges.{$index}", __('A connection joins a card that isn’t on the canvas.'));
                    }
                }
            },
        ];
    }

    /**
     * The cards or connections sent, each as its fields.
     *
     * @return list<array<array-key, mixed>>
     */
    public function items(string $key): array
    {
        return array_values(array_filter($this->array($key), is_array(...)));
    }
}
