<?php

namespace App\Http\Controllers\Api\EntryStock;

use App\Http\Controllers\Controller;
use App\Models\ItemsStockEntries;
use App\Models\StockEntry;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class EntryStockController extends Controller
{

    #[OA\Post(
        path: "/api/v1/stockEntriesStore",
        summary: "Créer une entrée de stock",
        tags: ["Stock Entries"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["transaction_date", "branche_id", "items"],
                properties: [
                    new OA\Property(property: "transaction_date", type: "string", example: "2026-04-05"),
                    new OA\Property(property: "supplier_id", type: "integer", example: 1),
                    new OA\Property(property: "branche_id", type: "integer", example: 1),

                    new OA\Property(
                        property: "items",
                        type: "array",
                        items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(property: "product_id", type: "integer", example: 1),
                                new OA\Property(property: "quantity", type: "integer", example: 50),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Entrée créée avec succès"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'transaction_date' => 'required|date',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($request) {

                $entry = StockEntry::create([
                    'transaction_date' => $request->transaction_date,
                    'reference' => fake()->unique()->numerify('ENT-#####'),
                    'supplier_id' => $request->supplier_id,
                    'addedBy' => Auth::id(),
                    'status' => 'created',
                ]);

                foreach ($request->items as $item) {

                    ItemsStockEntries::create([
                        'stock_entries_id' => $entry->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }

                StockService::addMultipleStock(
                    1,
                    $request->items,
                    "Entrée en stock REF: {$entry->reference}",
                    [
                        'id' => $entry->id,
                        'type' => "stock_entry : {$entry->reference}"
                    ]
                );

                return response()->json([
                    'status' => true,
                    'message' => 'Entrée de stock enregistrée avec succès',
                    'data' => $entry
                ], 201);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Erreur serveur',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/stockEntrieGetAllData",
        summary: "Lister",
        tags: ["Stock Entries"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]

    public function index()
    {
        $q = request('q');

        $entries = StockEntry::with([
            'supplier:id,name',
            'user:id,name',
            'items.product:id,name,unit_id',
            'items.product.unit:id,abreviation'
        ])
            ->where('status', '!=', 'deleted')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {

                    $sub->where('reference', 'like', "%$q%")

                        ->orWhereHas('supplier', function ($s) use ($q) {
                            $s->where('name', 'like', "%$q%");
                        });
                });
            })
            ->latest()
            ->paginate(10);

        $data = $entries->getCollection()->map(function ($entry) {
            return [
                'id' => $entry->id,
                'reference' => $entry->reference,
                'transaction_date' => $entry->transaction_date,

                'supplier' => $entry->supplier ? [
                    'id' => $entry->supplier->id,
                    'name' => $entry->supplier->name,
                ] : null,

                'user' => $entry->user ? [
                    'id' => $entry->user->id,
                    'name' => $entry->user->name,
                ] : null,

                'items' => $entry->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'name' => $item->product->name ?? null,
                        'unit' => $item->product->unit->abreviation ?? null,
                    ];
                }),
            ];
        });

        $entries->setCollection($data);

        return response()->json([
            'message' => 'succes',
            'status' => 200,
            'data' => $entries
        ]);
    }
    // public function index()
    // {
    //     $entries = StockEntry::with([
    //         'supplier:id,name',
    //         'user:id,name',
    //         'items.product:id,name,unit_id',
    //         'items.product.unit:id,abreviation'
    //     ])->where('status', '!=', 'deleted')
    //         ->latest()
    //         ->paginate(10);

    //     $data = $entries->getCollection()->map(function ($entry) {
    //         return [
    //             'id' => $entry->id,
    //             'reference' => $entry->reference,
    //             'transaction_date' => $entry->transaction_date,

    //             'supplier' => $entry->supplier ? [
    //                 'id' => $entry->supplier->id,
    //                 'name' => $entry->supplier->name,
    //             ] : null,

    //             'user' => $entry->user ? [
    //                 'id' => $entry->user->id,
    //                 'name' => $entry->user->name,
    //             ] : null,

    //             'items' => $entry->items->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'quantity' => $item->quantity,
    //                     'name' => $item->product->name ?? null,
    //                     'unit' => $item->product->unit->abreviation ?? null,
    //                 ];
    //             }),
    //         ];
    //     });

    //     $entries->setCollection($data);

    //     return response()->json([
    //         'message' => 'succes',
    //         'status' => 200,
    //         'data' => $entries
    //     ]);
    // }
}
