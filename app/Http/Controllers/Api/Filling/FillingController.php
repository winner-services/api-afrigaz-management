<?php

namespace App\Http\Controllers\Api\Filling;

use App\Http\Controllers\Controller;
use App\Models\Filling;
use App\Services\FillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class FillingController extends Controller
{
    public function __construct(
        protected FillingService $service
    ) {}

    #[OA\Post(
        path: "/api/tanks/fillingStoreData",
        summary: "Créer un remplissage des bouteilles",
        description: "Permet d’enregistrer un remplissage les bouteilles à partir d’un tank",
        tags: ["Fillings"],

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
                        property: "operation_date",
                        type: "string",
                        format: "date",
                        example: "2023-10-10",
                        description: "Date de l'opération"
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
    public function store(Request $request): JsonResponse
    {
        try {

            $data = $request->validate([
                'tank_id' => 'required|exists:tanks,id',
                'operation_date' => 'required|date',
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
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Throwable $e) {

            Log::error('Filling store error', [
                'message' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            $decoded = json_decode($e->getMessage(), true);

            return response()->json([
                'message' => 'Impossible de faire le remplissage',
                'errors' => is_array($decoded) ? $decoded : [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('paginate', 10);
        $branchId = 1;
        $search = $request->query('q', '');

        $fillings = Filling::query()
            ->with([
                'tank:id,name',
                'branch:id,name',
                'addedBy:id,name',
                'items.product:id,name'
            ])

            ->when(
                $branchId,
                fn($q) =>
                $q->where('branch_id', $branchId)
            )

            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('note', 'like', "%{$search}%")
                        ->orWhereHas('tank', function ($q3) use ($search) {
                            $q3->where('name', 'like', "%{$search}%");
                        });
                });
            })

            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'status' => 200,
            'data' => $fillings
        ]);
    }
}
