<?php

namespace App\Http\Controllers\Api\Sale;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
use OpenApi\Attributes as OA;

class SaleController extends Controller
{
    #[OA\Post(
        path: "/api/v1/saleStoreData",
        summary: "Créer une vente avec paiement ou dette",
        tags: ["Sales"],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["branch_id", "products"],
                properties: [

                    new OA\Property(property: "branch_id", type: "integer", example: 1),
                    new OA\Property(property: "customer_id", type: "integer", nullable: true, example: 1),
                    new OA\Property(property: "account_id", type: "integer", nullable: true, example: 1),
                    new OA\Property(property: "paid_amount", type: "number", format: "float", example: 5000),
                    new OA\Property(property: "sale_type", type: "string", example: "proforma"),
                    new OA\Property(property: "sale_category", type: "string", example: "detail"),

                    new OA\Property(
                        property: "products",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "product_id", type: "integer", example: 1),
                                new OA\Property(property: "quantity", type: "integer", example: 2),
                                new OA\Property(property: "unit_price", type: "number", format: "float", example: 1000),
                            ]
                        )
                    ),
                ]
            )
        ),

        responses: [

            new OA\Response(
                response: 201,
                description: "Vente créée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Vente enregistrée avec paiement/dette"),
                        new OA\Property(property: "sale_id", type: "integer", example: 10),
                        new OA\Property(property: "reference", type: "string", example: "SALE-20260405120000"),
                        new OA\Property(property: "total", type: "number", example: 15000)
                    ]
                )
            ),

            new OA\Response(
                response: 409,
                description: "Stock insuffisant",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Stock insuffisant pour certains produits"),
                        new OA\Property(
                            property: "errors",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "product_id", type: "integer", example: 1),
                                    new OA\Property(property: "message", type: "string", example: "Stock insuffisant"),
                                    new OA\Property(property: "available", type: "integer", example: 2),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: "Erreur interne serveur"
            )
        ]
    )]

    public function store(Request $request): JsonResponse
    {
        try {

            $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.unit_price' => 'required|integer|min:1',
                'customer_id' => 'nullable|exists:customers,id',
                'paid_amount' => 'nullable|numeric|min:0',
                'account_id' => 'required|exists:cash_accounts,id',
                'sale_type' => 'required|string',
                'sale_category' => 'required|string',
            ]);
            $reference1 = 'SALE-' . date('YmdHis');
            if ('sale_type' === 'Proforma') {
                $sale = Sale::create([
                    'reference' => $reference1,
                    'branch_id' => $request->branch_id,
                    'addedBy' => Auth::id(),
                    'total_amount' => 0,
                    'paid_amount' => $request->paid_amount ?? 0,
                    'transaction_date' => now(),
                    'customer_id' => $request->customer_id,
                    'sale_type' => $request->sale_type,
                    'sale_category' => $request->sale_category,
                ]);

                foreach ($request->products as $item) {

                    $product = Product::findOrFail($item['product_id']);
                    $quantity = $item['quantity'];

                    $lineTotal = $quantity * $item['unit_price'];

                    $sale->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $item['unit_price'],
                        'total_price' => $lineTotal
                    ]);
                }
            }

            $sale = SaleService::createSaleWithPayment(
                $request->branch_id,
                $request->products,
                Auth::id(),
                $request->customer_id,
                $request->paid_amount ?? 0,
                $request->account_id,
                $request->sale_type,
                $request->sale_category
            );

            return response()->json([
                'message' => 'Vente enregistrée',
                'status' => 201,
                'sale_id' => $sale->id,
                'reference' => $sale->reference,
                'total' => $sale->total_amount
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\App\Services\StockException $e) {

            return response()->json([
                'message' => 'Erreur de stock',
                'errors' => $e->getErrors()
            ], 400);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Erreur lors de la création de la vente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/salesGetAllData",
        summary: "Historique complet des ventes",
        tags: ["Sales"],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre de résultats par page",
                schema: new OA\Schema(type: "integer", example: 20)
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                description: "Numéro de page",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée des ventes",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "reference", type: "string"),
                                    new OA\Property(property: "transaction_date", type: "string", format: "date"),
                                    new OA\Property(
                                        property: "customer",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "user",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "items",
                                        type: "array",
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: "id", type: "integer"),
                                                new OA\Property(property: "quantity", type: "integer"),
                                                new OA\Property(property: "unit_price", type: "number"),
                                                new OA\Property(
                                                    property: "product",
                                                    type: "object",
                                                    properties: [
                                                        new OA\Property(property: "id", type: "integer"),
                                                        new OA\Property(property: "name", type: "string")
                                                    ]
                                                )
                                            ]
                                        )
                                    ),
                                    new OA\Property(property: "total_amount", type: "number"),
                                    new OA\Property(property: "paid_amount", type: "number"),
                                    new OA\Property(property: "status", type: "string")
                                ]
                            )
                        ),
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);

        $sales = Sale::with(['branch', 'customer', 'user', 'saleItems.product'])
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 200,
            'data' => $sales
        ]);
    }
}
