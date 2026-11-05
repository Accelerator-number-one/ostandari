<?php

namespace App\Services;

final readonly class PreparedUpload
{
    public function __construct(
        public string $path,
        public string $extension,
        public string $mime,
        public int $size,
        public string $name,
        public ?int $width = null,
        public ?int $height = null,
        private bool $temporary = false,
    ) {}

    public function cleanup(): void
    {
        if ($this->temporary) {
            @unlink($this->path);
        }
    }
}
