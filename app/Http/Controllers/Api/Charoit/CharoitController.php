<?php

namespace App\Http\Controllers\Api\Charoit;

use App\Http\Controllers\Controller;
use App\Models\Charoit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use OpenApi\Attributes as OA;

class CharoitController extends Controller
{
    #[OA\Get(
        path: "/api/v1/charoitsGetAllData",
        summary: "Liste des véhicules",
        tags: ["Charoits"],

        parameters: [
            new OA\Parameter(
                name: "paginate",
                in: "query",
                schema: new OA\Schema(type: "integer", example: 10)
            ),
            new OA\Parameter(
                name: "q",
                in: "query",
                description: "Recherche",
                schema: new OA\Schema(type: "string", example: "Toyota")
            )
        ],

        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "current_page", type: "integer"),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "Camion"),
                                new OA\Property(property: "brand", type: "string", example: "Toyota"),
                                new OA\Property(property: "plate_number", type: "string", example: "123ABC"),
                                new OA\Property(property: "color", type: "string", example: "Blanc"),
                                new OA\Property(property: "reference", type: "string", example: "REF001"),
                                new OA\Property(property: "status", type: "string", example: "created"),
                            ],
                            type: "object"
                        )),
                        new OA\Property(property: "total", type: "integer", example: 100)
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('paginate', 10);
        $search = $request->query('q', '');

        $items = Charoit::with('addedBy:id,name')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('charoits.name', 'like', "%$search%")
                        ->orWhere('charoits.brand', 'like', "%$search%")
                        ->orWhere('charoits.plate_number', 'like', "%$search%")
                        ->orWhere('charoits.reference', 'like', "%$search%")
                        ->orWhereHas('addedBy', function ($q3) use ($search) {
                            $q3->where('users.name', 'like', "%$search%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'status' => 200,
            'data' => $items
        ]);
    }

    #[OA\Get(
        path: "/api/v1/charoitsGetOptionData",
        summary: "Lister les options",
        tags: ["Charoits"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function charoitGetOptionData()
    {
        $data = Charoit::where('status', '!=', 'deleted')
            ->orderByDesc('id')
            ->get();
        return response()->json([
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: "/api/v1/charoitsStoreData",
        summary: "Créer un véhicule",
        tags: ["Charoits"],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Camion"),
                    new OA\Property(property: "brand", type: "string", example: "Toyota"),
                    new OA\Property(property: "plate_number", type: "string", example: "123ABC"),
                    new OA\Property(property: "color", type: "string", example: "Blanc")
                ]
            )
        ),

        responses: [
            new OA\Response(response: 201, description: "Créé"),
            new OA\Response(response: 422, description: "Erreur validation")
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        try {

            $data = $request->validate([
                'name' => 'nullable|string|max:255|unique:charoits,name',
                'brand' => 'nullable|string|max:255',
                'plate_number' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:255'
            ]);

            $data['addedBy'] = Auth::id();
            $data['reference'] = fake()->unique()->numerify('CHAR-#####');

            $item = Charoit::create($data);

            return response()->json([
                'message' => 'Enregistrement créé avec succès',
                'status' => 201,
                'data' => $item
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Throwable $e) {

            Log::error('Store error', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Erreur lors de la création',
                'errors' => [$e->getMessage()],
                'status' => 500
            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/charoitsUpdate/{id}",
        summary: "Mettre à jour un véhicule",
        tags: ["Charoits"],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],

        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "brand", type: "string"),
                    new OA\Property(property: "plate_number", type: "string"),
                    new OA\Property(property: "color", type: "string")
                ]
            )
        ),

        responses: [
            new OA\Response(response: 200, description: "Mis à jour"),
            new OA\Response(response: 404, description: "Introuvable"),
            new OA\Response(response: 422, description: "Erreur")
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        try {

            $item = Charoit::findOrFail($id);

            $data = $request->validate([
                'name' => "nullable|name|unique:your_table,name,$id",
                'brand' => "nullable|string|max:255",
                'plate_number' => "nullable|plate_number|unique:your_table,plate_number,$id",
                'color' => "nullable|string|max:255",
            ]);

            $item->update($data);

            return response()->json([
                'message' => 'Mise à jour réussie',
                'status' => 200,
                'data' => $item
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Élément introuvable',
                'status' => 404
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Throwable $e) {

            Log::error('Update error', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'errors' => [$e->getMessage()],
                'status' => 500
            ], 500);
        }
    }
    #[OA\Delete(
        path: "/api/v1/charoitsDelete/{id}",
        summary: "Supprimer un véhicule",
        tags: ["Charoits"],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],

        responses: [
            new OA\Response(response: 200, description: "Supprimé"),
            new OA\Response(response: 404, description: "Introuvable"),
            new OA\Response(response: 422, description: "Erreur")
        ]
    )]
    public function destroy($id): JsonResponse
    {
        try {

            $item = Charoit::findOrFail($id);

            $item->status = 'deleted';
            $item->save();

            return response()->json([
                'message' => 'Supprimé avec succès',
                'status' => 200
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Élément introuvable',
                'status' => 404
            ], 404);
        } catch (\Throwable $e) {

            Log::error('Delete error', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'errors' => [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }
}
