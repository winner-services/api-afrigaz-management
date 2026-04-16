<?php

namespace App\Http\Controllers\Api\ReturnBoitle;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',

                'products.*.returns' => 'required|array|min:1',
                'products.*.returns.*.condition' => 'required|in:good,damaged,repair',
                'products.*.returns.*.quantity' => 'required|integer|min:1',
            ]);

            $result = $this->stockService->storeReturn($data);

            return response()->json([
                'message' => 'Retour enregistré avec succès',
                'status' => 201,
                'data' => $result
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Erreur lors du retour des bouteilles',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
