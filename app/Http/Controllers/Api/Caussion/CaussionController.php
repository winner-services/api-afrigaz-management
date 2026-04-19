<?php

namespace App\Http\Controllers\Api\Caussion;

use App\Http\Controllers\Controller;
use App\Models\Caussion;
use App\Models\CaussionItem;
use App\Models\Currency;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CaussionController extends Controller
{
    #[OA\Post(
        path: '/api/v1/caussionStoreData',
        summary: 'Créer une caution avec calcul automatique',
        description: 'Crée une caution et calcule automatiquement le montant total basé sur quantity × price des produits.',
        tags: ['Caussions'],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['transaction_date', 'category_distributor_id', 'items'],
                properties: [
                    new OA\Property(
                        property: "transaction_date",
                        type: "string",
                        format: "date",
                        example: "2026-04-19"
                    ),

                    new OA\Property(
                        property: "category_distributor_id",
                        type: "integer",
                        example: 1,
                        description: "ID du distributeur"
                    ),

                    new OA\Property(
                        property: "items",
                        type: "array",
                        description: "Liste des produits",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(
                                    property: "product_id",
                                    type: "integer",
                                    example: 5
                                ),
                                new OA\Property(
                                    property: "quantity",
                                    type: "integer",
                                    example: 2
                                )
                            ]
                        )
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: 'Caution créée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Caution créée avec montant calculé automatiquement"),
                        new OA\Property(property: "total_amount", type: "number", example: 150.00),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
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
    public function storeData(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'category_distributor_id' => 'required|exists:category_distributors,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $caussion = Caussion::create([
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'category_distributor_id' => $request->category_distributor_id,
                'addedBy' => Auth::id(),
            ]);

            $productIds = collect($request->items)->pluck('product_id');

            $products = Product::whereIn('id', $productIds)
                ->get()
                ->keyBy('id');


            foreach ($request->items as $item) {
                CaussionItem::create([
                    'caussion_id' => $caussion->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $products->get($item['product_id'])->wholesale_price ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Caution créée avec succès',
                'data' => $caussion->load('items.product')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/v1/caussionUpdate/{id}',
        summary: 'Modifier une caution',
        description: 'Met à jour une caution et recalcule automatiquement le montant total.',
        tags: ['Caussions'],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID de la caution",
                schema: new OA\Schema(type: "integer")
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['transaction_date', 'category_distributor_id', 'items'],
                properties: [
                    new OA\Property(property: "transaction_date", type: "string", format: "date"),
                    new OA\Property(property: "category_distributor_id", type: "integer"),
                    new OA\Property(
                        property: "items",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "product_id", type: "integer"),
                                new OA\Property(property: "quantity", type: "integer"),
                            ]
                        )
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(response: 200, description: 'Caution mise à jour'),
            new OA\Response(response: 404, description: 'Caution introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 500, description: 'Erreur serveur'),
        ]
    )]
    public function updateData(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'category_distributor_id' => 'required|exists:category_distributors,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $caussion = Caussion::findOrFail($id);

            $caussion->update([
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'category_distributor_id' => $request->category_distributor_id,
            ]);

            // 🔥 delete old items
            CaussionItem::where('caussion_id', $id)->delete();

            $productIds = collect($request->items)->pluck('product_id');

            $products = Product::whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            // 🔥 insert new items
            foreach ($request->items as $item) {
                CaussionItem::create([
                    'caussion_id' => $caussion->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $products->get($item['product_id'])->wholesale_price ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Caution mise à jour',
                'data' => $caussion->load('items.product')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    #[OA\Put(
        path: '/api/v1/caussionDelete/{id}',
        summary: 'Supprimer une caution',
        tags: ['Caussions'],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],

        responses: [
            new OA\Response(response: 200, description: 'Caution supprimée'),
            new OA\Response(response: 404, description: 'Introuvable'),
            new OA\Response(response: 500, description: 'Erreur serveur'),
        ]
    )]
    public function destroy($id)
    {
        try {
            $caussion = Caussion::findOrFail($id);

            $caussion->status = 'deleted';
            $caussion->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Caution supprimée'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/caussionGetAllData',
        summary: 'Lister les cautions',
        tags: ['Caussions'],

        parameters: [
            new OA\Parameter(name: "q", in: "query", required: false),
            new OA\Parameter(name: "per_page", in: "query", required: false, example: 10),
            new OA\Parameter(name: "category_distributor_id", in: "query", required: false),
        ],

        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des cautions',
                content: new OA\JsonContent(type: "object")
            )
        ]
    )]
    public function getData(Request $request)
    {
        try {
            $devise = Currency::where('status', 'created')->latest()->get();
            $perPage = $request->query('per_page', 10);
            $search = $request->query('q', '');

            $query = Caussion::with(['items.product', 'distributor', 'distributor.categoryDistributor', 'addedBy'])
                ->where('status', '!=', 'deleted');

            if ($request->has('category_distributor_id')) {
                $query->where('category_distributor_id', $request->query('category_distributor_id'));
            }

            if (!empty($search)) {
                $query->whereHas('distributor', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                });
            }

            $data = $query
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'status' => 200,
                'devise' => $devise,
                'data' => $data
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
