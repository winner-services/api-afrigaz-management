<?php

namespace App\Http\Controllers\Api\Bulk;

use App\Http\Controllers\Controller;
use App\Models\Bulk_Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class BulkPurchaseController extends Controller
{
    #[OA\Post(
        path: '/api/v1/bulkPurchaseStoreData',
        summary: 'Créer',
        tags: ['Bulk'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['supplier_id'],
                properties: [
                    new OA\Property(property: "supplier_id", type: "integer", example: 1),
                    new OA\Property(property: "invoice_number", type: "integer", example: 1),
                    new OA\Property(property: "unit_price_per_kg", type: "integer", example: 1),
                    new OA\Property(property: "quantity_kg", type: "integer", example: 1),
                    new OA\Property(property: "purchase_date", type: "date", example: "2026-04-03")
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
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'quantity_kg' => ['required', 'min:1'],
            'unit_price_per_kg' => ['nullable', 'numeric'],
            'purchase_date' => ['nullable', 'date'],
        ];

        $messages = [
            'quantity_kg.required' => 'La quantité est obligatoire.',
            'quantity_kg.min' => 'La quantité doit être supérieure à 0.',
            'supplier_id.exists' => 'Fournisseur invalide.'
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
        $userId = Auth::id();

        try {
            // 💰 Calcul automatique
            $total_cost = $request->quantity_kg * ($request->unit_price_per_kg ?? 0);

            $purchase = Bulk_Purchase::create([
                'supplier_id' => $request->supplier_id,
                'invoice_number' => $request->invoice_number,
                'quantity_kg' => $request->quantity_kg,
                'unit_price_per_kg' => $request->unit_price_per_kg,
                'total_cost' => $total_cost,
                'status' => $request->status ?? 'created',
                'addedBy' => $userId,
                'purchase_date' => $request->purchase_date
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Achat créé avec succès',
                'data' => $purchase
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

    #[OA\Get(
        path: "/api/v1/bulkPurchaseGetAllData",
        summary: "Lister",
        tags: ["Bulk"],
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
        $allowedSortFields = [
            'id',
            'quantity_kg',
            'unit_price_per_kg',
            'total_cost',
            'purchase_date',
            'lost_Quantity_kg',
            'created_at'
        ];

        if (!in_array($sort_field, $allowedSortFields)) {
            $sort_field = 'id';
        }

        if (!in_array(strtolower($sort_direction), ['asc', 'desc'])) {
            $sort_direction = 'desc';
        }

        $data = Bulk_Purchase::join('suppliers', 'bulk__purchases.supplier_id', '=', 'suppliers.id')
            ->join('users', 'bulk__purchases.addedBy', '=', 'users.id')
            ->select(
                'bulk__purchases.*',
                'suppliers.name as supplier',
                'users.name as addedBy'
            )

            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('bulk__purchases.invoice_number', 'LIKE', "%{$q}%")
                        ->orWhere('suppliers.name', 'LIKE', "%{$q}%")
                        ->orWhere('users.name', 'LIKE', "%{$q}%");
                });
            })

            ->orderBy("bulk__purchases.$sort_field", $sort_direction)
            ->paginate($page);

        return response()->json([
            'status' => true,
            'message' => 'succès',
            'data' => $data
        ]);
    }

    #[OA\Put(
        path: "/api/v1/bulkPurchaseUpdate/{id}",
        summary: "Modifier",
        tags: ["Bulk"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "supplier_id", type: "integer", example: 1),
                    new OA\Property(property: "invoice_number", type: "integer", example: 1),
                    new OA\Property(property: "unit_price_per_kg", type: "integer", example: 1),
                    new OA\Property(property: "quantity_kg", type: "integer", example: 1),
                    new OA\Property(property: "purchase_date", type: "date", example: "2026-04-03")
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
        $purchase = Bulk_Purchase::find($id);

        if (!$purchase) {
            return response()->json([
                'status' => false,
                'message' => 'Achat introuvable'
            ], 404);
        }

        $rules = [
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'quantity_kg' => ['required', 'integer', 'min:1'],
            'unit_price_per_kg' => ['nullable', 'numeric'],
            'purchase_date' => ['nullable', 'date'],
        ];

        $messages = [
            'quantity_kg.required' => 'La quantité est obligatoire.',
            'supplier_id.exists' => 'Fournisseur invalide.'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // 💰 recalcul
        $total_cost = $request->quantity_kg * ($request->unit_price_per_kg ?? 0);

        $purchase->update([
            'supplier_id' => $request->supplier_id,
            'invoice_number' => $request->invoice_number,
            'quantity_kg' => $request->quantity_kg,
            'unit_price_per_kg' => $request->unit_price_per_kg,
            'total_cost' => $total_cost,
            'purchase_date' => $request->purchase_date
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Achat mis à jour',
            'data' => $purchase
        ]);
    }

    #[OA\Put(
        path: "/api/v1/bulkPurchaseDelete/{id}",
        summary: "Supprimer",
        tags: ["Bulk"],
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
        $purchase = Bulk_Purchase::find($id);

        if (!$purchase) {
            return response()->json([
                'status' => false,
                'message' => 'Achat introuvable'
            ], 404);
        }

        $purchase->status = 'deleted';
        $purchase->save();

        return response()->json([
            'status' => true,
            'message' => 'Achat supprimé'
        ]);
    }

    #[OA\Put(
        path: "/api/v1/lostQuantityStore/{id}",
        summary: "Modifier",
        tags: ["Bulk"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "lost_Quantity_kg", type: "integer", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "success"),
            new OA\Response(response: 404, description: "Non trouvée")
        ]
    )]
    public function lostQuantityStore(Request $request, $id): JsonResponse
    {
        $purchase = Bulk_Purchase::find($id);

        if (!$purchase) {
            return response()->json([
                'status' => false,
                'message' => 'Achat introuvable'
            ], 404);
        }

        $rules = [
            'lost_Quantity_kg' => ['required', 'integer', 'min:1']
        ];

        $messages = [
            'lost_Quantity_kg.required' => 'La quantité est obligatoire.'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $purchase->update([
            'lost_Quantity_kg' => $request->lost_Quantity_kg
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $purchase
        ]);
    }
}
