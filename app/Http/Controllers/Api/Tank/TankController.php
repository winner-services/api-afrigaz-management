<?php

namespace App\Http\Controllers\Api\Tank;

use App\Http\Controllers\Controller;
use App\Services\TankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class TankController extends Controller
{
    public function __construct(
        protected TankService $service
    ) {}

    #[OA\Post(
        path: "/api/v1/tankStoreData",
        summary: "Créer un tank",
        description: "Créer un nouveau tank avec capacité et niveau actuel",
        tags: ["Tanks"],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["capacity", "current_level"],
                properties: [
                    new OA\Property(
                        property: "name",
                        type: "string",
                        nullable: true,
                        example: "Tank Principal"
                    ),
                    new OA\Property(
                        property: "capacity",
                        type: "number",
                        format: "float",
                        example: 1000
                    ),
                    new OA\Property(
                        property: "current_level",
                        type: "number",
                        format: "float",
                        example: 500
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: "Tank créé avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "tank", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Tank created successfully"),
                        new OA\Property(property: "status", type: "integer", example: 201)
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
            ),

            new OA\Response(
                response: 500,
                description: "Erreur serveur",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Erreur lors de la création du tank"),
                        new OA\Property(property: "error", type: "string", example: "Internal Server Error"),
                        new OA\Property(property: "status", type: "integer", example: 500)
                    ]
                )
            )
        ]
    )]
    public function store(Request $request)
    {
        try {

            $data = $request->validate([
                'name' => ['nullable', 'string', 'max:255', 'unique:tanks,name'],
                'capacity' => 'required|numeric',
                'current_level' => 'required|numeric|min:0|lte:capacity'
            ]);

            $tank = $this->service->createTank($data);

            return response()->json([
                'tank' => $tank,
                'message' => 'Tank created successfully',
                'status' => 201
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Erreur lors de la création du tank',
                'error' => $e->getMessage(), // ⚠️ à désactiver en prod
                'status' => 500
            ], 500);
        }
    }

    #[OA\Post(
        path: "/api/v1/tankAddGas",
        summary: "Ajouter du gaz dans un tank",
        description: "Augmente le niveau de gaz dans un tank existant en respectant sa capacité",
        tags: ["Tanks"],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["tank_id", "quantity"],
                properties: [
                    new OA\Property(
                        property: "tank_id",
                        type: "integer",
                        example: 1
                    ),
                    new OA\Property(
                        property: "quantity",
                        type: "number",
                        format: "float",
                        example: 100
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: "Gaz ajouté avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Gaz ajouté avec succès"),
                        new OA\Property(property: "tank", type: "object"),
                        new OA\Property(property: "status", type: "integer", example: 200)
                    ]
                )
            ),

            new OA\Response(
                response: 422,
                description: "Erreur de validation ou capacité dépassée",
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

    public function addGas(Request $request)
    {
        try {

            $data = $request->validate([
                'tank_id' => 'required|exists:tanks,id',
                'quantity' => 'required|numeric|min:1'
            ]);

            $tank = $this->service->addGas(
                $data['tank_id'],
                $data['quantity']
            );

            return response()->json([
                'message' => 'Gaz ajouté avec succès',
                'tank' => $tank,
                'status' => 200
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Exception $e) {

            Log::error('Add gas error', [
                'error' => $e->getMessage(),
                'tank_id' => $request->tank_id ?? null
            ]);

            return response()->json([
                'message' => 'Problème de capacité',
                'errors' => $e->getMessage(),
                'status' => 422
            ], 422);
        }
    }

    #[OA\Post(
        path: "/api/v1/tankAdjust",
        summary: "Ajuster le niveau de gaz d’un tank",
        description: "Permet d’augmenter ou diminuer manuellement le niveau de gaz d’un tank",
        tags: ["Tanks"],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["tank_id", "quantity", "type"],
                properties: [
                    new OA\Property(
                        property: "tank_id",
                        type: "integer",
                        example: 1
                    ),
                    new OA\Property(
                        property: "quantity",
                        type: "number",
                        format: "float",
                        example: 50.5,
                        description: "Quantité à ajuster"
                    ),
                    new OA\Property(
                        property: "type",
                        type: "string",
                        enum: ["augmentation", "diminution"],
                        example: "augmentation",
                        description: "Type d’ajustement"
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: "Ajustement effectué avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Ajustement effectué avec succès"),
                        new OA\Property(property: "tank", type: "object"),
                        new OA\Property(property: "status", type: "integer", example: 200)
                    ]
                )
            ),

            new OA\Response(
                response: 422,
                description: "Erreur de validation ou logique métier",
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

    public function adjust(Request $request)
    {
        try {

            $data = $request->validate([
                'tank_id' => 'required|exists:tanks,id',
                'quantity' => 'required|numeric|min:0.1',
                'type' => 'required|in:augmentation,diminution'
            ]);

            $tank = $this->service->adjust(
                $data['tank_id'],
                $data['quantity'],
                $data['type']
            );

            return response()->json([
                'message' => 'Ajustement effectué avec succès',
                'tank' => $tank,
                'status' => 200
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Exception $e) {

            Log::error('Tank adjust error', [
                'error' => $e->getMessage(),
                'tank_id' => $request->tank_id ?? null
            ]);

            return response()->json([
                'message' => 'Erreur de la capacité',
                'error' => $e->getMessage(),
                'status' => 422
            ], 422);
        }
    }


    public function history($tankId)
    {
        return response()->json(
            $this->service->history($tankId)
        );
    }
}
