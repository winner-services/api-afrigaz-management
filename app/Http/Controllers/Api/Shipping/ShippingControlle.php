<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Branche;
use App\Models\Caussion;
use App\Models\Shipping;
use App\Models\ShippingItem;
use App\Services\ImageService;
use App\Services\SaleService;
use App\Services\StockException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ShippingControlle extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

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

    #[OA\Put(
        path: '/api/v1/shippingUpdate/{id}',
        summary: 'Mettre à jour une livraison planifiée',
        description: 'Met à jour une livraison (dates, distributeur, commentaire). Les produits sont automatiquement recalculés à partir de la caution associée.',
        tags: ['Shippings'],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID de la livraison",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['distributor_id', 'planned_date', 'transaction_date'],
                properties: [

                    new OA\Property(
                        property: "branch_id",
                        type: "integer",
                        example: 1,
                        description: "ID de la branche (optionnel)"
                    ),

                    new OA\Property(
                        property: "distributor_id",
                        type: "integer",
                        example: 5,
                        description: "ID du distributeur"
                    ),

                    new OA\Property(
                        property: "planned_date",
                        type: "string",
                        format: "date",
                        example: "2026-04-25",
                        description: "Date prévue de livraison"
                    ),

                    new OA\Property(
                        property: "transaction_date",
                        type: "string",
                        format: "date",
                        example: "2026-04-20",
                        description: "Date de création/modification"
                    ),

                    new OA\Property(
                        property: "commentaire",
                        type: "string",
                        example: "Livraison reportée au matin",
                        description: "Commentaire optionnel"
                    ),
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: 'Livraison mise à jour avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Livraison mise à jour (basée sur la caution)"),

                        new OA\Property(
                            property: "data",
                            type: "object",
                            description: "Shipping avec items recalculés"
                        )
                    ]
                )
            ),

            new OA\Response(
                response: 400,
                description: 'Livraison déjà commencée, modification refusée'
            ),

            new OA\Response(
                response: 404,
                description: 'Livraison introuvable'
            ),

            new OA\Response(
                response: 422,
                description: 'Erreur de validation'
            ),

            new OA\Response(
                response: 500,
                description: 'Erreur serveur'
            )
        ]
    )]
    public function updateData(Request $request, $id)
    {
        $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'distributor_id' => 'required|exists:distributors,id',
            'planned_date' => 'required|date',
            'transaction_date' => 'required|date',
            'commentaire' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $shipping = Shipping::with(['items', 'caussion.items'])
                ->lockForUpdate()
                ->findOrFail($id);

            if ($shipping->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'status' => 200,
                    'message' => 'Impossible de modifier une livraison déjà commencée'
                ], 400);
            }

            $shipping->update([
                'branch_id' => $request->branch_id ?? $shipping->branch_id,
                'distributor_id' => $request->distributor_id,
                'planned_date' => $request->planned_date,
                'transaction_date' => $request->transaction_date,
                'commentaire' => $request->commentaire,
            ]);

            $shipping->items()->delete();

            $caussionItems = $shipping->caussion->items;

            foreach ($caussionItems as $item) {
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
                'status' => 200,
                'message' => 'Livraison mise à jour (basée sur la caution)',
                'data' => $shipping->load('items.product')
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/v1/shippingDeliver/{id}',
        summary: 'Exécuter une livraison',
        description: 'Permet de livrer partiellement ou totalement les produits d’un shipping programmé.',
        tags: ['Shippings'],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID du shipping",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [

                    new OA\Property(
                        property: "items",
                        type: "array",
                        description: "Liste des produits à livrer",

                        items: new OA\Items(
                            properties: [

                                new OA\Property(
                                    property: "id",
                                    type: "integer",
                                    example: 1,
                                    description: "ID du shipping_item"
                                ),

                                new OA\Property(
                                    property: "delivered_quantity",
                                    type: "integer",
                                    example: 3,
                                    description: "Quantité livrée pour cet item"
                                )
                            ]
                        )
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: 'Livraison exécutée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Livraison exécutée avec succès"),
                        new OA\Property(property: "status", type: "string", example: "partial"),

                        new OA\Property(
                            property: "data",
                            type: "object",
                            description: "Shipping avec items mis à jour"
                        )
                    ]
                )
            ),

            new OA\Response(
                response: 404,
                description: 'Shipping introuvable'
            ),

            new OA\Response(
                response: 422,
                description: 'Erreur de validation ou stock insuffisant'
            ),

            new OA\Response(
                response: 500,
                description: 'Erreur serveur'
            )
        ]
    )]
    public function deliver(Request $request, $id)
    {
        $request->validate([
            'planned_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:shipping_items,id',
            'items.*.delivered_quantity' => 'required|integer|min:1',
        ]);

        try {
            $about = About::first();
            if ($about) {
                $this->imageService->transform($about, ['logo', 'logo2']);
            }

            $validItemIds = ShippingItem::where('shipping_id', $id)
                ->pluck('id')
                ->toArray();

            foreach ($request->items as $item) {
                if (!in_array($item['id'], $validItemIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Item ID {$item['id']} n'appartient pas à ce shipping"
                    ], 422);
                }
            }

            $result = SaleService::deliverShipping(
                $id,
                $request->items,
                $request->planned_date
            );

            return response()->json([
                'success' => true,
                'message' => 'Livraison exécutée avec succès',
                'info_company' => $about,
                'status' => $result['status'],
                'data' => $result['shipping']->load('items.product')
            ]);
        } catch (StockException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur de stock',
                'errors' => method_exists($e, 'getErrors')
                    ? $e->getErrors()
                    : $e->getMessage()
            ], 422);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

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
        $branche = Branche::where('user_id', Auth::id())->first();

        if (!$branche) {
            $brancheId = 1;
        } else {
            $brancheId = $branche->id;
        }

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
                        ->orWhereHas('user', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.product', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
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
