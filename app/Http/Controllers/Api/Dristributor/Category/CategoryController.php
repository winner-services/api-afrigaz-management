<?php

namespace App\Http\Controllers\Api\Dristributor\Category;

use App\Http\Controllers\Controller;
use App\Models\CategoryDistributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: "/api/v1/categoryDistribGetOptionsData",
        summary: "Lister",
        tags: ["Category Distributors"],
        responses: [
            new OA\Response(response: 200, description: "Liste dses categories de distributeurs")
        ]
    )]

    public function categoryDistribGetOptionsData()
    {
        $data = CategoryDistributor::where('status', '!=', 'deleted')->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: '/api/v1/categoryDistribStoreData',
        summary: 'Créer',
        tags: ['Category Distributors'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['designation', 'description'],
                properties: [
                    new OA\Property(property: "designation", type: "string", example: "John Doe"),
                    new OA\Property(property: "description", type: "string", example: "Description de la catégorie")
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

    public function storeCategory(Request $request)
    {
        $rule = [
            'designation' => ['required', 'unique:category_distributors,designation'],
            'description' => ['nullable', 'string', 'max:255']

        ];
        $message = [
            'designation.unique'   => 'Cette categorie existe déjà.',
        ];

        $validator = Validator::make($request->all(), $rule, $message);
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }
        $exists = CategoryDistributor::where('designation', $request->designation)
            ->first();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'existe déjà.',
            ], 409); // 409 = Conflict
        }

        try {
            $categorie = CategoryDistributor::create([
                'designation' => $request->designation,
                'description' => $request->description
            ]);
            return response()->json([
                'status'  => true,
                'message' => 'ajouté avec succès.',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue lors de l\'ajout.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $category = CategoryDistributor::findOrFail($id);
            if (!$category) {
                return response()->json([
                    'status'  => false,
                    'message' => ' introuvable'
                ], 404);
            }

            $rule = [
                'designation' => ['required', 'string', 'max:20', 'unique:category_distributors,designation,' . $category->id],
                'description' => ['nullable', 'string', 'max:255']
            ];
            $message = [
                'designation.unique'   => 'Cette categorie existe déjà.',
            ];

            $validator = Validator::make($request->all(), $rule, $message);
            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Les données envoyées ne sont pas valides.',
                    'errors'  => $validator->errors()
                ], 422);
            }
            $category->designation = $request->designation;
            $category->description = $request->description;
            $category->save();
            return response()->json([
                'status'  => 200,
                'message' => 'modifié avec succès.',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = CategoryDistributor::findOrFail($id);
            if (!$category) {
                return response()->json([
                    'status'  => false,
                    'message' => ' introuvable'
                ], 404);
            }
            $category->status = 'deleted';
            $category->save();
            return response()->json([
                'status'  => 200,
                'message' => 'supprimé avec succès.',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue.',
                'error'   => config('app.debug') ? $th->getMessage() : null
            ], 500);
        }
    }
}
