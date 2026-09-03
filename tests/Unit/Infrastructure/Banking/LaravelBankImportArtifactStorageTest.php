<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Banking;

use App\Infrastructure\Banking\LaravelBankImportArtifactStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LaravelBankImportArtifactStorageTest extends TestCase
{
    public function test_artifacts_are_private_immutable_and_temporary_failures_are_cleanable(): void
    {
        Storage::fake('bank_imports');
        $storage = new LaravelBankImportArtifactStorage;
        self::assertTrue($storage->storeImmutable('retained/random.xml', '<private/>'));
        self::assertSame('<private/>', $storage->read('retained/random.xml'));
        self::assertFalse($storage->storeImmutable('retained/random.xml', '<different/>'));
        self::assertSame('<private/>', $storage->read('retained/random.xml'));
        self::assertTrue($storage->storeImmutable('quarantine/upload.xml', '<failed/>'));
        $storage->deleteTemporary('quarantine/upload.xml');
        self::assertFalse($storage->exists('quarantine/upload.xml'));
        self::assertTrue($storage->storeImmutable('quarantine/promote.xml', '<source/>'));
        self::assertTrue($storage->promoteToRetained('quarantine/promote.xml', 'retained/promoted.xml', hash('sha256', '<source/>')));
        self::assertFalse($storage->exists('quarantine/promote.xml'));
        self::assertSame('<source/>', $storage->read('retained/promoted.xml'));
        self::assertFalse($storage->promoteToRetained('quarantine/missing.xml', 'retained/other.xml', hash('sha256', '<source/>')));
        self::assertArrayNotHasKey('url', config('filesystems.disks.bank_imports'));
        self::assertFalse(config('filesystems.disks.bank_imports.serve'));
        self::assertSame('private', config('filesystems.disks.bank_imports.visibility'));
    }
}
