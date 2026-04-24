<?php

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\CustomerDebt;
use App\Models\DebtDistributor;
use App\Models\Filling;
use App\Models\ItemSale;
use App\Models\ItemsStockEntries;
use App\Models\ItemsTransfer;
use App\Models\Sale;
use App\Models\StockByBranch;
use App\Models\Tank;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DashBoardController extends Controller
{
    #[OA\Get(
        path: "/api/v1/dashBoardGetData",
        summary: "Lister",
        tags: ["DashBoard"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function getDashboardData()
    {
        try {
            $devise = Currency::where('status', 'created')
                ->orderByRaw("currency_type = 'devise_principale' DESC")
                ->latest()
                ->get();

            $branchId = request('branche_id', 1);

            $startDate = request('start_date', now()->startOfMonth());
            $endDate = request('end_date', now());


            $totalSales = Sale::where('branch_id', $branchId)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->count();

            $totalRevenue = Sale::where('branch_id', $branchId)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('total_amount');

            $stockGaz = Tank::sum('current_level');

            $bottlesFull = StockByBranch::where('branche_id', $branchId)
                ->where('is_empty', false)
                ->sum('stock_quantity');

            $bottlesEmpty = StockByBranch::where('branche_id', $branchId)
                ->where('is_empty', true)
                ->sum('stock_quantity');

            $debtsDistrib = DebtDistributor::whereIn('status', ['pending', 'partial'])
                ->sum(DB::raw('COALESCE(loan_amount,0) - COALESCE(paid_amount,0)'));

            $debtCustomer = CustomerDebt::whereIn('status', ['pending', 'partial'])
                ->sum(DB::raw('COALESCE(loan_amount,0) - COALESCE(paid_amount,0)'));

            $debts = $debtsDistrib + $debtCustomer;

            $recipe = CashTransaction::whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');

            $expenses = CashTransaction::whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');

            $salesByDay = Sale::where('branch_id', $branchId)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->selectRaw('DATE(transaction_date) as date, SUM(total_amount) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $salesByType = Sale::where('branch_id', $branchId)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->selectRaw('sale_type, SUM(total_amount) as total')
                ->groupBy('sale_type')
                ->get()
                ->map(function ($item) {
                    return [
                        'type' => match ($item->sale_type) {
                            'refill' => 'Recharge',
                            'kit' => 'Kit complet',
                            'accessory' => 'Accessoires',
                            'exchange' => 'Echange',
                            default => $item->sale_type
                        },
                        'total' => $item->total
                    ];
                });

            $stockMovement = [
                [
                    'label' => 'Achats',
                    'value' => ItemsStockEntries::sum('quantity') ?? 0
                ],
                [
                    'label' => 'Recharges',
                    'value' => Filling::sum('total_gas_used') ?? 0
                ],
                [
                    'label' => 'Ventes',
                    'value' => ItemSale::sum('quantity') ?? 0
                ],
                [
                    'label' => 'Transferts',
                    'value' => ItemsTransfer::sum('quantity') ?? 0
                ],
            ];

            $tresorerie = CashTransaction::whereBetween('transaction_date', [$startDate, $endDate])
                ->selectRaw("
        DATE(transaction_date) as date,
        SUM(CASE WHEN type = 'Revenue' THEN amount ELSE 0 END) as `in`,
        SUM(CASE WHEN type = 'Depense' THEN amount ELSE 0 END) as `out`
    ")
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'kpis' => [
                    'totalSales' => $totalSales,
                    'totalRevenue' => $totalRevenue,
                    'stockGaz' => $stockGaz,
                    'bottlesFull' => $bottlesFull,
                    'bottlesEmpty' => $bottlesEmpty,
                    'debts' => $debts,
                    'expenses' => $expenses,
                    'recipe' => $recipe
                ],
                'salesByDay' => $salesByDay,
                'salesByType' => $salesByType,
                'stockMovement' => $stockMovement,
                'tresorerie' => $tresorerie,
                'devise' => $devise
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Erreur dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
