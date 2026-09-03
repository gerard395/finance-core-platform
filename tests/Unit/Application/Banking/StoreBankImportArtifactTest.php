<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Banking;

use App\Application\Banking\BankImportArtifactKeyGenerator;
use App\Application\Banking\BankImportArtifactStorage;
use App\Application\Banking\StoreBankImportArtifact;
use App\Application\Banking\StoreBankImportArtifactStatus;
use PHPUnit\Framework\TestCase;

final class StoreBankImportArtifactTest extends TestCase
{
    public function test_artifact_is_verified_and_quarantine_is_always_cleaned(): void
    {
        $storage = new class implements BankImportArtifactStorage
        {
            /** @var array<string, string> */
            public array $files = [];

            public function storeImmutable(string $storageKey, string $bytes): bool
            {
                if (isset($this->files[$storageKey])) {
                    return hash_equals($this->files[$storageKey], $bytes);
                } $this->files[$storageKey] = $bytes;

                return true;
            }

            public function read(string $storageKey): ?string
            {
                return $this->files[$storageKey] ?? null;
            }

            public function exists(string $storageKey): bool
            {
                return isset($this->files[$storageKey]);
            }

            public function promoteToRetained(string $temporaryKey, string $retainedKey, string $expectedSha256): bool
            {
                return false;
            }

            public function deleteTemporary(string $storageKey): void
            {
                unset($this->files[$storageKey]);
            }
        };
        $keys = new class implements BankImportArtifactKeyGenerator
        {
            public function temporaryKey(): string
            {
                return 'quarantine/random.xml';
            }

            public function retainedKey(string $sha256): string
            {
                return 'retained/random.xml';
            }
        };
        $result = (new StoreBankImportArtifact($storage, $keys))->execute('<private bank data/>');
        self::assertSame(StoreBankImportArtifactStatus::Success, $result->status);
        self::assertSame(hash('sha256', '<private bank data/>'), $result->artifact?->hash->value);
        self::assertTrue($storage->exists('quarantine/random.xml'));
        self::assertSame('quarantine/random.xml', $result->artifact?->storageKey);
        self::assertSame('<private bank data/>', $storage->read('quarantine/random.xml'));
    }
}
