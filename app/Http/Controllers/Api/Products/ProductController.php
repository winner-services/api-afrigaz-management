<?php

namespace App\Http\Controllers\Api\Products;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{

    #[OA\Get(
        path: "/api/v1/productGetAllData",
        summary: "Lister",
        tags: ["Products"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function index()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');

        $data = Product::join('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->join('users', 'products.addedBy', '=', 'users.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->select('products.*', 'users.name as addedBy', 'product_categories.designation as category', 'units.abreviation as unit_abreviation', 'units.designation as unit_designation')
            ->where('products.status', 'created')
            ->search(trim($q))
            ->orderBy($sort_field, $sort_direction)
            ->paginate($page);
        return response()->json([
            'message' => 'succes',
            'status' => true,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/productGetOptionsData",
        summary: "Lister",
        tags: ["Products"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]

    public function getProductOptions()
    {
        $data = Product::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: "/api/v1/productStoreData",
        summary: "Créer un produit",
        tags: ["Products"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "gaz"),
                    new OA\Property(property: "category_id", type: "integer", example: 1),
                    new OA\Property(property: "unit_id", type: "integer", example: 1),
                    new OA\Property(property: "wholesale_price", type: "integer", example: 1),
                    new OA\Property(property: "retail_price", type: "integer", example: 1),
                    new OA\Property(property: "type", type: "string", example: "bouteille ou accessoire ou service"),
                    new OA\Property(property: "weight_kg", type: "number", example: 1.5),
                    new OA\Property(property: "minimum_quantity", type: "integer", example: 10)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "créée"),
            new OA\Response(response: 422, description: "Erreur validation")
        ]
    )]

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', 'unique:products,name'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'unit_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:bouteille,accessoire,service'],
            'retail_price' => ['nullable', 'numeric'],
            'wholesale_price' => ['nullable', 'numeric'],
            'weight_kg' => 'nullable|numeric',
            'minimum_quantity' => 'nullable|integer|min:0'
        ];

        $messages = [
            'name.required' => 'Le nom du produit est obligatoire.',
            'name.unique' => 'Ce produit existe déjà.',
            'category_id.exists' => 'Catégorie invalide.',
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
            $product = Product::create([
                'name' => $request->name,
                'category_id' => $request->category_id,
                'unit_id' => $request->unit_id,
                'wholesale_price' => $request->wholesale_price,
                'retail_price' => $request->retail_price,
                'type' => $request->type,
                'weight_kg' => $request->weight_kg,
                'addedBy' => $userId,
                'reference' => fake()->unique()->numerify('PRD-#####'),
                'status' => 'created',
                'minimum_quantity' => $request->minimum_quantity ?? 0
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Produit créé avec succès',
                'data' => $product
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
        path: "/api/v1/productUpdate/{id}",
        summary: "Modifier",
        tags: ["Products"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "gaz"),
                    new OA\Property(property: "category_id", type: "integer", example: 1),
                    new OA\Property(property: "unit_id", type: "integer", example: 1),
                    new OA\Property(property: "wholesale_price", type: "integer", example: 1),
                    new OA\Property(property: "retail_price", type: "integer", example: 1),
                    new OA\Property(property: "type", type: "string", example: "bouteille ou accessoire ou service"),
                    new OA\Property(property: "weight_kg", type: "number", example: 1.5),
                    new OA\Property(property: "minimum_quantity", type: "integer", example: 10)
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
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Produit introuvable'
            ], 404);
        }

        $rules = [
            'name' => ['sometimes', 'string', 'max:255', 'unique:products,name,' . $product->id],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'unit_id' => ['nullable', 'integer'],
            'wholesale_price' => ['nullable', 'numeric'],
            'retail_price' => ['nullable', 'numeric'],
            'type' => ['nullable', 'in:bouteille,accessoire,service'],
            'weight_kg' => ['nullable', 'numeric'],
            'minimum_quantity' => 'nullable|integer|min:0'
        ];

        $messages = [
            'name.unique' => 'Ce produit existe déjà.',
            'category_id.exists' => 'Catégorie invalide.',
            'type.in' => 'Type de produit invalide.',
            'weight_kg.numeric' => 'Le poids doit être un nombre.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($request->only([
            'name',
            'category_id',
            'unit_id',
            'wholesale_price',
            'retail_price',
            'type',
            'weight_kg',
            'minimum_quantity'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Produit mis à jour',
            'data' => $product
        ]);
    }

    #[OA\Put(
        path: "/api/v1/productDelete/{id}",
        summary: "Supprimer",
        tags: ["Products"],
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
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Produit introuvable'
            ], 404);
        }

        $product->status = 'deleted';
        $product->save();

        return response()->json([
            'status' => true,
            'message' => 'Produit supprimé'
        ]);
    }

    #[OA\Get(
        path: "/api/v1/lowStockProductsGetData",
        summary: "Lister",
        tags: ["Products"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function lowStockProducts(Request $request): JsonResponse
    {
        $branche = Branche::where('user_id', Auth::id())->first();

        if (!$branche) {
            return response()->json([
                'message' => 'Branche non trouvée'
            ], 404);
        }

        $brancheId = request('branche_id', $branche->id);

        $perPage = $request->query('paginate', 10);

        $products = Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.minimum_quantity',
                'products.type'
            ])
            ->join('stock_by_branches', 'products.id', '=', 'stock_by_branches.product_id')
            ->where('stock_by_branches.branche_id', $brancheId)
            ->where('products.manage_stock', 1)
            ->where(function ($q) {
                $q->whereRaw('stock_by_branches.stock_quantity = 0')
                    ->orWhereColumn('stock_by_branches.stock_quantity', '<=', 'products.minimum_quantity');
            })
            ->selectRaw('
        stock_by_branches.stock_quantity,
        CASE
            WHEN stock_by_branches.stock_quantity = 0 THEN "rupture"
            WHEN stock_by_branches.stock_quantity <= products.minimum_quantity THEN "critique"
            ELSE "ok"
        END as stock_status
    ')
            ->orderByRaw('stock_by_branches.stock_quantity = 0 DESC')
            ->orderBy('stock_by_branches.stock_quantity', 'asc')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'status' => 200,
            'data' => $products
        ]);
    }
}
