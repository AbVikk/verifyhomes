<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadConfiguration
{
    public static function effectiveMaxUploadKilobytes(int $requestedKilobytes): int
    {
        $serverKilobytes = min(
            static::iniSizeToKilobytes((string) ini_get('upload_max_filesize')),
            static::iniSizeToKilobytes((string) ini_get('post_max_size')),
        );

        if ($serverKilobytes <= 0) {
            return $requestedKilobytes;
        }

        return max(1, min($requestedKilobytes, $serverKilobytes));
    }

    public static function formatKilobytes(int $kilobytes): string
    {
        if ($kilobytes >= 1024) {
            $megabytes = $kilobytes / 1024;

            return rtrim(rtrim(number_format($megabytes, 2, '.', ''), '0'), '.').'MB';
        }

        return $kilobytes.'KB';
    }

    public static function kilobytesToBytes(int $kilobytes): int
    {
        return $kilobytes * 1024;
    }

    public static function ensureLivewireTemporaryUploadDirectoryExists(): void
    {
        $disk = config('livewire.temporary_file_upload.disk') ?: config('filesystems.default');
        $directory = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';

        try {
            Storage::disk($disk)->makeDirectory($directory);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    protected static function iniSizeToKilobytes(string $value): int
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return 0;
        }

        $unit = strtolower(substr($trimmed, -1));
        $number = is_numeric($unit) ? (float) $trimmed : (float) substr($trimmed, 0, -1);

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024),
            'm' => (int) round($number * 1024),
            'k' => (int) round($number),
            default => (int) round(((float) $trimmed) / 1024),
        };
    }
}
