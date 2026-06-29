<?php

namespace App\Http\Controllers\Mobile\Products;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Branche;
use App\Models\Currency;
use App\Models\ItemsTransfer;
use App\Models\Product;
use App\Models\StockByBranch;
use App\Models\Transfer;
use App\Models\User;
use App\Services\ImageService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProductsController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function getStockByBrancheMobile()
    {
        $user = Auth::user();
        $devise = Currency::where('status', 'created')
            ->orderByRaw("currency_type = 'devise_principale' DESC")
            ->latest()
            ->get();

        $branches = Branche::latest()->get();
        $branche = Branche::where('user_id', $user->id)->first();

        $brancheId = $branche ? $branche->id : null;

        $q = request('q', null);
        $perPage = request('per_page', 10);

        $stocks = StockByBranch::with(['product.category', 'product.unit', 'product.addedBy'])
            ->where('branche_id', $brancheId)
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

            ->orderByDesc('id')
            ->paginate($perPage);

        $stocks->getCollection()->transform(function ($stock) {

            $product = $stock->product;

            if (!$product) return $stock;

            if ((int) $stock->categorie_id === 2) {

                $etat = ((bool) $stock->is_empty) ? 'vide' : 'pleine';

                $stock->product_name =
                    $product->name . ' - ' . $etat . ' - ' . $stock->condition_state;
            } else {

                $stock->product_name = $product->name;
            }

            return $stock;
        });

        return response()->json([
            'devise' => $devise,
            'branches' => $branches,
            'filters' => [
                'branche_id' => $brancheId,
                'q' => $q,
                'per_page' => $perPage,
            ],
            'data' => $stocks
        ]);
    }

    public function getProductOptionsSaleMobile()
    {
        $user = Auth::user();
        $branche = Branche::where('user_id', $user->id)->first();

        $brancheId = $branche ? $branche->id : null;
        $devise = Currency::where('status', 'created')
            ->orderByRaw("currency_type = 'devise_principale' DESC")
            ->latest()
            ->get();

        $gasProduct = Product::where('category_id', 1)->first();
        $gasWholesalePrice = $gasProduct->wholesale_price ?? 0;
        $gasRetailPrice = $gasProduct->retail_price ?? 0;

        $echange = StockByBranch::join('products', 'stock_by_branches.product_id', '=', 'products.id')
            ->where('stock_by_branches.branche_id', $brancheId)
            ->where('products.status', 'created')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('products.category_id', 2)
                        ->where('stock_by_branches.is_empty', 0)
                        ->where('stock_by_branches.condition_state', 'good');
                })->orWhere('products.category_id', '>=', 3);
            })
            ->select(
                'products.*',
                'stock_by_branches.stock_quantity as stock_quantity',
                'stock_by_branches.is_empty',

                DB::raw("
        CASE
            WHEN products.category_id = 2
            THEN " . ($gasProduct->retail_price ?? 0) . "
            WHEN products.category_id >= 3
            THEN products.retail_price
            ELSE 0
        END AS gas_price
    "),

                DB::raw("
        CASE
            WHEN products.category_id >= 3
            THEN 1
            ELSE products.weight_kg
        END AS weight_kg
    ")
            )
            ->get();

        $kit = StockByBranch::join('products', 'stock_by_branches.product_id', '=', 'products.id')
            ->where('stock_by_branches.branche_id', $brancheId)
            ->where('products.status', 'created')
            ->whereIn('products.category_id', [2, 3])
            ->where('stock_by_branches.is_empty', 0)
            ->where('stock_by_branches.condition_state', 'good')
            ->select(
                'products.*',
                'stock_by_branches.stock_quantity',
                DB::raw("CASE WHEN products.category_id = 2 THEN " . $gasWholesalePrice . " ELSE 0 END AS gas_price"),
                DB::raw("CASE WHEN products.category_id = 2 THEN " . $gasRetailPrice . " ELSE 0 END AS gas_retail_price")
            )
            ->get();

        return response()->json([
            'status' => 200,
            'success' => true,
            'devise' => $devise,
            'echange' => $echange,
            'kit' => $kit,
        ]);
    }

    public function getTransfertProductOptionsMobile()
    {
        $user = Auth::user();
        $branche = Branche::where('user_id', $user->id)->first();

        $brancheId = $branche ? $branche->id : null;

        $gasPrice = Product::where('category_id', 1)
            ->value('wholesale_price') ?? 0;

        $data = Product::join('units', 'products.unit_id', '=', 'units.id')
            ->leftJoin('stock_by_branches', function ($join) use ($brancheId) {
                $join->on('products.id', '=', 'stock_by_branches.product_id')
                    ->where('stock_by_branches.branche_id', '=', $brancheId);
            })
            ->where('products.status', 'created')
            ->whereIn('products.category_id', [2, 3])
            ->where('stock_by_branches.is_empty', 0)
            ->where('stock_by_branches.condition_state', 'good')
            ->select(
                'products.*',
                'units.abreviation',
                'stock_by_branches.stock_quantity'
            )
            ->selectRaw(
                'CASE 
                WHEN products.category_id = 2 THEN ?
                ELSE NULL
            END AS gas_price',
                [$gasPrice]
            )
            ->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => $data
        ]);
    }

    public function submitDeliveryConfirmation(Request $request)
    {
        try {

            $data = $request->validate([
                'driver_password' => 'required|string',
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|integer|exists:items_transfers,id',
                'items.*.received_quantity' => 'required|integer|min:1'
            ]);

            return DB::transaction(function () use ($data) {
                $about = About::first();
                if ($about) {
                    $this->imageService->transform($about, ['logo', 'logo2']);
                }

                $updatedItems = [];

                $firstItem = ItemsTransfer::with('transfer')
                    ->lockForUpdate()
                    ->findOrFail($data['items'][0]['id']);

                $transfer = $firstItem->transfer;

                if (!$transfer) {
                    throw new \Exception('Transfert introuvable');
                }

                $driver = User::find($transfer->driver);

                if (!$driver) {
                    throw new \Exception('Chauffeur introuvable');
                }

                if (!Hash::check($data['driver_password'], $driver->password)) {
                    throw new \Exception('Mot de passe du chauffeur incorrect');
                }
                $transfer->confirm_driver_id = $driver->id;
                $transfer->save();

                foreach ($data['items'] as $row) {

                    $item = ItemsTransfer::where('id', $row['id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($item->transfer_id != $transfer->id) {
                        throw new \Exception(
                            "L'item {$item->id} n'appartient pas au transfert sélectionné."
                        );
                    }

                    if ($item->status === 'completed') {
                        throw new \Exception(
                            "Réception déjà terminée pour le produit ID {$item->product_id}"
                        );
                    }

                    $remaining = $item->quantity - $item->received_quantity;

                    if ($row['received_quantity'] > $remaining) {
                        throw new \Exception(
                            "La quantité reçue dépasse le restant à recevoir pour l'item {$item->id}"
                        );
                    }

                    $item->received_quantity += $row['received_quantity'];

                    $item->status = $item->received_quantity >= $item->quantity
                        ? 'completed'
                        : 'partial';

                    $item->save();

                    app(StockService::class)->increaseStock(
                        $transfer->to_branch_id,
                        $item->product_id,
                        $row['received_quantity'],
                        0,
                        'good'
                    );

                    $updatedItems[] = $item->fresh();
                }

                $hasIncompleteItems = $transfer->items()
                    ->where('status', '!=', 'completed')
                    ->exists();

                $hasReceivedItems = $transfer->items()
                    ->whereIn('status', ['partial', 'completed'])
                    ->exists();

                if (!$hasIncompleteItems) {

                    $transfer->update([
                        'status' => 'completed'
                    ]);
                } elseif ($hasReceivedItems) {

                    $transfer->update([
                        'status' => 'partial'
                    ]);
                }

                return response()->json([
                    'message' => 'Réception validée avec succès',
                    'status' => 200,
                    'point_vente' => Branche::where('user_id', Auth::id())->value('name'),
                    'info_company' => $about,
                    'data' => $updatedItems
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Throwable $e) {

            Log::error('Reception error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Impossible de valider la réception',
                'errors' => [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }

    public function transfersGetMobile(Request $request)
    {
        $perPage = $request->query('per_page', 10);

        $branchId = Branche::where('user_id', Auth::id())->value('id');

        $query = Transfer::with([
            'fromBranch',
            'toBranch',
            'charoit',
            'driver',
            'user',
            'items.product'
        ])
            ->where(function ($q) use ($branchId) {
                $q->where('from_branch_id', $branchId)
                    ->orWhere('to_branch_id', $branchId);
            })
            ->orderBy('created_at', 'desc');

        if ($request->filled('from_branch_id')) {
            $query->where('from_branch_id', $request->from_branch_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('transfer_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('transfer_date', '<=', $request->to_date);
        }

        $transfers = $query->paginate($perPage);

        return response()->json([
            'status' => 200,
            'success' => true,
            'point_vente' => Branche::where('user_id', Auth::id())->value('name'),
            'message' => 'succès',
            'data' => $transfers
        ]);
    }
}
