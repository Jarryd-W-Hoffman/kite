<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Vault;
use App\Services\NoteExporter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Native\Desktop\Facades\System;
use Symfony\Component\HttpFoundation\Response;

final readonly class NoteExportController
{
    /**
     * Download the note as a standalone HTML page, or as a PDF in the desktop app.
     */
    public function show(Vault $vault, Note $note, string $format, NoteExporter $exporter): Response
    {
        $html = $exporter->page($note);
        $name = Str::of($note->title)->replaceMatches('/[\\\\\/:*?"<>|]+/u', ' ')->squish()->toString() ?: 'note';

        if ($format === 'html') {
            return response()->streamDownload(function () use ($html): void {
                echo $html;
            }, $name.'.html', ['Content-Type' => 'text/html; charset=utf-8']);
        }

        throw_unless(config('nativephp-internal.running'), ValidationException::withMessages([
            'format' => __('PDF export is available in the desktop app.'),
        ]));

        $pdf = base64_decode(System::printToPDF($html, [
            'printBackground' => true,
            'pageSize' => 'A4',
            'margins' => ['marginType' => 'default'],
        ]), true);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $name.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
