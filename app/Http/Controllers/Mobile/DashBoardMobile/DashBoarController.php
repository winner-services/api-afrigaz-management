<?php

namespace App\Http\Controllers\Mobile\DashBoardMobile;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductLedger;
use App\Models\Sale;
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

            // Chiffre d'affaires mensuel
            $monthlyData = Sale::select(
                DB::raw('MONTH(transaction_date) as month'),
                DB::raw('SUM(total_amount) as total')
            )
                ->where('branch_id', $branche->id)
                ->where('status', 'completed')
                ->whereYear('transaction_date', now()->year)
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

            $totalProducts = Product::count('id');

            $totalClients = Customer::count('id');

            $totalSales = Sale::where('branch_id', $branche->id)
                // ->where('status', 'completed')
                ->sum('total_amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_products' => $totalProducts,
                    'total_clients' => $totalClients,
                    'total_sales' => number_format($totalSales, 2) . ' $',
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
