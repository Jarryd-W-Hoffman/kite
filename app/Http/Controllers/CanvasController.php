<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DescribeCanvasFiles;
use App\Actions\LoadWorkspace;
use App\Actions\RenderMarkdown;
use App\Http\Requests\SaveCanvasRequest;
use App\Http\Requests\StoreCanvasRequest;
use App\Models\Vault;
use App\Services\Canvases;
use App\Services\VaultFilesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final readonly class CanvasController
{
    public function __construct(
        private Canvases $canvases,
        private VaultFilesystem $files,
    ) {
        //
    }

    public function store(StoreCanvasRequest $request, Vault $vault): RedirectResponse
    {
        try {
            $path = $this->canvases->create(
                $vault,
                $request->filled('name') ? $request->string('name')->toString() : 'Untitled',
                $request->filled('folder') ? $request->string('folder')->toString() : null,
            );
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['folder' => __('That folder is not inside this vault.')]);
        }

        return to_route('canvases.show', [$vault, $path]);
    }

    /**
     * A canvas with what its file cards show. One that can't be read is shown as such, never as
     * an empty canvas that a save would overwrite.
     */
    public function show(Vault $vault, string $path, LoadWorkspace $workspace, DescribeCanvasFiles $describe, RenderMarkdown $renderMarkdown): Response
    {
        $path = $this->existingCanvas($vault, $path);

        try {
            $canvas = $this->canvases->read($vault, $path);
        } catch (InvalidArgumentException) {
            $canvas = null;
        }

        $files = [];
        $texts = [];

        foreach ($canvas['nodes'] ?? [] as $node) {
            if (($node['type'] ?? null) === 'file' && is_string($node['file'] ?? null)) {
                $files[] = $node['file'];
            } elseif (($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null) && is_string($node['id'] ?? null)) {
                $texts[$node['id']] = $renderMarkdown->handle($vault, $node['text']);
            }
        }

        return Inertia::render('canvas/show', [
            ...$workspace->handle($vault),
            'canvas' => [
                'path' => $path,
                'name' => $this->canvases->name($path),
                'hash' => $canvas['hash'] ?? null,
                'readable' => $canvas !== null,
                'nodes' => $canvas['nodes'] ?? [],
                'edges' => $canvas['edges'] ?? [],
            ],
            'files' => (object) $describe->handle($vault, $files),
            // Text cards as HTML, by card id.
            'texts' => (object) $texts,
        ]);
    }

    /**
     * Save the whole canvas, unless the file changed since the window loaded it.
     */
    public function update(SaveCanvasRequest $request, Vault $vault, string $path): JsonResponse
    {
        $path = $this->existingCanvas($vault, $path);

        try {
            ['extra' => $extra, 'hash' => $hash] = $this->canvases->read($vault, $path);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => __('This canvas can’t be read, so it wasn’t saved.')], 409);
        }

        if ($hash !== $request->input('base_hash')) {
            return response()->json(['message' => __('This canvas changed on disk. Reload it to see the changes.')], 409);
        }

        // Keep anything else at the top of the file, such as another app's settings.
        $hash = $this->canvases->write($vault, $path, $request->items('nodes'), $request->items('edges'), $extra);

        return response()->json(['hash' => $hash]);
    }

    public function destroy(Vault $vault, string $path): RedirectResponse
    {
        $path = $this->existingCanvas($vault, $path);

        $this->files->trash($vault, $path);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->files->usesTrash()
                ? __('Moved “:name” to the trash.', ['name' => $this->canvases->name($path)])
                : __('Deleted “:name”.', ['name' => $this->canvases->name($path)]),
        ]);

        return to_route('vaults.show', $vault);
    }

    private function existingCanvas(Vault $vault, string $path): string
    {
        try {
            $path = $this->files->normalise($path);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_unless($this->canvases->isCanvas($path) && $this->files->exists($vault, $path), 404);

        return $path;
    }
}
