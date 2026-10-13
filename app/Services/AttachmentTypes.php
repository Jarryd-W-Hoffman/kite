<?php

declare(strict_types=1);

namespace App\Services;

final readonly class AttachmentTypes
{
    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return $this->list('kite.attachments.extensions');
    }

    /**
     * @return list<string>
     */
    public function imageExtensions(): array
    {
        return $this->list('kite.attachments.image_extensions');
    }

    public function isAttachment(string $path): bool
    {
        return in_array($this->extension($path), $this->extensions(), true);
    }

    public function isImage(string $path): bool
    {
        return in_array($this->extension($path), $this->imageExtensions(), true);
    }

    public function extension(string $path): string
    {
        return mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    public function folder(): string
    {
        $folder = config('kite.attachments.folder');

        return is_string($folder) ? mb_trim($folder, '/') : 'attachments';
    }

    public function maxUploadKilobytes(): int
    {
        $limit = config('kite.attachments.max_upload_kilobytes');

        return is_int($limit) ? $limit : 51200;
    }

    /**
     * @return list<string>
     */
    private function list(string $key): array
    {
        $values = config($key);

        return is_array($values)
            ? array_values(array_map(mb_strtolower(...), array_filter($values, is_string(...))))
            : [];
    }
}
