<?php

namespace App\Http\Controllers\Api\Sipplier;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class SupplierController extends Controller
{

    #[OA\Get(
        path: "/api/v1/supplierGetAllData",
        summary: "Lister",
        tags: ["Suppliers"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function index(): JsonResponse
    {
        // $devise = Currency::where('status', 'created')->latest()->get();
        $devise = Currency::where('status', 'created')
                ->orderByRaw("currency_type = 'devise_principale' DESC")
                ->latest()
                ->get();
        $page = request('paginate', 10);
        $q = request('q', '');
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');

        // 🔒 Sécurité tri
        $allowedSortFields = ['id', 'name', 'phone', 'created_at'];

        if (!in_array($sort_field, $allowedSortFields)) {
            $sort_field = 'id';
        }

        if (!in_array(strtolower($sort_direction), ['asc', 'desc'])) {
            $sort_direction = 'desc';
        }

        $data = Supplier::query()
            ->leftJoin('users', 'suppliers.addedBy', '=', 'users.id')
            ->select(
                'suppliers.*',
                'users.name as addedBy'
            )
            ->where('suppliers.status', 'created')

            // 🔍 Recherche
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('suppliers.name', 'LIKE', "%{$q}%")
                        ->orWhere('suppliers.phone', 'LIKE', "%{$q}%")
                        ->orWhere('suppliers.address', 'LIKE', "%{$q}%")
                        ->orWhere('users.name', 'LIKE', "%{$q}%");
                });
            })

            ->orderBy("suppliers.$sort_field", $sort_direction)
            ->paginate($page);

        return response()->json([
            'status' => true,
            'message' => 'succès',
            'devise' => $devise,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/suppliersGetOptionsData",
        summary: "Lister",
        tags: ["Suppliers"],
        responses: [
            new OA\Response(response: 200, description: "Liste des branches")
        ]
    )]

    public function getSupplierOptions()
    {
        $data = Supplier::where('status', 'created')->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: '/api/v1/supplierStoreData',
        summary: 'Créer',
        tags: ['Suppliers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "address", type: "string", example: "Dar"),
                    new OA\Property(property: "phone", type: "string", nullable: true, example: "+243990000000")
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
            'name' => ['nullable', 'string', 'max:255', 'unique:suppliers,name'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:suppliers,phone']
        ];

        $messages = [
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro existe déjà.',
            'name.unique' => 'Ce nom existe déjà.',
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

        $authId = Auth::id();

        try {
            $supplier = Supplier::create([
                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone,
                'addedBy' => $authId
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Fournisseur créé avec succès',
                'data' => $supplier
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
        path: "/api/v1/supplierUpdate/{id}",
        summary: "Modifier",
        tags: ["Suppliers"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "address", type: "string", example: "Dar"),
                    new OA\Property(property: "phone", type: "string", nullable: true, example: "+243990000000")
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
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Fournisseur introuvable'
            ], 404);
        }

        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:suppliers,phone,' . $supplier->id],
        ];

        $messages = [
            'phone.required' => 'Le numéro est obligatoire.',
            'phone.unique' => 'Ce numéro existe déjà.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $supplier->update([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Fournisseur mis à jour',
            'data' => $supplier
        ]);
    }

    #[OA\Put(
        path: "/api/v1/supplierDelete/{id}",
        summary: "Supprimer",
        tags: ["Suppliers"],
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
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Fournisseur introuvable'
            ], 404);
        }

        $supplier->status = 'deleted';
        $supplier->save();

        return response()->json([
            'status' => true,
            'message' => 'Fournisseur supprimé'
        ]);
    }
}
