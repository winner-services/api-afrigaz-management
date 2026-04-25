<?php

namespace App\Http\Controllers\Api\Repport;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Distributor;
use App\Models\Filling;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shipping;
use App\Models\StockEntry;
use App\Models\StockMovement;
use App\Models\TankMovement;
use App\Models\Transfer;
use OpenApi\Attributes as OA;

class RepportController extends Controller
{
    #[OA\Get(
        path: "/api/v1/productsList",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]

    public function productsList()
    {
        $data = Product::with([
            'category',
            'unit'
        ])->latest()->get();

        return response()->json([
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/stockReport",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function stockReport()
    {
        $startDate = request('start_date', now()->startOfMonth());
        $endDate = request('end_date', now());
        $branche_id = request('branche_id', 1);

        $data = StockMovement::with('product:id,name')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('product_id', 'type', 'quantity', 'created_at')
            ->where('branche_id', $branche_id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/distributorsList",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function distributorsList()
    {
        $data =  Distributor::with([
            'category'
        ])->latest()->get();

        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/customersList",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]

    public function customersList()
    {
        $data = Customer::with(['user'])->latest()->get();
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/tankMovements",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]

    public function tankMovements()
    {
        $data = TankMovement::with('tank:id,name','user')
            ->latest()
            ->get();
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/purchasesReport",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function purchasesReport()
    {
        $startDate = request('start_date', now()->startOfMonth());
        $endDate = request('end_date', now());

        $data = StockEntry::with('supplier', 'user', 'items.product:id,name')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('id', 'supplier_id', 'transaction_date')
            ->latest()
            ->get();
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/fillingsReport",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function fillingsReport()
    {
        $startDate = request('start_date', now()->startOfMonth());
        $endDate = request('end_date', now());
        $data =  Filling::with('tank', 'items.product:id,name')
            ->select('id', 'tank_id', 'total_gas_used', 'operation_date')
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->latest()
            ->get();
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/transfersReport",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function transfersReport()
    {
        $startDate = request('start_date', now()->startOfMonth());
        $endDate = request('end_date', now());
        $branche_id = request('branche_id', 1);
        $data = Transfer::join('items_transfers', 'transfers.id', '=', 'items_transfers.transfer_id')
            ->join('branches as from_branch', 'transfers.from_branch_id', '=', 'from_branch.id')
            ->join('branches as to_branch', 'items_transfers.to_branch_id', '=', 'to_branch.id')
            ->join('products', 'items_transfers.product_id', '=', 'products.id')
            ->select(
                'items_transfers.id',
                'transfers.transfer_date',
                'transfers.reference',
                'from_branch.name as from_branch_name',
                'products.name as product_name',
                'items_transfers.quantity as sent_quantity',
                'items_transfers.received_quantity as received_quantity',
                'items_transfers.status'
            )
            ->where('transfers.from_branch_id', $branche_id)
            ->whereBetween('transfer_date', [$startDate, $endDate])
            ->latest('transfers.created_at')
            ->get();

        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/deliveriesReport",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function deliveriesReport()
    {
        $data =  Shipping::with('items.product:id,name')
            ->select('id', 'reference', 'created_at')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }


    #[OA\Get(
        path: "/api/v1/salesReport",
        summary: "Lister",
        tags: ["Rapports"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function salesReport()
    {
        $startDate = request('start_date', now()->startOfMonth());
        $endDate = request('end_date', now());
        $branche_id = request('branche_id', 1);

        $data = Sale::with(['items.product:id,name', 'customer:id,name'])
            ->where('branch_id', $branche_id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('id', 'customer_id', 'sale_type', 'total_amount', 'created_at')
            ->latest()
            ->get();
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }
}
