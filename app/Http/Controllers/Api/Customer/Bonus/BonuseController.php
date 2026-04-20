<?php

namespace App\Http\Controllers\Api\Customer\Bonus;

use App\Http\Controllers\Controller;
use App\Models\Bonuse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class BonuseController extends Controller
{
    #[OA\Post(
        path: "/api/v1/programRacompanceStore",
        summary: "Créer une règle de parrainage",
        tags: ["Bonus"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["product_id", "reward_amount"],
                properties: [
                    new OA\Property(property: "product_id", type: "integer", example: 1),
                    new OA\Property(property: "reward_amount", type: "number", format: "float", example: 0.5)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Règle créée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Règle créée avec succès"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 500, description: "Erreur serveur")
        ]
    )]
    public function storeData(Request $request)
    {
        try {

            $data = $request->validate([
                'product_id' => 'required|exists:products,id|unique:bonuses,product_id',
                'reward_amount' => 'required|numeric|min:0'
            ]);
            $data['addedBy'] = Auth::id();
            $rule = Bonuse::create($data);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Règle créée avec succès',
                'data' => $rule
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
        path: "/api/v1/programRacompanceUpdate/{id}",
        summary: "Modifier une règle de parrainage",
        tags: ["Bonus"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "product_id", type: "integer", example: 1),
                    new OA\Property(property: "reward_amount", type: "number", format: "float", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Règle mise à jour avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Règle mise à jour avec succès"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 500, description: "Erreur serveur")
        ]
    )]
    public function updateData(Request $request, $id)
    {
        try {

            $rule = Bonuse::findOrFail($id);

            $data = $request->validate([
                'product_id' => "nullable|exists:products,id|unique:bonuses,product_id,$id",
                'reward_amount' => 'nullable|numeric|min:0'
            ]);

            $rule->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Règle mise à jour avec succès',
                'data' => $rule
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            Log::error('ReferralRule update error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/programDisable/{id}",
        summary: "Desactiver une règle de parrainage",
        tags: ["Bonus"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer"),
                example: 1
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Règle mise à jour avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Règle mise à jour avec succès"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 500, description: "Erreur serveur")
        ]
    )]
    public function delete($id)
    {
        try {

            $rule = Bonuse::findOrFail($id);

            $rule->update([
                'status' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Règle desactivee avec succès',
                'data' => $rule
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            Log::error('ReferralRule update error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/bonusGetAllData",
        summary: "Lister",
        tags: ["Bonus"],
        responses: [
            new OA\Response(response: 200, description: "Liste des branches")
        ]
    )]
    public function getData()
    {
        $data = Bonuse::latest()->get();
        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => $data
        ], 201);
    }
}
