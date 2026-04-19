<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Branche;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class TransactionController extends Controller
{


    // public function getTransactionData()
    // {
    //     $about = About::first();
    //     if ($about && $about->logo) {
    //         $path = storage_path('app/public/' . $about->logo);

    //         if (file_exists($path)) {
    //             $mime = mime_content_type($path);
    //             $data = base64_encode(file_get_contents($path));
    //             $about->logo = "data:$mime;base64,$data";
    //         } else {
    //             $about->logo = asset('images/default-logo.png');
    //         }
    //     } else {
    //         $about->logo = asset('images/default-logo.png');
    //     }
    //     $caisse = CashAccount::first();
    //     if (is_null($caisse)) {
    //         return response()->json([
    //             'message' => "Compte introuvable",
    //             'success' => false,
    //             'status' => 404
    //         ]);
    //     }
    //     $idCompte = request("account_id", null);

    //     if ($idCompte === null || $idCompte === 'null') {
    //         $idCompte = $caisse->id;
    //     }

    //     $page = request("paginate", 10);
    //     $q = request("q", "");
    //     $sort_direction = request('sort_direction', 'desc');
    //     $sort_field = request('sort_field', 'id');
    //     $data = Cash::join('users', 'trasaction_tresoreries.addedBy', '=', 'users.id')
    //         ->join('tresoreries', 'trasaction_tresoreries.account_id', '=', 'tresoreries.id')
    //         ->select('trasaction_tresoreries.*', 'users.name as addedBy', 'tresoreries.designation as account_name')
    //         ->where('trasaction_tresoreries.status', true)
    //         ->where('account_id', $idCompte)
    //         ->searh(trim($q))
    //         ->orderBy($sort_field, $sort_direction)
    //         ->paginate($page);
    //     $result = [
    //         'message' => "OK",
    //         'success' => true,
    //         'data' => $data,
    //         'company_info' => $about,
    //         'status' => 200,
    //     ];
    //     return response()->json($result);
    // }

    #[OA\Post(
        path: '/api/v1/transactionStoreData',
        summary: 'Créer une transaction de caisse',
        description: 'Permet de créer une transaction (Revenue ou Dépense) et met à jour automatiquement le solde.',
        tags: ['Cash Transactions'],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'amount', 'transaction_date', 'account_id'],
                properties: [
                    new OA\Property(property: "type", type: "string", example: "Revenue", description: "Type: Revenue ou Depense"),
                    new OA\Property(property: "amount", type: "number", format: "float", example: 100.00),
                    new OA\Property(property: "transaction_date", type: "string", format: "date", example: "2026-04-19"),
                    new OA\Property(property: "account_id", type: "integer", example: 1),
                    new OA\Property(property: "cash_categorie_id", type: "integer", example: 2),
                    new OA\Property(property: "reason", type: "string", example: "Paiement client"),
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: 'Transaction créée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Transaction ajoutée avec succès"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),

            new OA\Response(response: 400, description: 'Solde insuffisant'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 500, description: 'Erreur serveur'),
        ]
    )]
    public function store(Request $request)
    {
        $rules = [
            'reason' => ['nullable', 'string'],
            'type' => ['required', 'in:Revenue,Depense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:cash_accounts,id'],
            'cash_categorie_id' => ['nullable', 'exists:cash_categories,id']
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();

            $lastTransaction = CashTransaction::where('cash_account_id', $request->account_id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $currentSolde = $lastTransaction ? $lastTransaction->solde : 0;

            // 🔥 Vérification solde
            if ($request->type === 'Depense' && $currentSolde < $request->amount) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Solde insuffisant.',
                    'success' => false
                ], 400);
            }

            $newSolde = $request->type === 'Revenue'
                ? $currentSolde + $request->amount
                : $currentSolde - $request->amount;

            $transaction = CashTransaction::create([
                'transaction_date' => $request->transaction_date,
                'cash_account_id' => $request->account_id,
                'amount' => $request->amount,
                'transaction_type' => $request->type,
                'solde' => $newSolde,
                'cash_categorie_id' => $request->cash_categorie_id,
                'reference' => 'TRANS-' . strtoupper(uniqid()),
                'addedBy' => $user->id,
                'reason' => $request->reason ?? '-',
            ]);

            $transaction->load('account');

            DB::commit();

            return response()->json([
                'message' => "Transaction ajoutée avec succès",
                'success' => true,
                'data' => $transaction
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la création.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/v1/transactionUpdate/{id}',
        summary: 'Modifier une transaction',
        description: 'Met à jour une transaction existante et recalcule le solde.',
        tags: ['Cash Transactions'],

        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID de la transaction",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'amount', 'transaction_date', 'account_id'],
                properties: [
                    new OA\Property(property: "type", type: "string", example: "Depense"),
                    new OA\Property(property: "amount", type: "number", format: "float", example: 50.00),
                    new OA\Property(property: "transaction_date", type: "string", format: "date", example: "2026-04-19"),
                    new OA\Property(property: "account_id", type: "integer", example: 1),
                    new OA\Property(property: "cash_categorie_id", type: "integer", example: 2),
                    new OA\Property(property: "reason", type: "string", example: "Achat carburant"),
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: 'Transaction mise à jour',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Transaction mise à jour"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),

            new OA\Response(response: 400, description: 'Solde insuffisant'),
            new OA\Response(response: 404, description: 'Transaction non trouvée'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 500, description: 'Erreur serveur'),
        ]
    )]
    public function updateData(Request $request, $id)
    {
        $rules = [
            'reason' => ['nullable', 'string'],
            'type' => ['required', 'in:Revenue,Depense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:cash_accounts,id'],
            'cash_categorie_id' => ['nullable', 'exists:cash_categories,id']
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $transaction = CashTransaction::lockForUpdate()->findOrFail($id);

            $lastTransaction = CashTransaction::where('cash_account_id', $request->account_id)
                ->where('id', '<', $transaction->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $currentSolde = $lastTransaction ? $lastTransaction->solde : 0;

            // 🔥 Vérifier solde si dépense
            if ($request->type === 'Depense' && $currentSolde < $request->amount) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Solde insuffisant.',
                    'success' => false
                ], 400);
            }

            $newSolde = $request->type === 'Revenue'
                ? $currentSolde + $request->amount
                : $currentSolde - $request->amount;

            $transaction->update([
                'transaction_date' => $request->transaction_date,
                'cash_account_id' => $request->account_id,
                'amount' => $request->amount,
                'transaction_type' => $request->type,
                'solde' => $newSolde,
                'cash_categorie_id' => $request->cash_categorie_id,
                'reason' => $request->reason ?? '-',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transaction mise à jour',
                'success' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    #[OA\Get(
        path: "/api/v1/transactionsByBranchGetData",
        summary: "Lister",
        tags: ["Cash Transactions"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function indexByBranche(Request $request)
    {
        try {
            $branche = Branche::where('user_id', Auth::id())->first();

            if (!$branche) {
                return response()->json([
                    'message' => 'Branche non trouvée'
                ], 404);
            }

            $brancheId = request('branche_id', $branche->id);

            $perPage = $request->query('per_page', 10);
            $search = $request->query('q', '');
            $sortField = $request->query('sort_field', 'id');
            $sortDirection = $request->query('sort_direction', 'desc');

            // 🔒 Sécurité tri
            $allowedSortFields = [
                'id',
                'amount',
                'transaction_date',
                'type',
                'transaction_date'
            ];

            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'id';
            }

            $query = CashTransaction::query()
                ->with(['account:id,designation,branche_id'])
                ->whereHas('account', function ($q) use ($brancheId) {
                    if ($brancheId) {
                        $q->where('branche_id', $brancheId);
                    }
                });

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'LIKE', "%$search%")
                        ->orWhere('reference', 'LIKE', "%$search%")
                        ->orWhere('type', 'LIKE', "%$search%")
                        ->orWhere('amount', 'LIKE', "%$search%");
                });
            }

            // 📊 Tri + pagination
            $transactions = $query
                ->orderBy($sortField, $sortDirection)
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
