<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class AccountController extends Controller
{
    #[OA\Get(
        path: "/api/v1/AccountGetAllData",
        summary: "Lister",
        tags: ["Accounts"],
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
        $allowedSortFields = ['id', 'designation', 'nature', 'reference', 'created_at'];

        if (!in_array($sort_field, $allowedSortFields)) {
            $sort_field = 'id';
        }

        if (!in_array(strtolower($sort_direction), ['asc', 'desc'])) {
            $sort_direction = 'desc';
        }

        $data = CashAccount::query()
            ->leftJoin('users', 'cash_accounts.addedBy', '=', 'users.id')
            ->leftJoin('branches', 'cash_accounts.branche_id', '=', 'branches.id')
            ->select(
                'cash_accounts.*',
                'users.name as addedBy',
                'branches.name as brancheName'
            )
            ->where('cash_accounts.status', 'created')

            // 🔍 Recherche
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('cash_accounts.designation', 'LIKE', "%{$q}%")
                        ->orWhere('cash_accounts.nature', 'LIKE', "%{$q}%")
                        ->orWhere('cash_accounts.reference', 'LIKE', "%{$q}%")
                        ->orWhere('branches.name', 'LIKE', "%{$q}%");
                });
            })

            ->orderBy("cash_accounts.$sort_field", $sort_direction)
            ->paginate($page);

        return response()->json([
            'status' => true,
            'message' => 'succès',
            'data' => $data
        ]);
    }
    #[OA\Get(
        path: "/api/v1/accountGetOptionsData",
        summary: "Lister",
        tags: ["Accounts"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function getAccountOptions()
    {
        $data = CashAccount::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: '/api/v1/accountStoreData',
        summary: 'Créer',
        tags: ['Accounts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['designation', 'branche_id', 'nature'],
                properties: [
                    new OA\Property(property: "designation", type: "string", example: "John Doe"),
                    new OA\Property(property: "branche_id", type: "integer", example: 1),
                    new OA\Property(property: "nature", type: "string", example: "Nature du compte")
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
            'designation' => ['nullable', 'string', 'max:255', 'unique:cash_accounts,designation'],
            'nature' => ['nullable', 'string', 'max:255'],
            'branche_id' => ['required', 'integer', 'exists:branches,id'],
        ];

        $messages = [
            'branche_id.required' => 'La branche est obligatoire.',
            'branche_id.exists' => 'La branche sélectionnée n\'existe pas.',
            'designation.unique' => 'Cette désignation existe déjà.',
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
            $account = CashAccount::create([
                'designation' => $request->designation,
                'nature' => $request->nature,
                'branche_id' => $request->branche_id,
                'reference' => fake()->unique()->numerify('AC-#####'),
                'addedBy' => $authId
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Compte créé avec succès',
                'data' => $account
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
        path: "/api/v1/accountUpdate/{id}",
        summary: "Modifier",
        tags: ["Accounts"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "designation", type: "string", example: "John Doe"),
                    new OA\Property(property: "branche_id", type: "integer", example: 1),
                    new OA\Property(property: "nature", type: "string", example: "Nature du compte")
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
        $account = CashAccount::find($id);

        if (!$account) {
            return response()->json([
                'status' => false,
                'message' => 'Compte introuvable'
            ], 404);
        }

        $rules = [
            'designation' => ['nullable', 'string', 'max:255', 'unique:cash_accounts,designation,' . $account->id],
            'nature' => ['nullable', 'string', 'max:255'],
            'branche_id' => ['required', 'integer', 'exists:branches,id'],
        ];

        $messages = [
            'branche_id.required' => 'La branche est obligatoire.',
            'branche_id.exists' => 'La branche sélectionnée n\'existe pas.',
            'designation.unique' => 'Cette désignation existe déjà.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $account->update([
            'designation' => $request->designation,
            'nature' => $request->nature,
            'branche_id' => $request->branche_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Compte mis à jour',
            'data' => $account
        ]);
    }

    #[OA\Put(
        path: "/api/v1/accountDelete/{id}",
        summary: "Supprimer",
        tags: ["Accounts"],
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
        $account = CashAccount::find($id);

        if (!$account) {
            return response()->json([
                'status' => false,
                'message' => 'Compte introuvable'
            ], 404);
        }

        $account->status = 'deleted';
        $account->save();

        return response()->json([
            'status' => true,
            'message' => 'Compte supprimé'
        ]);
    }
}
