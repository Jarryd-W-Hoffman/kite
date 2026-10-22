<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\AttachmentTypes;
use App\Services\LocalTime;
use App\Services\VaultFilesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class StoreAttachment
{
    public function __construct(
        private VaultFilesystem $files,
        private AttachmentTypes $types,
        private IndexAttachments $indexAttachments,
        private LocalTime $time,
    ) {
        //
    }

    /**
     * Copy an uploaded file into the vault's attachment folder and return the embed that references it.
     *
     * @return array{path: string, embed: string}
     */
    public function handle(Vault $vault, UploadedFile $file): array
    {
        $extension = $this->types->extension($file->getClientOriginalName()) ?: mb_strtolower((string) $file->extension());
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = mb_trim((string) preg_replace('/[\\\\\/:*?"<>|#^\[\]\x00-\x1F]+/u', ' ', $name));
        $name = mb_trim((string) preg_replace('/\s+/u', ' ', $name), ' .') ?: 'Pasted '.$this->time->now()->format('Y-m-d His');

        $folder = $this->types->folder();
        $prefix = $folder === '' ? '' : $folder.'/';
        $path = "{$prefix}{$name}.{$extension}";

        for ($suffix = 1; $this->files->exists($vault, $path); $suffix++) {
            $path = "{$prefix}{$name} {$suffix}.{$extension}";
        }

        $this->files->import($vault, $path, $file->getRealPath());
        $this->indexAttachments->handle($vault);

        $isUnique = DB::table('attachments')
            ->where('vault_id', $vault->id)
            ->where('name_key', mb_strtolower(basename($path)))
            ->count() === 1;

        return [
            'path' => $path,
            'embed' => '![['.($isUnique ? basename($path) : $path).']]',
        ];
    }
}
