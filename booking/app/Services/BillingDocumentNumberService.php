<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingDocumentNumberService
{
    public function next(string $series): string
    {
        $series = strtoupper(trim($series));

        if (! preg_match('/^[A-Z]{2}$/', $series)) {
            throw new RuntimeException('Billing document series must be exactly two letters.');
        }

        $year = (int) now()->format('Y');
        $startYear = (int) now()->format('n') >= 4 ? $year : $year - 1;
        $financialYear = sprintf('%02d-%02d', $startYear % 100, ($startYear + 1) % 100);

        DB::table('billing_document_sequences')->insertOrIgnore([
            'series' => $series,
            'financial_year' => $financialYear,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('billing_document_sequences')
            ->where('series', $series)
            ->where('financial_year', $financialYear)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new RuntimeException('Could not allocate a billing document number.');
        }

        $next = (int) $row->last_number + 1;

        if ($next > 999999) {
            throw new RuntimeException('Billing document sequence is exhausted for this financial year.');
        }

        DB::table('billing_document_sequences')
            ->where('id', $row->id)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('%s/%s/%06d', $series, $financialYear, $next);
    }
}
