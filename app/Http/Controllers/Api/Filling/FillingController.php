<?php

namespace App\Http\Controllers\Api\Filling;

use App\Http\Controllers\Controller;
use App\Services\FillingService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FillingController extends Controller
{
    public function __construct(
        protected FillingService $service
    ) {}

    #[OA\Post(
        path: "/api/tanks/fillings",
        summary: "Créer un remplissage de tank",
        description: "Permet d’enregistrer un remplissage de tank avec plusieurs produits (bouteilles)",
        tags: ["Tanks"],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["tank_id", "items"],
                properties: [
                    new OA\Property(
                        property: "tank_id",
                        type: "integer",
                        example: 1
                    ),

                    new OA\Property(
                        property: "items",
                        type: "array",
                        description: "Liste des produits à remplir",
                        items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "product_id",
                                    type: "integer",
                                    example: 1
                                ),
                                new OA\Property(
                                    property: "Number_of_bottles",
                                    type: "integer",
                                    example: 10,
                                    description: "Nombre de bouteilles"
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
                description: "Remplissage effectué avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Remplissage effectué avec succès"),
                        new OA\Property(property: "status", type: "integer", example: 201),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),

            new OA\Response(
                response: 422,
                description: "Erreur de validation",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Erreur de validation"),
                        new OA\Property(property: "errors", type: "object"),
                        new OA\Property(property: "status", type: "integer", example: 422)
                    ]
                )
            )
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.Number_of_bottles' => 'required|integer|min:1',
        ]);

        $filling = $this->service->processFilling($data);

        return response()->json([
            'message' => 'Remplissage effectué avec succès',
            'status' => 201,
            'data' => $filling
        ], 201);
    }
}
