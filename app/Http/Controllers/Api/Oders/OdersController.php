<?php

namespace App\Http\Controllers\Api\Oders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class OdersController extends Controller
{
    #[OA\Post(
        path: "/api/v1/distributor/orders",
        summary: "Créer une commande distributeur multi produits",
        tags: ["Distributor Orders"],
        security: [["bearerAuth" => []]],

        requestBody: new OA\RequestBody(
            required: true,

            content: new OA\MediaType(
                mediaType: "application/json",

                schema: new OA\Schema(

                    required: [
                        "order_date",
                        "products"
                    ],

                    properties: [

                        new OA\Property(
                            property: "delivery_address",
                            type: "string",
                            example: "Goma Himbi"
                        ),

                        new OA\Property(
                            property: "order_date",
                            type: "string",
                            format: "date",
                            example: "2026-05-20"
                        ),

                        new OA\Property(
                            property: "products",
                            type: "array",

                            items: new OA\Items(

                                properties: [

                                    new OA\Property(
                                        property: "product_id",
                                        type: "integer",
                                        example: 1
                                    ),

                                    new OA\Property(
                                        property: "quantity",
                                        type: "integer",
                                        example: 5
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            )
        ),

        responses: [

            new OA\Response(
                response: 201,
                description: "Commande créée avec succès"
            ),

            new OA\Response(
                response: 401,
                description: "Non authentifié"
            ),

            new OA\Response(
                response: 404,
                description: "Produit introuvable"
            ),

            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            ),

            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            ),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [

            'delivery_address' => [
                'nullable',
                'string'
            ],

            'order_date' => [
                'required',
                'date'
            ],

            'products' => [
                'required',
                'array',
                'min:1'
            ],

            'products.*.product_id' => [
                'required',
                'integer',
                'exists:products,id'
            ],

            'products.*.quantity' => [
                'required',
                'integer',
                'min:1'
            ],

        ]);

        if ($validator->fails()) {

            return response()->json([

                'success' => false,

                'status' => 422,

                'message' => 'Erreur de validation',

                'errors' => $validator->errors(),

                'data_received' => $request->all()

            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {

            $distributor = Auth::guard('distributor')->user();

            if (! $distributor) {

                return response()->json([

                    'success' => false,

                    'status' => 401,

                    'message' => 'Non authentifié'

                ], 401);
            }

            $total = 0;

            $order = Order::create([

                'distributor_id' => $distributor->id,

                'reference' => 'ORD-' . strtoupper(uniqid()),

                'status' => 'pending',

                'delivery_address' => $validated['delivery_address'] ?? null,

                'note' => 'Commande des produits',

                'order_date' => $validated['order_date'],

                'amount' => 0,

                'total' => 0,
            ]);

            foreach ($validated['products'] as $item) {

                $product = Product::find($item['product_id']);

                if (! $product) {

                    DB::rollBack();

                    return response()->json([

                        'success' => false,

                        'status' => 404,

                        'message' => 'Produit introuvable',

                        'product_id' => $item['product_id']

                    ], 404);
                }

                $quantity = (int) $item['quantity'];

                $unitPrice = (float) $product->wholesale_price;

                $subtotal = $quantity * $unitPrice;

                $total += $subtotal;

                $order->items()->create([

                    'product_id' => $product->id,

                    'quantity' => $quantity,

                    'unit_price' => $unitPrice,

                    'subtotal' => $subtotal,
                ]);
            }

            $order->update([
                'total' => $total
            ]);

            DB::commit();

            $order->load([
                'distributor',
                'items.product'
            ]);

            return response()->json([

                'success' => true,

                'status' => 201,

                'message' => 'Commande créée avec succès',

                'data' => $order

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'status' => 500,

                'message' => 'Erreur lors de la création de la commande',

                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Erreur interne du serveur'

            ], 500);
        }
    }
    #[OA\Post(
        path: "/api/v1/distributor/orders/{id}",
        summary: "Modifier une commande distributeur",
        tags: ["Distributor Orders"],
        security: [["bearerAuth" => []]],

        parameters: [

            new OA\Parameter(
                name: "id",
                description: "ID de la commande",
                in: "path",
                required: true,

                schema: new OA\Schema(
                    type: "integer",
                    example: 1
                )
            ),
        ],

        requestBody: new OA\RequestBody(
            required: true,

            content: new OA\MediaType(
                mediaType: "application/json",

                schema: new OA\Schema(

                    required: [
                        "order_date",
                        "products"
                    ],

                    properties: [

                        new OA\Property(
                            property: "delivery_address",
                            type: "string",
                            example: "Goma Katindo"
                        ),

                        new OA\Property(
                            property: "order_date",
                            type: "string",
                            format: "date",
                            example: "2026-05-21"
                        ),

                        new OA\Property(
                            property: "products",
                            type: "array",

                            items: new OA\Items(

                                properties: [

                                    new OA\Property(
                                        property: "product_id",
                                        type: "integer",
                                        example: 1
                                    ),

                                    new OA\Property(
                                        property: "quantity",
                                        type: "integer",
                                        example: 3
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            )
        ),

        responses: [

            new OA\Response(
                response: 200,
                description: "Commande modifiée avec succès"
            ),

            new OA\Response(
                response: 401,
                description: "Non authentifié"
            ),

            new OA\Response(
                response: 403,
                description: "Accès refusé"
            ),

            new OA\Response(
                response: 404,
                description: "Commande ou produit introuvable"
            ),

            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            ),

            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            ),
        ]
    )]
    public function updateData(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [

            'delivery_address' => [
                'nullable',
                'string'
            ],

            'order_date' => [
                'required',
                'date'
            ],

            'products' => [
                'required',
                'array',
                'min:1'
            ],

            'products.*.product_id' => [
                'required',
                'integer',
                'exists:products,id'
            ],

            'products.*.quantity' => [
                'required',
                'integer',
                'min:1'
            ],

        ]);

        if ($validator->fails()) {

            return response()->json([

                'success' => false,

                'status' => 422,

                'message' => 'Erreur de validation',

                'errors' => $validator->errors()

            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {

            $distributor = Auth::guard('distributor')->user();

            if (! $distributor) {

                return response()->json([

                    'success' => false,

                    'status' => 401,

                    'message' => 'Non authentifié'

                ], 401);
            }

            $order = Order::with('items')->find($id);

            if (! $order) {

                return response()->json([

                    'success' => false,

                    'status' => 404,

                    'message' => 'Commande introuvable'

                ], 404);
            }

            if ($order->distributor_id !== $distributor->id) {

                return response()->json([

                    'success' => false,

                    'status' => 403,

                    'message' => 'Accès refusé'

                ], 403);
            }

            $order->items()->delete();

            $total = 0;

            $order->update([

                'delivery_address' => $validated['delivery_address'] ?? null,

                'order_date' => $validated['order_date'],
            ]);

            foreach ($validated['products'] as $item) {

                $product = Product::find($item['product_id']);

                if (! $product) {

                    DB::rollBack();

                    return response()->json([

                        'success' => false,

                        'status' => 404,

                        'message' => 'Produit introuvable',

                        'product_id' => $item['product_id']

                    ], 404);
                }

                $quantity = (int) $item['quantity'];

                $unitPrice = (float) $product->wholesale_price;

                $subtotal = $quantity * $unitPrice;

                $total += $subtotal;

                $order->items()->create([

                    'product_id' => $product->id,

                    'quantity' => $quantity,

                    'unit_price' => $unitPrice,

                    'subtotal' => $subtotal,
                ]);
            }

            $order->update([
                'total' => $total
            ]);

            DB::commit();

            $order->load([
                'distributor',
                'items.product'
            ]);

            return response()->json([

                'success' => true,

                'status' => 200,

                'message' => 'Commande modifiée avec succès',

                'data' => $order

            ], 200);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'status' => 500,

                'message' => 'Erreur lors de la modification de la commande',

                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Erreur interne du serveur'

            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/ordersGetAllData",
        summary: "Lister toutes les commandes du distributeur connecté",
        tags: ["Distributor Orders"],
        security: [["bearerAuth" => []]],

        parameters: [

            new OA\Parameter(
                name: "status",
                description: "Filtrer par statut",
                in: "query",
                required: false,

                schema: new OA\Schema(
                    type: "string",
                    example: "pending"
                )
            ),

            new OA\Parameter(
                name: "search",
                description: "Recherche par référence",
                in: "query",
                required: false,

                schema: new OA\Schema(
                    type: "string",
                    example: "ORD-"
                )
            ),

            new OA\Parameter(
                name: "per_page",
                description: "Nombre d'éléments par page",
                in: "query",
                required: false,

                schema: new OA\Schema(
                    type: "integer",
                    example: 10
                )
            ),
        ],

        responses: [

            new OA\Response(
                response: 200,
                description: "Liste des commandes récupérée avec succès"
            ),

            new OA\Response(
                response: 401,
                description: "Non authentifié"
            ),

            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            ),
        ]
    )]
    public function ordersGetAllData(Request $request): JsonResponse
    {
        try {

            $distributor = Auth::guard('distributor')->user();

            if (! $distributor) {

                return response()->json([

                    'success' => false,

                    'status' => 401,

                    'message' => 'Non authentifié'

                ], 401);
            }

            $query = Order::with([
                'items.product',
                'distributor',
                'confirmedBy',
                'rejectedBy'
            ])
                ->where(
                    'distributor_id',
                    $distributor->id
                )
                ->latest();

            /**
             * Filtre statut
             */
            if ($request->filled('status')) {

                $query->where(
                    'status',
                    $request->status
                );
            }

            /**
             * Recherche référence
             */
            if ($request->filled('search')) {

                $query->where(
                    'reference',
                    'LIKE',
                    '%' . $request->search . '%'
                );
            }

            $orders = $query->paginate(
                $request->per_page ?? 10
            );

            return response()->json([

                'success' => true,

                'status' => 200,

                'message' => 'Liste des commandes récupérée avec succès',

                'data' => $orders

            ], 200);
        } catch (\Throwable $e) {

            return response()->json([

                'success' => false,

                'status' => 500,

                'message' => 'Erreur lors de la récupération des commandes',

                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Erreur interne du serveur'

            ], 500);
        }
    }
}
