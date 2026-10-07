<?php

namespace App\Services;

use App\Models\NumberSequence;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe document numbers (INV-2026-00001).
 * The sequence row is locked FOR UPDATE inside a transaction, so two requests can never get the same number.
 */
class NumberingService
{
    public function __construct(private TenantContext $context, private SettingsService $settings) {}

    public function next(string $type, ?int $branchId = null): string
    {
        $prefix = (string) $this->settings->get("{$type}_prefix", strtoupper(substr($type, 0, 3)));
        $reset = (string) $this->settings->get('invoice_reset', 'yearly');
        $padding = (int) $this->settings->get('invoice_padding', 5);
        $now = now($this->context->timezone());

        $period = match ($reset) {
            'yearly' => $now->format('Y'),
            'monthly' => $now->format('Y-m'),
            default => '',
        };

        $useBranch = $type === 'invoice' && $this->settings->get('invoice_branch_sequence') && $branchId;
        $branchKey = $useBranch ? $branchId : 0;

        $number = DB::transaction(function () use ($type, $period, $branchKey) {
            $keys = ['tenant_id' => $this->context->id(), 'type' => $type, 'period' => $period, 'branch_id' => $branchKey];

            // Create the sequence row if it is missing; a concurrent insert is ignored by the unique index
            DB::table('number_sequences')->insertOrIgnore($keys + [
                'next_number' => $type === 'invoice' ? max(1, (int) $this->settings->get('invoice_next_number', 1)) : 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $sequence = NumberSequence::where($keys)->lockForUpdate()->first();
            $current = $sequence->next_number;
            $sequence->forceFill(['next_number' => $current + 1])->save();

            return $current;
        });

        $parts = array_filter([$prefix, $useBranch ? 'B'.$branchId : null, str_replace('-', '', $period)], fn ($part) => $part !== null && $part !== '');
        $parts[] = str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

        return implode('-', $parts);
    }
}
