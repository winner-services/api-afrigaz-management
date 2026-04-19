<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\Caussion;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\ShippingItem;
use App\Services\SaleService;
use App\Services\StockException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;
use OpenApi\Attributes as OA;

class ShippingControlle extends Controller
{

    #[OA\Post(
        path: '/api/v1/shippingStoreData',
        summary: 'Programmer une livraison',
        description: 'Crée une livraison planifiée à partir d’une caution déjà payée. Aucun produit n’est encore livré.',
        tags: ['Shippings'],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['caussion_id', 'branch_id', 'distributor_id', 'planned_date', 'transaction_date'],
                properties: [
                    new OA\Property(property: "caussion_id", type: "integer", example: 1),
                    new OA\Property(property: "branch_id", type: "integer", example: 1),
                    new OA\Property(property: "distributor_id", type: "integer", example: 5),

                    new OA\Property(
                        property: "planned_date",
                        type: "string",
                        format: "date",
                        example: "2026-04-25"
                    ),

                    new OA\Property(
                        property: "transaction_date",
                        type: "string",
                        format: "date",
                        example: "2026-04-19"
                    ),

                    new OA\Property(
                        property: "commentaire",
                        type: "string",
                        example: "Livraison prévue matin"
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: 'Livraison programmée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Livraison programmée avec succès"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),

            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 500, description: 'Erreur serveur')
        ]
    )]

    public function storeData(Request $request)
    {
        $request->validate([
            'caussion_id' => 'required|exists:caussions,id',
            'branch_id' => 'nullable|exists:branches,id',
            'distributor_id' => 'required|exists:distributors,id',
            'planned_date' => 'required|date',
            'transaction_date' => 'required|date',
            'commentaire' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $caussion = Caussion::with('items')->findOrFail($request->caussion_id);

            $reference = 'SHIP-' . strtoupper(uniqid());
            $branch_id = $request->branch_id ?? 1;

            // 📦 créer shipping
            $shipping = Shipping::create([
                'reference' => $reference,
                'caussion_id' => $caussion->id,
                'branch_id' => $branch_id,
                'distributor_id' => $request->distributor_id,
                'addedBy' => Auth::id(),
                'transaction_date' => $request->transaction_date,
                'commentaire' => $request->commentaire ?? null,
                'planned_date' => $request->planned_date,
                'status' => 'pending',
            ]);

            foreach ($caussion->items as $item) {

                ShippingItem::create([
                    'shipping_id' => $shipping->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'delivered_quantity' => 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Livraison planifiée avec succès',
                'reference' => $reference,
                'data' => $shipping->load('items.product')
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la livraison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deliver(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:shipping_items,id',
            'items.*.delivered_quantity' => 'required|integer|min:0',
        ]);

        try {

            $result = SaleService::deliverShipping(
                $id,
                $request->items
            );

            return response()->json([
                'success' => true,
                'message' => 'Livraison exécutée avec succès',
                'status' => $result['status'],
                'data' => $result['shipping']->load('items.product')
            ]);
        } catch (StockException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur de stock',
                'errors' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function deliver(Request $request, $id)
    // {
    //     $request->validate([
    //         'items' => 'required|array',
    //         'items.*.id' => 'required|exists:shipping_items,id',
    //         'items.*.delivered_quantity' => 'required|integer|min:0',
    //     ]);

    //     try {

    //         DB::beginTransaction();

    //         $shipping = Shipping::with('items')->findOrFail($id);

    //         foreach ($request->items as $itemData) {

    //             $item = ShippingItem::findOrFail($itemData['id']);

    //             $item->delivered_quantity += $itemData['delivered_quantity'];
    //             $item->save();
    //         }

    //         // 🔥 recalcul status
    //         $total = $shipping->items->sum('quantity');
    //         $delivered = $shipping->items->sum('delivered_quantity');

    //         if ($delivered == 0) {
    //             $status = 'pending';
    //         } elseif ($delivered < $total) {
    //             $status = 'partial';
    //         } else {
    //             $status = 'completed';
    //         }

    //         $shipping->update([
    //             'status' => $status
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Livraison mise à jour',
    //             'status' => $status
    //         ]);
    //     } catch (\Exception $e) {

    //         DB::rollBack();

    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function store(Request $request): JsonResponse
    // {
    //     try {

    //         $request->validate([
    //             'products' => 'required|array|min:1',
    //             'products.*.product_id' => 'required|exists:products,id',
    //             'products.*.quantity' => 'required|integer|min:1',
    //             'distributor_id' => 'nullable|exists:distributors,id',
    //             'commentaire' => 'nullable|string',
    //             'transaction_date' => 'date|string',
    //         ]);
    //         $branch_id = request($request->branch_id, 1);

    //         $livraison = SaleService::createShipping(
    //             $branch_id,
    //             $request->products,
    //             $request->distributor_id,
    //             $request->transaction_date ?? now(),
    //             $request->commentaire
    //         );
    //         return response()->json([
    //             'message' => 'Vente enregistrée',
    //             'status' => 201,
    //             'data' => $livraison
    //         ], 201);
    //     } catch (\Illuminate\Validation\ValidationException $e) {

    //         return response()->json([
    //             'message' => 'Erreur de validation',
    //             'errors' => $e->errors()
    //         ], 422);
    //     } catch (\App\Services\StockException $e) {

    //         return response()->json([
    //             'message' => 'Erreur de stock',
    //             'errors' => $e->getErrors()
    //         ], 400);
    //     } catch (\Exception $e) {

    //         return response()->json([
    //             'message' => 'Erreur lors de la création de la vente',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    #[OA\Get(
        path: "/api/v1/shippingsGetAllData",
        summary: "Historique complet des livraisons",
        tags: ["Shippings"],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre de résultats par page",
                schema: new OA\Schema(type: "integer", example: 20)
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                description: "Numéro de page",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée des ventes",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "reference", type: "string"),
                                    new OA\Property(property: "transaction_date", type: "string", format: "date"),
                                    new OA\Property(
                                        property: "customer",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "user",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "items",
                                        type: "array",
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: "id", type: "integer"),
                                                new OA\Property(property: "quantity", type: "integer"),
                                                new OA\Property(property: "unit_price", type: "number"),
                                                new OA\Property(
                                                    property: "product",
                                                    type: "object",
                                                    properties: [
                                                        new OA\Property(property: "id", type: "integer"),
                                                        new OA\Property(property: "name", type: "string")
                                                    ]
                                                )
                                            ]
                                        )
                                    ),

                                    new OA\Property(property: "status", type: "string")
                                ]
                            )
                        ),
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            )
        ]
    )]

    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $branches = Branche::latest()->get();

        $shipping = Shipping::with(['branch', 'distributor', 'user', 'items.product'])
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 200,
            'branches' => $branches,
            'data' => $shipping
        ]);
    }

    #[OA\Get(
        path: "/api/v1/shippingByBranchGetData",
        summary: "Historique des livraisons par succursale",
        tags: ["Shippings"],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre de résultats par page",
                schema: new OA\Schema(type: "integer", example: 20)
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                description: "Numéro de page",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée des ventes",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "reference", type: "string"),
                                    new OA\Property(property: "transaction_date", type: "string", format: "date"),
                                    new OA\Property(
                                        property: "customer",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "user",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "items",
                                        type: "array",
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: "id", type: "integer"),
                                                new OA\Property(property: "quantity", type: "integer"),
                                                new OA\Property(property: "unit_price", type: "number"),
                                                new OA\Property(
                                                    property: "product",
                                                    type: "object",
                                                    properties: [
                                                        new OA\Property(property: "id", type: "integer"),
                                                        new OA\Property(property: "name", type: "string")
                                                    ]
                                                )
                                            ]
                                        )
                                    ),
                                    new OA\Property(property: "total_amount", type: "number"),
                                    new OA\Property(property: "paid_amount", type: "number"),
                                    new OA\Property(property: "status", type: "string")
                                ]
                            )
                        ),
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            )
        ]
    )]
    public function indexByBranche(Request $request)
    {
        $branches = Branche::latest()->get();
        $user = Auth::user();
        $branch = Branche::where('user_id', $user->id)->first();

        $brancheId = request('branche_id', $branch->id);
        $perPage = $request->query('per_page', 20);
        $search = request('q', '');

        $sales = Shipping::with(['branch', 'distributor', 'user', 'items.product'])
            ->where('branch_id', $brancheId)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    // Recherche sur client
                    $q->whereHas('distributor', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                        // Recherche sur utilisateur (vendeur)
                        ->orWhereHas('user', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.product', function ($q2) use ($search) {
                            $q2->where('designation', 'like', "%{$search}%");
                        })
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 200,
            'branches' => $branches,
            'data' => $sales
        ]);
    }
}
