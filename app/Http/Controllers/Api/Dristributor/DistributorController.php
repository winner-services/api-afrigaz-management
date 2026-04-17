<?php

namespace App\Http\Controllers\Api\Dristributor;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\DebtDistributor;
use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class DistributorController extends Controller
{
    #[OA\Get(
        path: "/api/v1/distributorGetAllData",
        summary: "Liste des Distributeurs",
        tags: ["Distributeurs"],
        parameters: [
            new OA\Parameter(name: "paginate", in: "query", schema: new OA\Schema(type: "integer", example: 10)),
            new OA\Parameter(name: "q", in: "query", schema: new OA\Schema(type: "string", example: "Kinshasa"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Liste paginée")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('paginate', 10);
        $search = $request->query('q', '');

        $items = Distributor::with('addedBy:id,name', 'debts')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhere('zone', 'like', "%$search%")
                        ->orWhereHas('addedBy', function ($q3) use ($search) {
                            $q3->where('name', 'like', "%$search%");
                        });
                });
            })
            ->where('is_deleted', false)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $items
        ]);
    }
    #[OA\Get(
        path: "/api/v1/distributorsGetOptionData",
        summary: "Lister les options",
        tags: ["Distributeurs"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function getDistributorOptions(): JsonResponse
    {
        $items = Distributor::where('is_deleted', false)->where('status', 'actif')->orderByDesc('id')->get();

        return response()->json([
            'message' => 'succeess',
            'status' => 200,
            'data' => $items
        ]);
    }

    #[OA\Post(
        path: "/api/v1/distributorStoreData",
        summary: "Créer",
        tags: ["Distributeurs"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "caution_amount"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Entreprise X"),
                    new OA\Property(property: "address", type: "string", example: "Kinshasa"),
                    new OA\Property(property: "email", type: "string", example: "test@mail.com"),
                    new OA\Property(property: "phone", type: "string", example: "099999999"),
                    new OA\Property(property: "zone", type: "string", example: "Gombe"),
                    new OA\Property(property: "caution_amount", type: "number", example: 1000),
                    new OA\Property(property: "operation_date", type: "string", format: "date", example: "2024-01-01")
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
                'name' => 'required|string|max:255|unique:distributors,name',
                'address' => 'nullable|string',
                'email' => 'nullable|email|unique:distributors,email',
                'phone' => 'nullable|string|max:20|unique:distributors,phone',
                'zone' => 'nullable|string',
                'caution_amount' => 'nullable|numeric|min:0',
                'operation_date' => 'nullable|date',
                'loan_amount' => 'nullable|numeric|min:0',
                'account_id' => 'nullable|integer|exists:cash_accounts,id'
            ]);

            $result = DB::transaction(function () use ($data) {

                $data['addedBy'] = Auth::id();
                $data['reference'] = fake()->unique()->numerify('DB-#####');

                $loan_amount = $data['loan_amount'] ?? 0;
                $caution_amount = $data['caution_amount'] ?? 0;
                $account_id = $data['account_id'] ?? 1;


                $item = Distributor::create($data);


                $lastTransaction = CashTransaction::where('cash_account_id', $account_id)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                $solde = $lastTransaction ? $lastTransaction->solde : 0;


                if ($caution_amount > 0) {

                    CashTransaction::create([
                        'reason' => 'Paiement Caution initiale',
                        'type' => 'Revenue',
                        'amount' => $caution_amount,
                        'transaction_date' => now(),
                        'solde' => $solde + $caution_amount,
                        'reference' => $item->reference,
                        'reference_id' => $item->id,
                        'cash_account_id' => $account_id,
                        'cash_categorie_id' => 1,
                        'addedBy' => Auth::id(),
                    ]);

                    if ($caution_amount < $loan_amount) {
                        DebtDistributor::create([
                            'distributor_id' => $item->id,
                            'loan_amount' => $loan_amount,
                            'paid_amount' => $caution_amount,
                            'transaction_date' => now(),
                            'motif' => 'Crédit initial',
                            'status' => 'pending',
                            'user_id' => Auth::id(),
                        ]);
                    }
                }

                return $item;
            });

            return response()->json([
                'message' => 'Client créé avec succès',
                'status' => 201,
                'data' => $result
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Erreur serveur',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
    #[OA\Put(
        path: "/api/v1/distributorUpdate/{id}",
        summary: "Mettre à jour un distributeur",
        tags: ["Distributeurs"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Mis à jour"),
            new OA\Response(response: 404, description: "Introuvable")
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        try {

            $item = Distributor::findOrFail($id);

            $data = $request->validate([
                'name' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('distributors', 'name')->ignore($id)
                ],
                'address' => ['nullable', 'string'],
                'email' => [
                    'nullable',
                    'email',
                    Rule::unique('distributors', 'email')->ignore($id)
                ],
                'phone' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('distributors', 'phone')->ignore($id)
                ],
                'zone' => ['nullable', 'string'],
                'caution_amount' => 'nullable|numeric|min:0',
                'operation_date' => 'nullable|date'
            ]);

            $item->update($data);

            return response()->json([
                'message' => 'Client mis à jour',
                'status' => 200,
                'data' => $item
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Erreur',
                'errors' => [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }

    #[OA\Put(
        path: "/api/v1/distributorDelete/{id}",
        summary: "Supprimer un distributeur",
        tags: ["Distributeurs"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Supprimé"),
            new OA\Response(response: 404, description: "Introuvable")
        ]
    )]
    public function delete($id): JsonResponse
    {
        try {

            $item = Distributor::findOrFail($id);

            $item->is_deleted = true;
            $item->save();

            return response()->json([
                'message' => 'Distributeur supprimé',
                'status' => 200
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Erreur suppression',
                'errors' => [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }

    #[OA\Put(
        path: "/api/v1/distributorDisable/{id}",
        summary: "Désactiver un distributeur",
        tags: ["Distributeurs"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Désactivé"),
            new OA\Response(response: 404, description: "Introuvable")
        ]
    )]
    public function disableDistributor($id): JsonResponse
    {
        try {

            $item = Distributor::findOrFail($id);

            $item->status = 'inactif';
            $item->save();

            return response()->json([
                'message' => 'Distributeur désactivé',
                'status' => 200
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Erreur suppression',
                'errors' => [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }
    #[OA\Delete(
        path: "/api/v1/distributorActivate/{id}",
        summary: "Activer un distributeur",
        tags: ["Distributeurs"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Activé"),
            new OA\Response(response: 404, description: "Introuvable")
        ]
    )]
    public function activate($id): JsonResponse
    {
        try {

            $item = Distributor::findOrFail($id);

            $item->status = 'actif';
            $item->save();

            return response()->json([
                'message' => 'Distributeur activé',
                'status' => 200
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Erreur suppression',
                'errors' => [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }
}
