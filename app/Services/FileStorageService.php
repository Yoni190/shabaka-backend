<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    /**
     * Store an uploaded file and return its path relative to the disk root.
     *
     * Filenames are generated as UUIDs to avoid collisions and any influence
     * from client-supplied names. The original extension is preserved.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        return $file->storeAs($directory, $filename, $this->disk());
    }

    /**
     * Delete a stored file. Null and empty paths are ignored, as are files
     * that no longer exist on disk.
     */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    /**
     * Delete multiple stored files in one call. Null and empty paths are
     * filtered out before hitting the filesystem.
     *
     * @param  iterable<string|null>  $paths
     */
    public function deleteMany(iterable $paths): void
    {
        $clean = collect($paths)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values()
            ->all();

        if ($clean === []) {
            return;
        }

        Storage::disk($this->disk())->delete($clean);
    }

    /**
     * The disk used for uploads. Defaults to `s3` to preserve existing
     * behavior; set `filesystems.uploads_disk` to override globally.
     */
    private function disk(): string
    {
        return config('filesystems.uploads_disk', 's3');
    }
}