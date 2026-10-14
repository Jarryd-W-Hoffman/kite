<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vault;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;

final readonly class VaultFilesystem
{
    /**
     * Normalise a vault-relative path, rejecting anything that could escape the vault.
     */
    public function normalise(string $path): string
    {
        $path = mb_trim(str_replace('\\', '/', $path));

        throw_if(
            $path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[a-z]:/i', $path) === 1,
            InvalidArgumentException::class,
            "The path [{$path}] is not a valid vault path.",
        );

        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== '' && $segment !== '.'));

        foreach ($segments as $segment) {
            throw_if(
                $segment === '..' || str_starts_with($segment, '.'),
                InvalidArgumentException::class,
                "The path [{$path}] is not a valid vault path.",
            );
        }

        return implode('/', $segments);
    }

    public function absolutePath(Vault $vault, string $path): string
    {
        return $vault->path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->normalise($path));
    }

    /**
     * @return array<string, array{size: int, modified_at: int}>
     */
    public function markdownFiles(Vault $vault): array
    {
        return $this->filesMatching($vault, '/\.md$/i');
    }

    /**
     * Files with one of the given extensions, e.g. images and PDFs referenced from notes.
     *
     * @param  list<string>  $extensions
     * @return array<string, array{size: int, modified_at: int}>
     */
    public function filesWithExtensions(Vault $vault, array $extensions): array
    {
        if ($extensions === []) {
            return [];
        }

        $pattern = implode('|', array_map(fn (string $extension): string => preg_quote($extension, '/'), $extensions));

        return $this->filesMatching($vault, '/\.('.$pattern.')$/i');
    }

    /**
     * @return list<string>
     */
    public function ignoredFolders(): array
    {
        $folders = config('kite.ignored_folders');

        return is_array($folders)
            ? array_values(array_filter(array_map(fn (mixed $folder): string => is_string($folder) ? mb_trim($folder, " \t/") : '', $folders)))
            : [];
    }

    /**
     * Whether a vault-relative path falls inside one of the ignored folders.
     */
    public function isIgnored(string $path): bool
    {
        return array_any($this->ignoredFolders(), fn (string $folder): bool => $path === $folder || str_starts_with($path, $folder.'/') || in_array($folder, explode('/', $path), true));
    }

    /**
     * Every folder in the vault, including empty ones, skipping dot-folders and ignored folders.
     *
     * @return list<string>
     */
    public function folders(Vault $vault): array
    {
        $finder = Finder::create()
            ->directories()
            ->in($vault->path)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->ignoreUnreadableDirs()
            ->exclude($this->ignoredFolders());

        $folders = [];

        foreach ($finder as $directory) {
            $folders[] = str_replace('\\', '/', $directory->getRelativePathname());
        }

        natcasesort($folders);

        return array_values($folders);
    }

    public function folderExists(Vault $vault, string $path): bool
    {
        return File::isDirectory($this->absolutePath($vault, $path));
    }

    public function makeFolder(Vault $vault, string $path): void
    {
        File::ensureDirectoryExists($this->absolutePath($vault, $path));
    }

    public function exists(Vault $vault, string $path): bool
    {
        return File::isFile($this->absolutePath($vault, $path));
    }

    public function read(Vault $vault, string $path): string
    {
        return File::get($this->absolutePath($vault, $path));
    }

    /**
     * @return array{size: int, modified_at: int}
     */
    public function stat(Vault $vault, string $path): array
    {
        $absolutePath = $this->absolutePath($vault, $path);

        clearstatcache(true, $absolutePath);

        return [
            'size' => File::size($absolutePath),
            'modified_at' => File::lastModified($absolutePath),
        ];
    }

    /**
     * Atomically write a file inside the vault, creating parent folders as needed.
     */
    public function write(Vault $vault, string $path, string $contents): void
    {
        $absolutePath = $this->absolutePath($vault, $path);

        File::ensureDirectoryExists(dirname($absolutePath));

        $temporaryPath = dirname($absolutePath).DIRECTORY_SEPARATOR.'.'.basename($absolutePath).'.'.bin2hex(random_bytes(4)).'.tmp';

        File::put($temporaryPath, $contents);
        File::move($temporaryPath, $absolutePath);
    }

    public function move(Vault $vault, string $from, string $to): void
    {
        $destination = $this->absolutePath($vault, $to);

        File::ensureDirectoryExists(dirname($destination));
        File::move($this->absolutePath($vault, $from), $destination);
    }

    /**
     * Atomically copy a file from outside the vault (e.g. an upload) to a path inside it.
     */
    public function import(Vault $vault, string $path, string $sourcePath): void
    {
        $absolutePath = $this->absolutePath($vault, $path);

        File::ensureDirectoryExists(dirname($absolutePath));

        $temporaryPath = dirname($absolutePath).DIRECTORY_SEPARATOR.'.'.basename($absolutePath).'.'.bin2hex(random_bytes(4)).'.tmp';

        File::copy($sourcePath, $temporaryPath);
        File::move($temporaryPath, $absolutePath);
    }

    public function delete(Vault $vault, string $path): void
    {
        File::delete($this->absolutePath($vault, $path));
    }

    /**
     * @return array<string, array{size: int, modified_at: int}>
     */
    private function filesMatching(Vault $vault, string $pattern): array
    {
        $finder = Finder::create()
            ->files()
            ->in($vault->path)
            ->name($pattern)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->ignoreUnreadableDirs()
            ->exclude($this->ignoredFolders());

        $files = [];

        foreach ($finder as $file) {
            $files[str_replace('\\', '/', $file->getRelativePathname())] = [
                'size' => (int) $file->getSize(),
                'modified_at' => (int) $file->getMTime(),
            ];
        }

        ksort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }
}
