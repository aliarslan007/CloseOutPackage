<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Services;

use App\Shared\Exceptions\MediaStorageUnavailableException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Persists uploaded chunks on the private disk under
 * media-library/chunks/{asset_id}/{zero-padded index} and reassembles them
 * into a single contiguous file for the processing job.
 */
final class ChunkStorageService
{
    private const DISK = 'private';

    private const ROOT = 'media-library/chunks';

    private const TMP_ROOT = 'media-library/tmp';

    public function storeChunk(int $assetId, int $index, UploadedFile $chunk): void
    {
        $directory = $this->directoryFor($assetId);

        try {
            $result = Storage::disk(self::DISK)->putFileAs($directory, $chunk, $this->chunkName($index));
        } catch (Throwable $exception) {
            Log::error('Media chunk write failed', [
                'asset_id' => $assetId,
                'chunk_index' => $index,
                'error' => $exception->getMessage(),
            ]);

            throw new MediaStorageUnavailableException('Server storage is temporarily unavailable. Try again later.');
        }

        if ($result === false) {
            throw new MediaStorageUnavailableException('Server storage is temporarily unavailable. Try again later.');
        }
    }

    public function hasChunk(int $assetId, int $index): bool
    {
        return Storage::disk(self::DISK)->exists($this->filePathFor($assetId, $index));
    }

    public function countChunks(int $assetId): int
    {
        /** @var list<string> $files */
        $files = Storage::disk(self::DISK)->files($this->directoryFor($assetId));

        // Only count files that match our zero-padded chunk format so a
        // partial/temp file left by an interrupted write can never inflate
        // the count and let a broken upload finalize.
        return count(array_filter($files, fn (string $file): bool => (bool) preg_match('/\d{6}$/', $file)));
    }

    /**
     * Concatenate every stored chunk (ordered by zero-padded index) into a
     * single temp file and return its absolute path.
     */
    public function reassemble(int $assetId): string
    {
        $relative = self::TMP_ROOT.'/reassemble-'.$assetId.'-'.uniqid('', true).'.bin';
        $absolute = Storage::disk(self::DISK)->path($relative);

        Storage::disk(self::DISK)->makeDirectory(self::TMP_ROOT);

        $out = fopen($absolute, 'wb');

        if ($out === false) {
            throw new MediaStorageUnavailableException('Server storage is temporarily unavailable. Try again later.');
        }

        try {
            /** @var list<string> $chunkFiles */
            $chunkFiles = Storage::disk(self::DISK)->files($this->directoryFor($assetId));
            sort($chunkFiles, SORT_STRING);

            foreach ($chunkFiles as $chunkFile) {
                $in = Storage::disk(self::DISK)->readStream($chunkFile);

                if (! is_resource($in)) {
                    throw new MediaStorageUnavailableException('Server storage is temporarily unavailable. Try again later.');
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        return $absolute;
    }

    public function cleanup(int $assetId): void
    {
        Storage::disk(self::DISK)->deleteDirectory($this->directoryFor($assetId));
    }

    /**
     * Delete chunk directories whose asset no longer exists in the database
     * (rows removed by abort or purge). Returns the number of directories
     * removed.
     *
     * @param  list<int>  $existingAssetIds  ids still present in media_assets
     */
    public function pruneOrphanedDirectories(array $existingAssetIds): int
    {
        $existing = array_flip($existingAssetIds);
        $removed = 0;

        foreach ($this->chunkDirectories() as $directory) {
            $assetId = (int) basename($directory);

            if ($assetId > 0 && ! isset($existing[$assetId])) {
                Storage::disk(self::DISK)->deleteDirectory($directory);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * How many chunk folders on disk have no owning asset. Reported by the
     * admin purge preview so an operator can see abandoned-upload bytes are
     * part of the reclaim, not just the media table.
     *
     * @param  list<int>  $existingAssetIds
     */
    public function countOrphanedDirectories(array $existingAssetIds): int
    {
        $existing = array_flip($existingAssetIds);
        $orphans = 0;

        foreach ($this->chunkDirectories() as $directory) {
            $assetId = (int) basename($directory);

            if ($assetId > 0 && ! isset($existing[$assetId])) {
                $orphans++;
            }
        }

        return $orphans;
    }

    /**
     * @return list<string>
     */
    private function chunkDirectories(): array
    {
        /** @var list<string> $directories */
        $directories = Storage::disk(self::DISK)->directories(self::ROOT);

        return $directories;
    }

    private function directoryFor(int $assetId): string
    {
        return self::ROOT.'/'.$assetId;
    }

    private function filePathFor(int $assetId, int $index): string
    {
        return $this->directoryFor($assetId).'/'.$this->chunkName($index);
    }

    private function chunkName(int $index): string
    {
        return str_pad((string) $index, 6, '0', STR_PAD_LEFT);
    }
}
