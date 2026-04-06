<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomerController extends Controller
{
    #[OA\Get(
        path: "/api/v1/customerGetAllData",
        summary: "Lister",
        tags: ["Customers"],
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
        $allowedSortFields = ['id', 'name', 'phone', 'address', 'created_at'];

        if (!in_array($sort_field, $allowedSortFields)) {
            $sort_field = 'id';
        }

        if (!in_array(strtolower($sort_direction), ['asc', 'desc'])) {
            $sort_direction = 'desc';
        }

        $data = Customer::query()
            ->leftJoin('users', 'customers.addedBy', '=', 'users.id')
            ->select(
                'customers.*',
                'users.name as addedBy'
            )
            ->where('customers.status', 'created')

            // 🔍 Recherche
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('customers.name', 'LIKE', "%{$q}%")
                        ->orWhere('customers.phone', 'LIKE', "%{$q}%")
                        ->orWhere('customers.address', 'LIKE', "%{$q}%")
                        ->orWhere('users.name', 'LIKE', "%{$q}%");
                });
            })

            ->orderBy("customers.$sort_field", $sort_direction)
            ->paginate($page);

        return response()->json([
            'status' => true,
            'message' => 'succès',
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/customersGetOptionsData",
        summary: "Lister",
        tags: ["Customers"],
        responses: [
            new OA\Response(response: 200, description: "Liste des clients")
        ]
    )]

    public function getCustomerOptions()
    {
        $data = Customer::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: '/api/v1/customerStoreData',
        summary: 'Créer',
        tags: ['Customers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'category'],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "category", type: "enum", example: "consommateur ou distributeur"),
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
            'name' => ['nullable', 'string', 'max:255', 'unique:customers,name'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:customers,phone'],
            'category' => ['required', 'in:distributeur,consommateur']
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
            $customer = Customer::create([
                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone,
                'category' => $request->category,
                'addedBy' => $authId
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Client créé avec succès',
                'data' => $customer
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
        path: "/api/v1/customerUpdate/{id}",
        summary: "Modifier",
        tags: ["Customers"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "address", type: "string", example: "Dar"),
                    new OA\Property(property: "phone", type: "string", nullable: true, example: "+243990000000"),
                    new OA\Property(property: "category", type: "enum", example: "consommateur ou distributeur")
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
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Client introuvable'
            ], 404);
        }

        $rules = [
            'name' => ['nullable', 'string', 'max:255', 'unique:customers,name,' . $customer->id],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:customers,phone,' . $customer->id],
            'category' => ['nullable', 'in:distributeur,consommateur']
        ];

        $messages = [
            'phone.required' => 'Le numéro est obligatoire.',
            'phone.unique' => 'Ce numéro existe déjà.',
            'name.unique' => 'Ce nom existe déjà.',
            'category.required' => 'La catégorie est obligatoire.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer->update([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'category' => $request->category
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Client mis à jour',
            'data' => $customer
        ]);
    }

    #[OA\Put(
        path: "/api/v1/customerDelete/{id}",
        summary: "Supprimer",
        tags: ["Customers"],
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
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Client introuvable'
            ], 404);
        }

        $customer->status = 'deleted';
        $customer->save();

        return response()->json([
            'status' => true,
            'message' => 'Client supprimé'
        ]);
    }
}
