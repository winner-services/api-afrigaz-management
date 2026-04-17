<?php

namespace App\Http\Controllers\Api\ReturnBoitle;

use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Models\BottleReturn;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ReturnBootleController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    #[OA\Post(
        path: "/api/bottleReturnStore",
        summary: "Enregistrer un retour de bouteilles",
        description: "Retour multi-produits avec états (good, damaged, repair)",
        tags: ["Bottle Returns"],
        security: [["bearerAuth" => []]],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["branch_id", "products"],

                properties: [
                    new OA\Property(property: "branch_id", type: "integer", example: 1),
                    new OA\Property(property: "note", type: "string", example: "Retour de bouteilles"),

                    new OA\Property(
                        property: "products",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "product_id", type: "integer", example: 1),

                                new OA\Property(
                                    property: "returns",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(
                                                property: "condition",
                                                type: "string",
                                                enum: ["good", "damaged", "repair"],
                                                example: "good"
                                            ),
                                            new OA\Property(
                                                property: "quantity",
                                                type: "integer",
                                                example: 40
                                            )
                                        ]
                                    )
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
                description: "Retour enregistré avec succès"
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            ),
            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            )
        ]
    )]
    public function returnMultipleBottles(Request $request)
    {
        try {

            $data = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'note' => 'nullable|string',
                'date_operation' => 'nullable|date',

                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',

                'products.*.returns' => 'required|array|min:1',
                'products.*.returns.*.condition' => 'required|in:good,damaged,repair',
                'products.*.returns.*.quantity' => 'required|integer|min:1',
            ]);

            $result = $this->stockService->storeReturn($data);

            return response()->json([
                'success' => true,
                'message' => 'Retour des bouteilles enregistré avec succès',
                'data' => $result
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\App\Services\StockException $e) {

            return response()->json([
                'message' => 'Erreur de stock',
                'errors' => $e->getErrors()
            ], 400);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Erreur lors de la création de la vente',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getData(Request $request)
    {
        try {

            $user = Auth::user();
            $branchId = $user->branch_id;

            $perPage = $request->query('per_page', 10);
            $search = $request->query('q', '');
            $sortField = $request->query('sort_field', 'id');
            $sortDirection = $request->query('sort_direction', 'desc');

            $allowedSortFields = [
                'id',
                'reference',
                'total_items',
                'return_date',
                'created_at'
            ];

            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'id';
            }

            $query = BottleReturn::with([
                'agent:id,name',
                'branch:id,name',
                'items.product:id,name',
                'user:id,name'
            ])
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($q2) use ($search) {
                        $q2->where('reference', 'like', "%$search%")
                            ->orWhere('note', 'like', "%$search%");
                    });
                })

                ->orderBy($sortField, $sortDirection)
                ->paginate($perPage);

            return response()->json([
                'message' => 'Liste des retours',
                'data' => $query
            ]);
        } catch (\Exception $e) {

            Log::error('BottleReturn getData error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la récupération des données'
            ], 500);
        }
    }
}
