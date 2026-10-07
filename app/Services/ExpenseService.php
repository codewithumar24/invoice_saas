<?php

namespace App\Services;

use App\Events\ExpenseCreated;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(private CurrencyService $currency, private FileStorageService $files, private ActivityService $activity) {}

    public function create(array $data, array $receipts = []): Expense
    {
        $expense = DB::transaction(function () use ($data, $receipts) {
            $currency = strtoupper($data['currency'] ?? tenant()->currency);

            $expense = Expense::create($this->attributes($data) + [
                'currency' => $currency,
                'exchange_rate' => $this->currency->rate($currency, $data['expense_date'] ?? null),
                'created_by' => auth()->id(),
            ]);

            $this->storeReceipts($expense, $receipts);
            $this->syncTags($expense, $data);
            $this->activity->log('expense.created', __('Expense of :amount recorded', ['amount' => money($expense->amount, $expense->currency)]), $expense);

            return $expense;
        });

        ExpenseCreated::dispatch($expense);

        return $expense;
    }

    public function update(Expense $expense, array $data, array $receipts = []): Expense
    {
        return DB::transaction(function () use ($expense, $data, $receipts) {
            $expense->fill($this->attributes($data));

            if (isset($data['currency']) && strtoupper($data['currency']) !== $expense->currency) {
                $expense->currency = strtoupper($data['currency']);
                $expense->exchange_rate = $this->currency->rate($expense->currency, $expense->expense_date?->toDateString());
            }

            $expense->save();
            $this->storeReceipts($expense, $receipts);
            $this->syncTags($expense, $data);

            return $expense;
        });
    }

    private function attributes(array $data): array
    {
        return collect($data)->only(['category_id', 'branch_id', 'project_id', 'vendor', 'amount', 'tax_amount', 'expense_date',
            'payment_method', 'reference', 'notes', 'is_recurring', 'recurring_frequency'])
            ->map(fn ($value, $key) => $key === 'tax_amount' ? ($value ?? 0) : $value)->all();
    }

    private function storeReceipts(Expense $expense, array $receipts): void
    {
        foreach ($receipts as $file) {
            $expense->receipts()->create($this->files->store($file, 'expense_receipts'));
        }
    }

    private function syncTags(Expense $expense, array $data): void
    {
        if (array_key_exists('tags', $data)) {
            $expense->syncTagNames(is_array($data['tags']) ? $data['tags'] : explode(',', (string) $data['tags']));
        }
    }
}
