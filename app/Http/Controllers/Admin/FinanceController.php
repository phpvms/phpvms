<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Models\Enums\JournalType;
use App\Models\Journal;
use App\Services\FinanceService;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeSvc
    ) {}

    /**
     * Display the summation tables for a given month by airline
     *
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function index(Request $request): View
    {
        $month = $request->query('month', date('Y-m'));
        $transaction_groups = $this->financeSvc->getAllAirlineTransactionsBetween($month);

        $first_journal = Journal::select(['created_at'])
            ->where(['type' => JournalType::AIRLINE])
            ->orderBy('created_at', 'asc')
            ->limit(1)
            ->first();

        return view('admin.finances.index', [
            'current_month'      => $month,
            'months_list'        => Dates::getMonthsList($first_journal->created_at),
            'transaction_groups' => $transaction_groups,
        ]);
    }

    /**
     * Show a month
     *
     * @param mixed $id
     */
    public function show($id) {}
}
