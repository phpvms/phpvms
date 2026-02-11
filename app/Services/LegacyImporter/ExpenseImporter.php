<?php

namespace App\Services\LegacyImporter;

use App\Models\Enums\ExpenseType;
use App\Models\Expense;

class ExpenseImporter extends BaseImporter
{
    protected $table = 'expenses';

    private array $expense_types = [
        'M' => ExpenseType::MONTHLY,
        'F' => ExpenseType::FLIGHT,
        'P' => ExpenseType::MONTHLY, // percent, monthly
        'G' => ExpenseType::FLIGHT, // percent, per-flight
    ];

    /**
     * {@inheritdoc}
     */
    public function run($start = 0)
    {
        $this->comment('--- EXPENSES IMPORT ---');

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start);
        foreach ($rows as $row) {
            $attrs = [
                'airline_id'     => null,
                'name'           => $row->name,
                'amount'         => $row->cost,
                'type'           => $this->expense_types[$row->type],
                'active'         => 1,
                'ref_model_type' => Expense::class,
            ];

            $expense = Expense::updateOrCreate(['name' => $row->name], $attrs);
            $this->idMapper->addMapping('expenses', $row->id, $expense->id);
            $this->idMapper->addMapping('expenses', $row->name, $expense->id);

            $count++;
        }

        $this->info('Imported '.$count.' expenses');
    }
}
