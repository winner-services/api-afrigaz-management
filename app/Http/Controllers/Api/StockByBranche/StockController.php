<?php

namespace App\Http\Controllers\Api\StockByBranche;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\Currency;
use App\Models\StockByBranch;
use Illuminate\Http\Request;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class StockController extends Controller
{
    #[OA\Get(
        path: "/api/v1/stocksByBranchGetAllData",
        summary: "Liste des stocks par branche",
        tags: ["Stocks"],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre de résultats par page",
                schema: new OA\Schema(type: "integer", example: 20)
            ),
            new OA\Parameter(
                name: "branch_id",
                in: "query",
                description: "Filtrer par branche",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "product_id",
                in: "query",
                description: "Filtrer par produit",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée des stocks",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "stock_quantity", type: "integer"),
                                    new OA\Property(property: "status", type: "string"),
                                    new OA\Property(
                                        property: "branch",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
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

        $query = StockByBranch::with(['branch', 'product'])
            ->orderBy('stock_quantity', 'desc');

        // 🔍 Filtre par branche
        if ($request->filled('branch_id')) {
            $query->where('branche_id', $request->branch_id);
        }

        // 🔍 Filtre par produit
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $stocks = $query->paginate($perPage);

        return response()->json(['status' => 200, 'message' => 'succès', 'data' => $stocks]);
    }

    #[OA\Get(
        path: "/api/v1/stocksByBrancheGetData",
        summary: "Liste des stocks par branche",
        tags: ["Stocks"],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre de résultats par page",
                schema: new OA\Schema(type: "integer", example: 20)
            ),
            new OA\Parameter(
                name: "branch_id",
                in: "query",
                description: "Filtrer par branche",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "product_id",
                in: "query",
                description: "Filtrer par produit",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée des stocks",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "stock_quantity", type: "integer"),
                                    new OA\Property(property: "status", type: "string"),
                                    new OA\Property(
                                        property: "branch",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
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
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            )
        ]
    )]

    public function getStockByBranche(): JsonResponse
    {
        $user = Auth::user();
        // $devide = Currency::latest()->get();
        $devise = Currency::where('status', 'created')
                ->orderByRaw("currency_type = 'devise_principale' DESC")
                ->latest()
                ->get();
        $branches = Branche::latest()->get();

        $page = request("paginate", 10);
        $q = request("q", "");

        $branche = Branche::where('user_id', $user->id)->first();

        if (!$branche) {
            return response()->json([
                'message' => 'Branche non trouvée'
            ], 404);
        }

        $brancheId = request('branche_id', $branche->id);

        $stocks = StockByBranch::with([
            'product.category',
            'product.unit'
        ])
            ->where('branche_id', $brancheId)

            // 🔍 recherche simple
            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->whereHas('product', function ($q3) use ($q) {
                        $q3->where('name', 'like', "%$q%");
                    })
                        ->orWhereHas('product.category', function ($q3) use ($q) {
                            $q3->where('designation', 'like', "%$q%");
                        });
                });
            })

            ->orderBy('id', 'desc')
            ->paginate($page);

        return response()->json(
            [
                'devise' => $devise,
                'branches' => $branches,
                'data' => $stocks
            ]
        );
    }

    // public function returnMultipleBottles(Request $request)
    // {
    //     try {

    //         $data = $request->validate([
    //             'branch_id' => 'required|exists:branches,id',
    //             'note' => 'nullable|string',

    //             'products' => 'required|array|min:1',
    //             'products.*.product_id' => 'required|exists:products,id',

    //             'products.*.returns' => 'required|array|min:1',
    //             'products.*.returns.*.condition' => 'required|in:good,damaged,repair',
    //             'products.*.returns.*.quantity' => 'required|integer|min:1',
    //         ]);

    //         $result = $this->stockService->storeReturn($data);

    //         return response()->json([
    //             'message' => 'Retour enregistré avec succès',
    //             'data' => $result
    //         ], 201);
    //     } catch (\Illuminate\Validation\ValidationException $e) {

    //         return response()->json([
    //             'message' => 'Erreur de validation',
    //             'errors' => $e->errors()
    //         ], 422);
    //     } catch (\Exception $e) {

    //         Log::error('Bottle return error', [
    //             'error' => $e->getMessage()
    //         ]);

    //         return response()->json([
    //             'message' => 'Erreur lors du retour des bouteilles'
    //         ], 500);
    //     }
    // }
}
