<?php

namespace App\Http\Controllers\Api\CashCategory;

use App\Http\Controllers\Controller;
use App\Models\CashCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class CashCategoryController extends Controller
{
    #[OA\Get(
        path: "/api/v1/cashCategoriesGetAllData",
        summary: "Lister",
        tags: ["CashCategories"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function index(): JsonResponse
    {
        $page = request('paginate', 10);
        $q = request('q', '');
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');

        // 🔒 Sécurité tri
        $allowedSortFields = ['id', 'designation', 'description', 'type', 'created_at'];

        if (!in_array($sort_field, $allowedSortFields)) {
            $sort_field = 'id';
        }

        if (!in_array(strtolower($sort_direction), ['asc', 'desc'])) {
            $sort_direction = 'desc';
        }

        $data = CashCategory::query()
            ->leftJoin('users', 'cash_categories.addedBy', '=', 'users.id')
            ->select(
                'cash_categories.*',
                'users.name as addedBy'
            )
            ->where('cash_categories.status', 'created')

            // 🔍 Recherche
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('cash_categories.designation', 'LIKE', "%{$q}%")
                        ->orWhere('cash_categories.description', 'LIKE', "%{$q}%")
                        ->orWhere('cash_categories.type', 'LIKE', "%{$q}%")
                        ->orWhere('users.name', 'LIKE', "%{$q}%");
                });
            })

            ->orderBy("cash_categories.$sort_field", $sort_direction)
            ->paginate($page);

        return response()->json([
            'status' => true,
            'message' => 'succès',
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/cashCategoriesGetOptionsData",
        summary: "Lister",
        tags: ["CashCategories"],
        responses: [
            new OA\Response(response: 200, description: "Liste des catégories de trésorerie")
        ]
    )]

    public function getCashCategoryOptions()
    {
        $data = CashCategory::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: '/api/v1/cashCategoriesStoreData',
        summary: 'Créer',
        tags: ['CashCategories'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['designation', 'type'],
                properties: [
                    new OA\Property(property: "designation", type: "string", example: "John Doe"),
                    new OA\Property(property: "type", type: "enum", example: "Revenue ou Depense"),
                    new OA\Property(property: "description", type: "string", example: "Dar")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Données créées avec succès'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation des données échouée'
            ),
            new OA\Response(
                response: 500,
                description: 'Erreur serveur'
            )
        ]
    )]

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'designation' => ['required', 'string', 'max:255', 'unique:cash_categories,designation'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:Revenue,Depense']
        ];

        $messages = [
            'designation.required' => 'Le nom de la catégorie est obligatoire.',
            'type.required' => 'Le type de la catégorie est obligatoire.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        $authId = auth()->id;

        try {
            $cashCategory = CashCategory::create([
                'designation' => $request->designation,
                'description' => $request->description,
                'type' => $request->type,
                'addedBy' => $authId
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Catégorie de trésorerie créée avec succès',
                'data' => $cashCategory
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Erreur serveur',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/cashCategoryUpdate/{id}",
        summary: "Modifier",
        tags: ["CashCategories"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "designation", type: "string", example: "John Doe"),
                    new OA\Property(property: "description", type: "string", example: "Dar"),
                    new OA\Property(property: "type", type: "enum", example: "Revenue ou Depense")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "mise à jour"),
            new OA\Response(response: 404, description: "Non trouvée")
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $cashCategory = CashCategory::find($id);

        if (!$cashCategory) {
            return response()->json([
                'status' => false,
                'message' => 'Catégorie de trésorerie introuvable'
            ], 404);
        }

        $rules = [
            'designation' => ['nullable', 'string', 'max:255', 'unique:cash_categories,designation,' . $cashCategory->id],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:Revenue,Depense']
        ];

        $messages = [
            'designation.unique' => 'Ce nom de catégorie existe déjà.',
            'type.required' => 'Le type de la catégorie est obligatoire.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $cashCategory->update([
            'designation' => $request->designation,
            'description' => $request->description,
            'type' => $request->type
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Catégorie de trésorerie mise à jour',
            'data' => $cashCategory
        ]);
    }

    #[OA\Put(
        path: "/api/v1/cashCategoryDelete/{id}",
        summary: "Supprimer",
        tags: ["CashCategories"],
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
        $cashCategory = CashCategory::find($id);

        if (!$cashCategory) {
            return response()->json([
                'status' => false,
                'message' => 'Catégorie de trésorerie introuvable'
            ], 404);
        }

        $cashCategory->status = 'deleted';
        $cashCategory->save();

        return response()->json([
            'status' => true,
            'message' => 'Catégorie de trésorerie supprimée'
        ]);
    }
}
