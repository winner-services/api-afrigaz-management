<?php

namespace App\Http\Controllers\Api\Tank;

use App\Http\Controllers\Controller;
use App\Models\Tank;
use App\Models\TankMovement;
use App\Services\TankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class TankController extends Controller
{
    public function __construct(
        protected TankService $service
    ) {}

    #[OA\Get(
        path: "/api/v1/tankGetAllData",
        summary: "Lister",
        tags: ["Tanks"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('paginate', 10);
        $search = $request->query('q', '');
        $status = $request->query('status');

        $tanks = Tank::with('user:id,name')

            ->when(
                $search,
                fn($q) =>
                $q->where('name', 'like', "%$search%")
            )

            ->when(
                $status,
                fn($q) =>
                $q->where('status', $status)
            )
            ->where('status', '!=', 'deleted')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'tanks' => $tanks,
            'message' => 'successfully',
            'status' => 200
        ]);
    }
    #[OA\Get(
        path: "/api/v1/tankGetOptionsData",
        summary: "Lister",
        tags: ["Tanks"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function getOptionTank(Request $request): JsonResponse
    {
        $search = $request->query('q', '');
        $tanks = Tank::when(
            $search,
            fn($q) =>
            $q->where('name', 'like', "%$search%")
        )
            ->orderBy('id', 'desc')->get();

        return response()->json([
            'tanks' => $tanks,
            'message' => 'successfully',
            'status' => 200
        ]);
    }

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
                'error' => $e->getMessage(),
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
                    ),
                    new OA\Property(
                        property: "operation_date",
                        type: "string",
                        format: "date",
                        example: "2023-10-10",
                        description: "Date de l'opération"

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
                'quantity' => 'required|numeric|min:1',
                'operation_date' => 'required|date',
            ]);

            $tank = $this->service->addGas(
                $data['tank_id'],
                $data['quantity'],
                $data['operation_date']
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
                    ),
                    new OA\Property(
                        property: "operation_date",
                        type: "string",
                        format: "date",
                        example: "2023-10-10",
                        description: "Date de l'opération"

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
                'type' => 'required|in:augmentation,diminution',
                'operation_date' => 'nullable|date',
            ]);

            $tank = $this->service->adjust(
                $data['tank_id'],
                $data['quantity'],
                $data['type'],
                $data['operation_date'] ?? now()
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

    #[OA\Get(
        path: "/api/v1/tankMovementGetAllData",
        summary: "Lister",
        tags: ["Tanks"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]

    public function history(Request $request): JsonResponse
    {
        $perPage = $request->query('paginate', 10);
        $search = $request->query('q', '');
        $type = $request->query('type');
        $tankId = $request->query('tank_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $movements = TankMovement::query()
            ->with([
                'tank:id,name',
                'user:id,name'
            ])

            ->when(
                $tankId,
                fn($q) =>
                $q->where('tank_id', $tankId)
            )

            ->when(
                $type,
                fn($q) =>
                $q->where('type', $type)
            )

            ->when(
                $startDate && $endDate,
                fn($q) =>
                $q->whereBetween('created_at', [$startDate, $endDate])
            )
            ->when(
                $startDate && !$endDate,
                fn($q) =>
                $q->whereDate('created_at', '>=', $startDate)
            )
            ->when(
                !$startDate && $endDate,
                fn($q) =>
                $q->whereDate('created_at', '<=', $endDate)
            )

            ->when(
                $search,
                fn($q) =>
                $q->where(function ($q2) use ($search) {
                    $q2->where('note', 'like', "%{$search}%")
                        ->orWhere('reference_type', 'like', "%{$search}%")
                        ->orWhere('reference_id', 'like', "%{$search}%");
                })
            )

            ->orderByDesc('id')

            ->paginate($perPage);

        return response()->json([
            'movements' => $movements,
            'message' => 'successfully',
            'status' => 200
        ]);
    }

    #[OA\Get(
        path: "/api/v1/approvisionnementGetAllData",
        summary: "Lister",
        tags: ["Tanks"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]

    public function getAddGasHistory(Request $request): JsonResponse
    {
        $perPage = $request->query('paginate', 10);
        $search = $request->query('q', '');
        $type = $request->query('type');
        $tankId = $request->query('tank_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $movements = TankMovement::query()
            ->with([
                'tank:id,name',
                'user:id,name'
            ])

            ->when(
                $tankId,
                fn($q) =>
                $q->where('tank_id', $tankId)
            )

            ->when(
                $type,
                fn($q) =>
                $q->where('type', $type)
            )

            ->when(
                $startDate && $endDate,
                fn($q) =>
                $q->whereBetween('created_at', [$startDate, $endDate])
            )
            ->when(
                $startDate && !$endDate,
                fn($q) =>
                $q->whereDate('created_at', '>=', $startDate)
            )
            ->when(
                !$startDate && $endDate,
                fn($q) =>
                $q->whereDate('created_at', '<=', $endDate)
            )

            ->when(
                $search,
                fn($q) =>
                $q->where(function ($q2) use ($search) {
                    $q2->where('note', 'like', "%{$search}%")
                        ->orWhere('reference_type', 'like', "%{$search}%")
                        ->orWhere('reference_id', 'like', "%{$search}%");
                })
            )
            ->where('type', 'entry')
            ->orderByDesc('id')

            ->paginate($perPage);

        return response()->json([
            'movements' => $movements,
            'message' => 'successfully',
            'status' => 200
        ]);
    }

    #[OA\Put(
        path: "/api/v1/tankUpdate/{id}",
        summary: "Mettre à jour un tank",
        description: "Permet de modifier les informations d’un tank (nom, capacité, niveau actuel, statut)",
        tags: ["Tanks"],

        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID du tank",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Tank Principal"),
                    new OA\Property(property: "capacity", type: "number", format: "float", example: 1000.50),
                    new OA\Property(property: "current_level", type: "number", format: "float", example: 500.25)
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: "Tank mis à jour avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Tank mis à jour avec succès"),
                        new OA\Property(property: "status", type: "integer", example: 200),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "Tank Principal"),
                                new OA\Property(property: "capacity", type: "number", example: 1000.50),
                                new OA\Property(property: "current_level", type: "number", example: 500.25),
                                new OA\Property(property: "status", type: "string", example: "active"),
                            ],
                            type: "object"
                        )
                    ],
                    type: "object"
                )
            ),

            new OA\Response(
                response: 422,
                description: "Erreur de validation ou métier",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Impossible de mettre à jour le tank"),
                        new OA\Property(
                            property: "errors",
                            type: "array",
                            items: new OA\Items(type: "string", example: "Le niveau actuel ne peut pas dépasser la capacité")
                        ),
                        new OA\Property(property: "status", type: "integer", example: 422)
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        try {

            $data = $request->validate([
                'name' => "nullable|string|max:255|unique:tanks,name,$id",
                'capacity' => 'nullable|numeric|min:0',
                'current_level' => 'nullable|numeric|min:0',
            ]);

            $tank = Tank::findOrFail($id);

            if (isset($data['current_level']) && isset($data['capacity'])) {
                if ($data['current_level'] > $data['capacity']) {
                    throw new \Exception("Le niveau actuel ne peut pas dépasser la capacité");
                }
            }

            if (isset($data['capacity']) && $tank->current_level > $data['capacity']) {
                throw new \Exception("La capacité ne peut pas être inférieure au niveau actuel");
            }

            $tank->update($data);

            return response()->json([
                'message' => 'Tank mis à jour avec succès',
                'status' => 200,
                'data' => $tank
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Throwable $e) {

            Log::error('Tank update error', [
                'error' => $e->getMessage(),
                'tank_id' => $id
            ]);

            return response()->json([
                'message' => 'Impossible de mettre à jour le tank',
                'errors' => [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }

    #[OA\Put(
        path: "/api/v1/tankDelete/{id}",
        summary: "Supprimer",
        tags: ["Tanks"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Supprimée"),
            new OA\Response(response: 404, description: "Non trouvée")
        ]
    )]
    public function destroy($id): JsonResponse
    {
        try {

            $tank = Tank::findOrFail($id);

            $tank->update([
                'status' => 'deleted'
            ]);

            return response()->json([
                'message' => 'Tank supprimé avec succès',
                'status' => 200
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'errors' => [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }
}
