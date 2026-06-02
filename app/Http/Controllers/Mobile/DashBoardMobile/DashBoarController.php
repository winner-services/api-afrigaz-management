<?php

namespace App\Http\Controllers\Mobile\DashBoardMobile;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashBoarController extends Controller
{
    public function stockDashboard()
    {
        try {

            $branche = Branche::where('user_id', Auth::id())->first();

            if (!$branche) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune branche trouvée pour cet utilisateur.'
                ], 404);
            }

            $monthlyData = ProductLedger::select(
                DB::raw('MONTH(operation_date) as month'),
                DB::raw("
                    SUM(
                        CASE
                            WHEN movement = 'in' THEN quantity
                            WHEN movement = 'out' THEN -quantity
                            ELSE 0
                        END
                    ) as total
                ")
            )
                ->where('branch_id', $branche->id)
                ->where('status', 'posted')
                ->whereYear('operation_date', now()->year)
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month');

            $labels = [];
            $datasets = [];

            for ($month = 1; $month <= 12; $month++) {
                $labels[] = Carbon::create()
                    ->month($month)
                    ->locale('fr')
                    ->translatedFormat('M');

                $datasets[] = (float) ($monthlyData[$month] ?? 0);
            }

            $cashAccountIds = CashAccount::where('branche_id', $branche->id)
                ->pluck('id');

            $lastTransactionIds = CashTransaction::selectRaw('MAX(id) as id')
                ->whereIn('cash_account_id', $cashAccountIds)
                ->groupBy('cash_account_id')
                ->pluck('id');

            $totalCashBalance = CashTransaction::whereIn('id', $lastTransactionIds)
                ->sum('solde');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_products' => Product::count(),
                    'total_clients' => Customer::count(),
                    'total_cash_balance' => number_format($totalCashBalance, 2) . ' $',
                    'chart_data' => [
                        'labels' => $labels,
                        'datasets' => $datasets
                    ]
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
