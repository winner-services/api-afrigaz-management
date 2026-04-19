<?php

namespace App\Http\Controllers\Api\Currency;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviseRequest;
use App\Http\Requests\UpdateDeviseRequest;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CurrencyController extends Controller
{
    #[OA\Get(
        path: "/api/v1/currencyGetAllData",
        summary: "Lister les devises",
        tags: ["Currency"],
        responses: [
            new OA\Response(response: 200, description: "Liste des devises")
        ]
    )]
    public function index()
    {
        $devises = Currency::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $devises
        ]);
    }

    #[OA\Post(
        path: '/api/v1/currencyStoreData',
        summary: 'Créer',
        tags: ['Currency'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['designation', 'currency_type', 'conversion_amount', 'symbol'],
                properties: [
                    new OA\Property(property: "designation", type: "string", example: "John Doe"),
                    new OA\Property(property: "currency_type", type: "enum", example: "devise_principale"),
                    new OA\Property(property: "conversion_amount", type: "number", example: 1.0),
                    new OA\Property(property: "symbol", type: "string", example: "$")
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

    public function createCurrency(StoreDeviseRequest $request)
    {
        try {
            DB::beginTransaction();
            $userId = Auth::user()->id;

            // ✅ Vérifier devise principale
            if ($request->currency_type === 'devise_principale') {
                $exists = Currency::where('currency_type', 'devise_principale')->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Il existe déjà une devise principale.'
                    ], 400);
                }
            }

            // ✅ Création
            $currency = Currency::create([
                'designation'        => $request->designation,
                'currency_type'      => $request->currency_type,
                'conversion_amount'  => $request->conversion_amount,
                'symbol'             => $request->symbol,
                'addedBy' => $userId
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Devise créée avec succès.',
                'data'    => $currency
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error'   => $e->getMessage() // 🔒 en prod, enlève ça
            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/currencyUpdate/{id}",
        summary: "Modifier une devise",
        tags: ["Currency"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "designation", type: "string"),
                    new OA\Property(property: "currency_type", type: "enum"),
                    new OA\Property(property: "conversion_amount", type: "number"),
                    new OA\Property(property: "symbol", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Devise mise à jour"),
            new OA\Response(response: 404, description: "Devise non trouvée")
        ]
    )]
    public function update(UpdateDeviseRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $devise = Currency::find($id);

            if (!$devise) {
                return response()->json([
                    'success' => false,
                    'message' => 'Devise non trouvée.'
                ], 404);
            }

            if ($request->currency_type === 'devise_principale') {
                $exists = Currency::where('currency_type', 'devise_principale')
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Une devise principale existe déjà.'
                    ], 400);
                }
            }

            $devise->update($request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Devise mise à jour.',
                'data' => $devise
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/currencyDelete/{id}",
        summary: "Supprimer une devise",
        tags: ["Currency"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Supprimée"),
            new OA\Response(response: 404, description: "Non trouvée")
        ]
    )]
    public function destroy($id)
    {
        $devise = Currency::find($id);

        if (!$devise) {
            return response()->json([
                'success' => false,
                'message' => 'Devise non trouvée.'
            ], 404);
        }

        if ($devise->currency_type === 'devise_principale') {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Impossible de supprimer la devise principale.'
            ], 422);
        }

        $devise->status = 'deleted';
        $devise->save();

        return response()->json([
            'success' => true,
            'message' => 'Devise supprimée.'
        ]);
    }
}
