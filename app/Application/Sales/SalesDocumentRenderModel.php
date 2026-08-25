<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Enums\SalesDocumentType;

final readonly class SalesDocumentRenderModel
{
    /** @param array<string, mixed> $content */
    public function __construct(
        public SalesDocumentType $type,
        public string $sourceId,
        public string $number,
        public string $templateVersion,
        public array $content,
    ) {}

    public function fingerprint(): string
    {
        $canonical = ['type' => $this->type->value, 'source_id' => $this->sourceId, 'template_version' => $this->templateVersion, 'content' => self::canonicalize($this->content)];
        $json = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return hash('sha256', $json);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }
        ksort($value, SORT_STRING);

        return array_map(self::canonicalize(...), $value);
    }
}
