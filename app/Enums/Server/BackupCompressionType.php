<?php

namespace App\Enums\Server;

enum BackupCompressionType: string
{
    case NONE = 'none';
    case LZO = 'lzo';
    case GZIP = 'gzip';
    case ZSTD = 'zstd';

    public function extension(): string
    {
        return match ($this) {
            self::NONE => '',
            self::LZO => '.lzo',
            self::GZIP => '.gz',
            self::ZSTD => '.zst',
        };
    }
}
