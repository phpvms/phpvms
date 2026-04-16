<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpFmsDownloads extends Dto
{
    /**
     * @param SimBriefOfpFile[] $files
     */
    public function __construct(
        public string $directory,
        public array $files,
    ) {}

    public static function fromArray(array $data): self
    {
        $directory = $data['directory'];
        $files = [];

        foreach ($data as $key => $value) {
            if ($key === 'directory') {
                continue;
            }

            if (is_array($value) && isset($value['name'], $value['link'])) {
                $files[$key] = SimBriefOfpFile::from($value);
            }
        }

        return new self(
            directory: $directory,
            files: $files
        );
    }
}
