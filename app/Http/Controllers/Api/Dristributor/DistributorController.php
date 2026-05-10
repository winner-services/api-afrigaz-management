<?php

namespace App\Http\Controllers\Api\Dristributor;

use App\Http\Controllers\Controller;
use App\Jobs\SendDistributorSmsJob;
use App\Models\Caussion;
use App\Models\Currency;
use App\Models\DebtDistributor;
use App\Models\Distributor;
use App\Models\StockByBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $devise = Currency::where('status', 'created')
            ->orderByRaw("currency_type = 'devise_principale' DESC")
            ->latest()
            ->get();
        $perPage = $request->query('paginate', 10);
        $search = $request->query('q', '');

        $items = Distributor::with('addedBy:id,name', 'categoryDistributor:id,designation', 'debts')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhereHas('addedBy', function ($q3) use ($search) {
                            $q3->where('name', 'like', "%$search%");
                        })
                        ->orWhereHas('categoryDistributor', function ($q4) use ($search) {
                            $q4->where('name', 'like', "%$search%");
                        });
                });
            })
            ->where('is_deleted', false)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'message' => 'success',
            'status' => 200,
            'devise' => $devise,
            'data' => $items
        ]);
    }

    #[OA\Get(
        path: "/api/v1/distributorGetWithDebt",
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
    public function distributorGetWithDebt(Request $request): JsonResponse
    {
        $devise = Currency::where('status', 'created')
            ->orderByRaw("currency_type = 'devise_principale' DESC")
            ->latest()
            ->get();
        $perPage = $request->query('paginate', 10);
        $search = $request->query('q', '');

        $items = Distributor::with('addedBy:id,name', 'categoryDistributor:id,designation', 'debts')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhereHas('addedBy', function ($q3) use ($search) {
                            $q3->where('name', 'like', "%$search%");
                        })
                        ->orWhereHas('categoryDistributor', function ($q4) use ($search) {
                            $q4->where('name', 'like', "%$search%");
                        });
                });
            })
            ->whereHas('debts', function ($q) {
                $q->whereIn('status', ['pending', 'partial']);
            })
            ->where('is_deleted', false)
            ->orderByDesc('id')
            ->paginate($perPage);
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'devise' => $devise,
            'data' => $items
        ]);
    }
    #[OA\Get(
        path: "/api/v1/distributorsOptionData",
        summary: "Lister",
        tags: ["Distributeurs"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]

    public function getData(Request $request)
    {
        try {

            $search = $request->query('q', '');

            $data = Distributor::with([
                'category:id,designation',
                'category.caussions.items.product:id,name,category_id,unit_id',
                'category.caussions.items.product.unit:id,abreviation'
            ])
                ->whereDoesntHave('shippings')
                ->when($search, function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhereHas('category', function ($q2) use ($search) {
                            $q2->where('designation', 'like', "%$search%");
                        });
                })
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($distributor) {

                    foreach ($distributor->category->caussions as $caussion) {
                        foreach ($caussion->items as $item) {

                            $product = $item->product;

                            if (!$product) continue;

                            if ((int) $product->category_id === 2) {

                                $stock = StockByBranch::where('product_id', $product->id)
                                    ->where('branche_id', 1)
                                    ->where('is_empty', 0)
                                    ->where('condition_state', 'good')
                                    ->value('stock_quantity');

                                $product->stock_quantity = $stock ?? 0;
                            } else {

                                $stock = StockByBranch::where('product_id', $product->id)
                                    ->where('branche_id', 1)
                                    ->value('stock_quantity');

                                $product->stock_quantity = $stock ?? 0;
                            }
                        }
                    }

                    return $distributor;
                });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Liste des distributeurs avec caution',
                'data' => $data
            ]);
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
        summary: "Créer un distributeur (physique ou entreprise avec KYC)",
        tags: ["Distributeurs"],
        security: [["bearerAuth" => []]],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["type", "name"],

                    properties: [

                        new OA\Property(
                            property: "type",
                            type: "string",
                            enum: ["physical", "company"],
                            example: "physical"
                        ),

                        new OA\Property(property: "name", type: "string", example: "Jean Paul"),
                        new OA\Property(property: "gender", type: "string", example: "M"),

                        new OA\Property(property: "identity_type", type: "string", example: "Carte d'électeur"),
                        new OA\Property(property: "identity_number", type: "string", example: "123456789"),

                        new OA\Property(property: "rccm", type: "string", example: "RCCM12345"),
                        new OA\Property(property: "idnat", type: "string", example: "IDNAT67890"),
                        new OA\Property(property: "tax_number", type: "string", example: "IMPOT123"),
                        new OA\Property(property: "manager_name", type: "string", example: "John Manager"),

                        new OA\Property(property: "phone", type: "string", example: "+243810000000"),
                        new OA\Property(property: "email", type: "string", example: "test@mail.com"),

                        new OA\Property(property: "country", type: "string", example: "RDC"),
                        new OA\Property(property: "city", type: "string", example: "Kinshasa"),
                        new OA\Property(property: "commune", type: "string", example: "Gombe"),
                        new OA\Property(property: "quartier", type: "string", example: "Centre"),
                        new OA\Property(property: "avenue", type: "string", example: "Av. Fikin"),

                        new OA\Property(property: "category_distributor_id", type: "integer", example: 1),

                        new OA\Property(
                            property: "identity_document",
                            type: "string",
                            format: "binary",
                            description: "Pièce d'identité (PDF, JPG, PNG)"
                        )
                    ]
                )
            )
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: "Distributeur créé avec succès"
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
    public function store(Request $request): JsonResponse
    {
        try {

            $data = $request->validate([

                'type' => 'required|in:physical,company',

                'name' => 'required|string|max:255|unique:distributors,name',

                'gender' => 'nullable|string|max:20',

                'rccm' => 'nullable|string|max:255',
                'idnat' => 'nullable|string|max:255',
                'tax_number' => 'nullable|string|max:255',
                'manager_name' => 'nullable|string|max:255',

                'identity_type' => 'nullable|string|max:100',
                'identity_number' => 'nullable|string|max:100',

                'identity_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',

                'phone' => 'nullable|string|max:20|unique:distributors,phone',
                'email' => 'nullable|email|unique:distributors,email',
                'password' => 'nullable|string|max:100',

                'country' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'commune' => 'nullable|string|max:100',
                'quartier' => 'nullable|string|max:100',
                'avenue' => 'nullable|string|max:100',

                'category_distributor_id' => 'nullable|integer|exists:category_distributors,id',
            ]);
            $data['password'] =  bcrypt($request->input('password'));

            if ($request->hasFile('identity_document')) {

                $filename = uniqid() . '.' . $request->file('identity_document')->extension();

                $path = $request->file('identity_document')
                    ->storeAs('distributors/identity', $filename, 'public');

                $data['identity_document'] = $path;
            }

            $result = DB::transaction(function () use ($data) {

                $data['addedBy'] = Auth::id();
                $data['reference'] = 'DB-' . random_int(10000, 99999);

                $item = Distributor::create($data);

                $caution = Caussion::where(
                    'category_distributor_id',
                    $item->category_distributor_id
                )->first();

                DebtDistributor::create([
                    'distributor_id' => $item->id,
                    'loan_amount' => $caution?->amount ?? 0,
                    'paid_amount' => 0,
                    'transaction_date' => now(),
                    'motif' => 'Caution initiale',
                    'status' => 'pending',
                    'reference' => $data['reference'],
                    'user_id' => Auth::id(),
                ]);

                return $item;
            });

            if ($result->phone) {
                SendDistributorSmsJob::dispatch($result->id)
                    ->onQueue('sms');
            }

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
        summary: "Mettre à jour un distributeur (physique ou entreprise)",
        tags: ["Distributeurs"],
        security: [["bearerAuth" => []]],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID du distributeur",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [

                        new OA\Property(
                            property: "type",
                            type: "string",
                            enum: ["physical", "company"],
                            example: "company"
                        ),

                        new OA\Property(property: "name", type: "string", example: "Entreprise ABC"),
                        new OA\Property(property: "gender", type: "string", example: "M"),

                        new OA\Property(property: "rccm", type: "string", example: "RCCM12345"),
                        new OA\Property(property: "idnat", type: "string", example: "IDNAT67890"),
                        new OA\Property(property: "tax_number", type: "string", example: "IMPOT123"),
                        new OA\Property(property: "manager_name", type: "string", example: "John Manager"),

                        new OA\Property(property: "identity_type", type: "string", example: "Carte d'électeur"),
                        new OA\Property(property: "identity_number", type: "string", example: "123456789"),

                        new OA\Property(
                            property: "identity_document",
                            type: "string",
                            format: "binary",
                            description: "Document d'identité (PDF, JPG, PNG)"
                        ),

                        new OA\Property(property: "phone", type: "string", example: "+243810000000"),
                        new OA\Property(property: "email", type: "string", example: "update@mail.com"),

                        new OA\Property(property: "country", type: "string", example: "RDC"),
                        new OA\Property(property: "city", type: "string", example: "Kinshasa"),
                        new OA\Property(property: "commune", type: "string", example: "Gombe"),
                        new OA\Property(property: "quartier", type: "string", example: "Centre"),
                        new OA\Property(property: "avenue", type: "string", example: "Av. Fikin"),

                        new OA\Property(property: "category_distributor_id", type: "integer", example: 1),
                    ]
                )
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: "Distributeur mis à jour avec succès"
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            ),
            new OA\Response(
                response: 404,
                description: "Distributeur introuvable"
            ),
            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            )
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        try {

            $item = Distributor::findOrFail($id);

            $data = $request->validate([

                'type' => 'nullable|in:physical,company',

                'name' => 'nullable|string|max:255|unique:distributors,name,' . $id,

                'gender' => 'nullable|string|max:20',

                'rccm' => 'nullable|string|max:255',
                'idnat' => 'nullable|string|max:255',
                'tax_number' => 'nullable|string|max:255',
                'manager_name' => 'nullable|string|max:255',

                'identity_type' => 'nullable|string|max:100',
                'identity_number' => 'nullable|string|max:100',

                'identity_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',

                'phone' => 'nullable|string|max:20|unique:distributors,phone,' . $id,
                'email' => 'nullable|email|unique:distributors,email,' . $id,
                'password' => 'nullable|string|max:100',

                'country' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'commune' => 'nullable|string|max:100',
                'quartier' => 'nullable|string|max:100',
                'avenue' => 'nullable|string|max:100',

                'category_distributor_id' => 'nullable|integer|exists:category_distributors,id',
            ]);
            $data['password'] =  bcrypt($request->input('password'));
            if ($request->hasFile('identity_document')) {

                if ($item->identity_document) {
                    Storage::disk('public')->delete($item->identity_document);
                }

                $filename = uniqid() . '.' . $request->file('identity_document')->extension();

                $path = $request->file('identity_document')
                    ->storeAs('distributors/identity', $filename, 'public');

                $data['identity_document'] = $path;
            }

            DB::transaction(function () use ($item, $data) {

                $data['updatedBy'] = Auth::id();

                $item->update($data);
            });

            return response()->json([
                'message' => 'Distributeur mis à jour avec succès',
                'status' => 200,
                'data' => $item->fresh()
            ]);
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
