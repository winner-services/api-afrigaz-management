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
use Illuminate\Support\Facades\Request;
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

    #[OA\Post(
        path: "/api/tankMovements",
        summary: "Lister les mouvements des tanks par période",
        tags: ["Rapports"],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-04-01"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-04-25"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "success"),
                        new OA\Property(property: "status", type: "integer", example: 200),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "operation_date", type: "string", example: "2026-04-20"),
                                    new OA\Property(property: "tank_id", type: "integer", example: 2),
                                    new OA\Property(property: "user_id", type: "integer", example: 5),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            )
        ]
    )]

    public function tankMovements(Request $request)
    {
        $validated = validator($request->all(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ])->validate();
        $startDate = $validated['start_date'] ?? now()->startOfMonth();
        $endDate = $validated['end_date'] ?? now();

        $data = TankMovement::with('tank:id,name', 'user')
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }
    #[OA\Post(
        path: "/api/purchasesReport",
        summary: "Lister",
        tags: ["Rapports"],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-04-01"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-04-25"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "success"),
                        new OA\Property(property: "status", type: "integer", example: 200),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "operation_date", type: "string", example: "2026-04-20"),
                                    new OA\Property(property: "tank_id", type: "integer", example: 2),
                                    new OA\Property(property: "user_id", type: "integer", example: 5),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            )
        ]
    )]
    public function purchasesReport(Request $request)
    {
        $validated = validator($request->all(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ])->validate();
        $startDate = $validated['start_date'] ?? now()->startOfMonth();
        $endDate = $validated['end_date'] ?? now();

        $data = StockEntry::with('supplier', 'user', 'items.product:id,name')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->latest()
            ->get();
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: "/api/fillingsReport",
        summary: "Lister",
        tags: ["Rapports"],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-04-01"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-04-25"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "success"),
                        new OA\Property(property: "status", type: "integer", example: 200),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "operation_date", type: "string", example: "2026-04-20"),
                                    new OA\Property(property: "tank_id", type: "integer", example: 2),
                                    new OA\Property(property: "user_id", type: "integer", example: 5),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            )
        ]
    )]
    public function fillingsReport(Request $request)
    {
        $validated = validator($request->all(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ])->validate();
        $startDate = $validated['start_date'] ?? now()->startOfMonth();
        $endDate = $validated['end_date'] ?? now();
        $data =  Filling::with('tank', 'items.product:id,name')
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->latest()
            ->get();
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: "/api/transfersReport",
        summary: "Lister",
        tags: ["Rapports"],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-04-01"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-04-25"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "success"),
                        new OA\Property(property: "status", type: "integer", example: 200),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "operation_date", type: "string", example: "2026-04-20"),
                                    new OA\Property(property: "tank_id", type: "integer", example: 2),
                                    new OA\Property(property: "user_id", type: "integer", example: 5),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            )
        ]
    )]
    public function transfersReport(Request $request)
    {
        $validated = validator($request->all(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ])->validate();
        $startDate = $validated['start_date'] ?? now()->startOfMonth();
        $endDate = $validated['end_date'] ?? now();
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

    #[OA\Post(
        path: "/api/deliveriesReport",
        summary: "Rapport des livraisons (filtré par date, distributeur et branche)",
        tags: ["Rapports"],

        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-04-01"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-04-25"),
                    new OA\Property(property: "distributor_id", type: "integer", example: 2),
                    new OA\Property(property: "branch_id", type: "integer", example: 1),
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: "Succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "success"),
                        new OA\Property(property: "status", type: "integer", example: 200),

                        new OA\Property(
                            property: "filters",
                            properties: [
                                new OA\Property(property: "start_date", type: "string", example: "2026-04-01"),
                                new OA\Property(property: "end_date", type: "string", example: "2026-04-25"),
                                new OA\Property(property: "branch_id", type: "integer", example: 1),
                            ]
                        ),

                        new OA\Property(
                            property: "stats",
                            properties: [
                                new OA\Property(property: "total_deliveries", type: "integer", example: 15),
                                new OA\Property(property: "total_quantity", type: "number", example: 250),
                            ]
                        ),

                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "reference", type: "string", example: "DEL-001"),
                                    new OA\Property(property: "transaction_date", type: "string", example: "2026-04-20"),

                                    new OA\Property(
                                        property: "total_quantity",
                                        type: "number",
                                        example: 50
                                    ),

                                    new OA\Property(
                                        property: "distributor",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 2),
                                            new OA\Property(property: "name", type: "string", example: "Distributor A"),
                                        ]
                                    ),

                                    new OA\Property(
                                        property: "branch",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 1),
                                            new OA\Property(property: "name", type: "string", example: "Main Branch"),
                                        ]
                                    ),

                                    new OA\Property(
                                        property: "user",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 5),
                                            new OA\Property(property: "name", type: "string", example: "Admin"),
                                        ]
                                    ),

                                    new OA\Property(
                                        property: "items",
                                        type: "array",
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: "id", type: "integer", example: 10),
                                                new OA\Property(property: "quantity", type: "number", example: 20),

                                                new OA\Property(
                                                    property: "product",
                                                    properties: [
                                                        new OA\Property(property: "id", type: "integer", example: 3),
                                                        new OA\Property(property: "name", type: "string", example: "Gaz 12kg"),
                                                    ]
                                                ),
                                            ]
                                        )
                                    )
                                ]
                            )
                        )
                    ]
                )
            ),

            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            )
        ]
    )]
    public function deliveriesReport(Request $request)
    {
        $validated = validator($request->all(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'distributor_id' => ['nullable', 'exists:distributors,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ])->validate();
        $startDate = $validated['start_date'] ?? now()->startOfMonth();
        $endDate = $validated['end_date'] ?? now();
        $branchId = $validated['branch_id'] ?? 1;

        $query = Shipping::with([
            'items.product:id,name',
            'distributor:id,name',
            'user:id,name',
            'branch:id,name'
        ])
            ->withSum('items as total_quantity', 'quantity')
            ->where('branch_id', $branchId)
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        // ✅ Filtre distributeur
        if (!empty($validated['distributor_id'])) {
            $query->where('distributor_id', $validated['distributor_id']);
        }

        // ✅ Résultat
        $data = $query->latest()->get();

        // ✅ Stats
        $stats = [
            'total_deliveries' => $data->count(),
            'total_quantity' => $data->sum('total_quantity'),
        ];

        return response()->json([
            'message' => 'success',
            'status' => 200,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'branch_id' => $branchId
            ],
            'stats' => $stats,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: "/api/salesReport",
        summary: "Lister",
        tags: ["Rapports"],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-04-01"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-04-25"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "success"),
                        new OA\Property(property: "status", type: "integer", example: 200),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "operation_date", type: "string", example: "2026-04-20"),
                                    new OA\Property(property: "tank_id", type: "integer", example: 2),
                                    new OA\Property(property: "user_id", type: "integer", example: 5),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            )
        ]
    )]
    public function salesReport()
    {
        $startDate = request('start_date', now()->startOfMonth());
        $endDate = request('end_date', now());
        $branche_id = request('branche_id', 1);

        $data = Sale::with(['items.product:id,name', 'customer:id,name', 'distributor', 'user:id,name'])
            ->where('branch_id', $branche_id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->latest()
            ->get();
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $data
        ]);
    }
}
