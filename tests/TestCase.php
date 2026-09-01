<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function createOpenAccountingPeriodFixture(string $administrationId): void
    {
        $yearId = (string) Str::uuid();
        $periodId = (string) Str::uuid();
        $now = now();
        DB::table('book_years')->insert([
            'id' => $yearId, 'administration_id' => $administrationId, 'code' => 'TEST-2026',
            'label' => 'Test 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('accounting_periods')->insert([
            'id' => $periodId, 'administration_id' => $administrationId, 'book_year_id' => $yearId,
            'code' => 'TEST-2026', 'label' => 'Test 2026', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'status' => 'open', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
