<?php

namespace App\Http\Controllers\Api\Products;

use App\Http\Controllers\Controller;
use App\Models\CashCategory;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: "/api/v1/categoryGetOptionsData",
        summary: "Lister",
        tags: ["Products"],
        responses: [
            new OA\Response(response: 200, description: "Liste des branches")
        ]
    )]

    public function getCategoryOptions()
    {
        $data = ProductCategory::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: '/api/v1/categoryStoreData',
        summary: 'Créer',
        tags: ['Products'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['designation'],
                properties: [
                    new OA\Property(property: "designation", type: "string", example: "John Doe"),
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
            'designation' => ['required', 'unique:product_categories,designation']
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
        $exists = ProductCategory::where('designation', $request->designation)
            ->first();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'existe déjà.',
            ], 409); // 409 = Conflict
        }

        try {
            $categorie = ProductCategory::create([
                'designation' => $request->designation
            ]);
            return response()->json([
                'status'  => true,
                'message' => 'ajouté avec succès.',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue lors de la création de l\'utilisateur.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $category = ProductCategory::findOrFail($id);
            if (!$category) {
                return response()->json([
                    'status'  => false,
                    'message' => ' introuvable'
                ], 404);
            }

            $rule = [
                'designation' => ['required', 'string', 'max:20', 'unique:product_categories,designation,' . $category->id],
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
            $category = ProductCategory::findOrFail($id);
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
                'message' => 'modifié avec succès.',
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
