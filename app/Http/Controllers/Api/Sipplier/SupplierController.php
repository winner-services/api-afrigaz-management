<?php

namespace App\Http\Controllers\Api\Sipplier;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $devise = Currency::where('status', 'created')
            ->orderByRaw("currency_type = 'devise_principale' DESC")
            ->latest()
            ->get();
        $page = request('paginate', 10);
        $q = request('q', '');
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');

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
        summary: 'Créer un fournisseur',
        tags: ['Suppliers'],

        requestBody: new OA\RequestBody(
            required: true,

            content: new OA\JsonContent(

                required: ['name', 'phone'],

                properties: [

                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'AfriGaz Supplier'
                    ),

                    new OA\Property(
                        property: 'phone',
                        type: 'string',
                        example: '+243990000000'
                    ),

                    new OA\Property(
                        property: 'address',
                        type: 'string',
                        nullable: true,
                        example: 'Kinshasa Gombe'
                    ),

                    new OA\Property(
                        property: 'company_name',
                        type: 'string',
                        nullable: true,
                        example: 'AfriGaz SARL'
                    ),

                    new OA\Property(
                        property: 'email',
                        type: 'string',
                        format: 'email',
                        nullable: true,
                        example: 'contact@afrigaz.com'
                    ),

                    new OA\Property(
                        property: 'country',
                        type: 'string',
                        nullable: true,
                        example: 'RDC'
                    ),

                    new OA\Property(
                        property: 'city',
                        type: 'string',
                        nullable: true,
                        example: 'Kinshasa'
                    ),

                    new OA\Property(
                        property: 'tax_number',
                        type: 'string',
                        nullable: true,
                        example: 'A123456789'
                    ),

                    new OA\Property(
                        property: 'rccm',
                        type: 'string',
                        nullable: true,
                        example: 'CD/KIN/RCCM/24-B-1234'
                    ),

                    new OA\Property(
                        property: 'idnat',
                        type: 'string',
                        nullable: true,
                        example: '01-F4300-N12345X'
                    ),

                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        nullable: true,
                        example: 'created',
                        enum: ['created', 'active', 'inactive', 'blocked']
                    ),
                ]
            )
        ),

        responses: [

            new OA\Response(
                response: 201,
                description: 'Fournisseur créé avec succès'
            ),

            new OA\Response(
                response: 422,
                description: 'Erreur de validation'
            ),

            new OA\Response(
                response: 404,
                description: 'Ressource introuvable'
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

            'name' => [
                'required',
                'string',
                'max:255',
                'unique:suppliers,name'
            ],

            'phone' => [
                'required',
                'string',
                'max:20',
                'unique:suppliers,phone'
            ],

            'address' => [
                'nullable',
                'string',
                'max:255'
            ],

            'company_name' => [
                'nullable',
                'string',
                'max:255'
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:suppliers,email'
            ],

            'country' => [
                'nullable',
                'string',
                'max:100'
            ],

            'city' => [
                'nullable',
                'string',
                'max:100'
            ],

            'tax_number' => [
                'nullable',
                'string',
                'max:100',
                'unique:suppliers,tax_number'
            ],

            'rccm' => [
                'nullable',
                'string',
                'max:100',
                'unique:suppliers,rccm'
            ],

            'idnat' => [
                'nullable',
                'string',
                'max:100',
                'unique:suppliers,idnat'
            ],

            'status' => [
                'nullable',
                'in:created,active,inactive,blocked'
            ],
        ];

        $messages = [

            'name.required' => 'Le nom du fournisseur est obligatoire.',
            'name.unique' => 'Ce nom existe déjà.',

            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro existe déjà.',

            'email.email' => 'Adresse email invalide.',
            'email.unique' => 'Cet email existe déjà.',

            'tax_number.unique' => 'Le numéro fiscal existe déjà.',
            'rccm.unique' => 'Le RCCM existe déjà.',
            'idnat.unique' => 'Cet IDNAT existe déjà.',
        ];

        $validator = Validator::make(
            $request->all(),
            $rules,
            $messages
        );

        if ($validator->fails()) {

            return response()->json([

                'status' => false,
                'message' => 'Données invalides.',
                'errors' => $validator->errors()

            ], 422);
        }

        DB::beginTransaction();

        try {

            $supplier = Supplier::create([

                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone,
                'status' => $request->status ?? 'created',

                'company_name' => $request->company_name,
                'email' => $request->email,
                'country' => $request->country,
                'city' => $request->city,

                'tax_number' => $request->tax_number,
                'rccm' => $request->rccm,
                'idnat' => $request->idnat,

                'addedBy' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([

                'status' => true,
                'message' => 'Fournisseur créé avec succès.',
                'data' => $supplier

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Erreur création fournisseur', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([

                'status' => false,
                'message' => 'Une erreur est survenue lors de la création du fournisseur.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null

            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/supplierUpdate/{id}",
        summary: "Modifier un fournisseur",
        tags: ["Suppliers"],

        parameters: [

            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,

                description: "ID du fournisseur",

                schema: new OA\Schema(
                    type: "integer",
                    example: 1
                )
            )
        ],

        requestBody: new OA\RequestBody(

            required: true,

            content: new OA\JsonContent(

                required: ["name", "phone"],

                properties: [

                    new OA\Property(
                        property: "name",
                        type: "string",
                        example: "AfriGaz Supplier"
                    ),

                    new OA\Property(
                        property: "phone",
                        type: "string",
                        example: "+243990000000"
                    ),

                    new OA\Property(
                        property: "address",
                        type: "string",
                        nullable: true,
                        example: "Kinshasa Gombe"
                    ),

                    new OA\Property(
                        property: "company_name",
                        type: "string",
                        nullable: true,
                        example: "AfriGaz SARL"
                    ),

                    new OA\Property(
                        property: "email",
                        type: "string",
                        format: "email",
                        nullable: true,
                        example: "contact@afrigaz.com"
                    ),

                    new OA\Property(
                        property: "country",
                        type: "string",
                        nullable: true,
                        example: "RDC"
                    ),

                    new OA\Property(
                        property: "city",
                        type: "string",
                        nullable: true,
                        example: "Kinshasa"
                    ),

                    new OA\Property(
                        property: "tax_number",
                        type: "string",
                        nullable: true,
                        example: "A123456789"
                    ),

                    new OA\Property(
                        property: "rccm",
                        type: "string",
                        nullable: true,
                        example: "CD/KIN/RCCM/24-B-1234"
                    ),

                    new OA\Property(
                        property: "idnat",
                        type: "string",
                        nullable: true,
                        example: "01-F4300-N12345X"
                    ),

                    new OA\Property(
                        property: "status",
                        type: "string",
                        nullable: true,
                        example: "active",
                        enum: ["created", "active", "inactive", "blocked"]
                    ),
                ]
            )
        ),

        responses: [

            new OA\Response(
                response: 200,
                description: "Fournisseur mis à jour avec succès"
            ),

            new OA\Response(
                response: 404,
                description: "Fournisseur introuvable"
            ),

            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            ),

            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            )
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {

            return response()->json([

                'status' => false,
                'message' => 'Fournisseur introuvable.'

            ], 404);
        }

        $rules = [

            'name' => [
                'required',
                'string',
                'max:255',
                'unique:suppliers,name,' . $supplier->id
            ],

            'phone' => [
                'required',
                'string',
                'max:20',
                'unique:suppliers,phone,' . $supplier->id
            ],

            'address' => [
                'nullable',
                'string',
                'max:255'
            ],

            'company_name' => [
                'nullable',
                'string',
                'max:255'
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:suppliers,email,' . $supplier->id
            ],

            'country' => [
                'nullable',
                'string',
                'max:100'
            ],

            'city' => [
                'nullable',
                'string',
                'max:100'
            ],

            'tax_number' => [
                'nullable',
                'string',
                'max:100',
                'unique:suppliers,tax_number,' . $supplier->id
            ],

            'rccm' => [
                'nullable',
                'string',
                'max:100',
                'unique:suppliers,rccm,' . $supplier->id
            ],

            'idnat' => [
                'nullable',
                'string',
                'max:100',
                'unique:suppliers,idnat,' . $supplier->id
            ],

            'status' => [
                'nullable',
                'in:created,active,inactive,blocked'
            ],
        ];

        $messages = [

            'name.required' => 'Le nom du fournisseur est obligatoire.',
            'name.unique' => 'Ce nom existe déjà.',

            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro existe déjà.',

            'email.email' => 'Adresse email invalide.',
            'email.unique' => 'Cet email existe déjà.',

            'tax_number.unique' => 'Le numéro fiscal existe déjà.',
            'rccm.unique' => 'Le RCCM existe déjà.',
            'idnat.unique' => 'Cet IDNAT existe déjà.',
        ];

        $validator = Validator::make(
            $request->all(),
            $rules,
            $messages
        );

        if ($validator->fails()) {

            return response()->json([

                'status' => false,
                'message' => 'Données invalides.',
                'errors' => $validator->errors()

            ], 422);
        }

        DB::beginTransaction();

        try {

            $supplier->update([

                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone,
                'status' => $request->status ?? $supplier->status,

                'company_name' => $request->company_name,
                'email' => $request->email,

                'country' => $request->country,
                'city' => $request->city,

                'tax_number' => $request->tax_number,
                'rccm' => $request->rccm,
                'idnat' => $request->idnat,
            ]);

            DB::commit();

            return response()->json([

                'status' => true,
                'message' => 'Fournisseur mis à jour avec succès.',
                'data' => $supplier->fresh()

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Erreur mise à jour fournisseur', [

                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([

                'status' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null

            ], 500);
        }
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
