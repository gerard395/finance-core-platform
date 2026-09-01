<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Accounting;

use App\Application\Accounting\CreateAccountingPeriod;
use App\Application\Accounting\CreateBookYear;
use App\Domain\Accounting\Entities\AccountingPeriod;
use App\Domain\Accounting\Entities\BookYear;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class AccountingPeriodConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'ab210000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('administrations')->insert(['id' => self::A, 'code' => 'APC', 'name' => 'AP concurrency', 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_real_mysql_serializes_overlapping_book_year_creates(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the AP concurrency test.');
        }
        DB::commit();
        $results = $this->race('ap-year-race-', function (int $index): string {
            $id = sprintf('ab230000-0000-4000-8000-%012d', $index + 1);
            $year = new BookYear(new BookYearId(new Uuid($id)), $this->admin(), 'RACE-'.$index, 'Race', new DateTimeImmutable($index === 0 ? '2026-01-01' : '2026-06-01'), new DateTimeImmutable($index === 0 ? '2026-12-31' : '2027-05-31'));

            return $this->app->make(CreateBookYear::class)->execute($year)->name;
        });

        sort($results);
        self::assertSame(['IntegrityFailure', 'Success'], $results);
        self::assertSame(1, DB::table('book_years')->where('administration_id', self::A)->count());
        self::assertSame(0, $this->durableOverlapPairs('book_years', null));
        $this->restoreTestTransaction();
    }

    public function test_real_mysql_serializes_overlapping_period_plans_in_one_book_year(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the AP concurrency test.');
        }
        $yearId = new BookYearId(new Uuid('ab230000-0000-4000-8000-000000000010'));
        $year = new BookYear($yearId, $this->admin(), 'FY26', 'FY26', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'));
        self::assertSame('Success', $this->app->make(CreateBookYear::class)->execute($year)->name);
        DB::commit();
        $results = $this->race('ap-period-race-', function (int $index) use ($yearId): string {
            $id = sprintf('ab240000-0000-4000-8000-%012d', $index + 1);
            $period = new AccountingPeriod(new AccountingPeriodId(new Uuid($id)), $this->admin(), $yearId, 'RACE-'.$index, 'Race', new DateTimeImmutable($index === 0 ? '2026-01-01' : '2026-06-01'), new DateTimeImmutable('2026-12-31'));

            return $this->app->make(CreateAccountingPeriod::class)->execute($period)->name;
        });

        sort($results);
        self::assertSame(['IntegrityFailure', 'Success'], $results);
        self::assertSame(1, DB::table('accounting_periods')->where('administration_id', self::A)->where('book_year_id', $yearId->toString())->count());
        self::assertSame(0, $this->durableOverlapPairs('accounting_periods', $yearId->toString()));
        $this->restoreTestTransaction();
    }

    /** @return list<string> */
    private function race(string $prefix, callable $operation): array
    {
        $files = [tempnam(sys_get_temp_dir(), $prefix), tempnam(sys_get_temp_dir(), $prefix)];
        $children = [];
        foreach ($files as $index => $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($file, $operation($index));
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.get_class($exception).':'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        return $results;
    }

    private function durableOverlapPairs(string $table, ?string $yearId): int
    {
        $query = DB::table($table.' as left_row')->join($table.' as right_row', function ($join): void {
            $join->on('left_row.administration_id', '=', 'right_row.administration_id')->on('left_row.id', '<', 'right_row.id')->on('left_row.start_date', '<=', 'right_row.end_date')->on('left_row.end_date', '>=', 'right_row.start_date');
        })->where('left_row.administration_id', self::A);
        if ($yearId !== null) {
            $query->where('left_row.book_year_id', $yearId)->where('right_row.book_year_id', $yearId);
        }

        return $query->count();
    }

    private function restoreTestTransaction(): void
    {
        DB::table('accounting_periods')->where('administration_id', self::A)->delete();
        DB::table('book_years')->where('administration_id', self::A)->delete();
        DB::table('administrations')->where('id', self::A)->delete();
        DB::beginTransaction();
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::A));
    }
}
