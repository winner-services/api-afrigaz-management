<?php

namespace App\Http\Controllers\Mobile\Products;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\Currency;
use App\Models\Product;
use App\Models\StockByBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductsController extends Controller
{
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

        $gasPrice = Product::where('category_id', 1)->value('wholesale_price');

        $recharge = Product::where('status', 'created')
            ->where('category_id', 2)
            ->select(
                'products.*',
                DB::raw("
            CASE 
        WHEN products.category_id = 2 
        THEN " . ($gasPrice ?? 0) . "
        ELSE 0
    END AS gas_price
        ")
            )
            ->latest()
            ->get();

        $gasProduct = Product::where('category_id', 1)->first();

        $kit = StockByBranch::join('products', 'stock_by_branches.product_id', '=', 'products.id')
            ->where('stock_by_branches.branche_id', $brancheId)
            ->where('products.status', 'created')
            ->whereIn('products.category_id', [2, 3])
            ->where('stock_by_branches.is_empty', 0)
            ->where('stock_by_branches.condition_state', 'good')
            ->select(
                'products.*',
                'stock_by_branches.stock_quantity',

                DB::raw("
    CASE 
        WHEN products.category_id = 2 
        THEN " . ($gasProduct->wholesale_price ?? 0) . "
        ELSE 0
    END AS gas_price
")
            )
            ->get();
        $echange = StockByBranch::join('products', 'stock_by_branches.product_id', '=', 'products.id')
            ->where('stock_by_branches.branche_id', $brancheId)
            ->where('products.status', 'created')
            ->where('products.category_id', 2)
            ->where('stock_by_branches.is_empty', 0)
            ->where('stock_by_branches.condition_state', 'good')

            ->select(
                'products.*',
                'stock_by_branches.stock_quantity as stock_quantity',
                'stock_by_branches.is_empty',

                DB::raw("
            CASE 
        WHEN products.category_id = 2 
        THEN " . ($gasProduct->wholesale_price ?? 0) . "
        ELSE 0
    END AS gas_price
        ")
            )
            ->get();

        $accessoirs = StockByBranch::join('products', 'stock_by_branches.product_id', '=', 'products.id')
            ->where('stock_by_branches.branche_id', $brancheId)
            ->where('products.status', 'created')
            ->where('products.category_id', '>=', 3)
            ->select('products.*', 'stock_by_branches.stock_quantity as stock_quantity')
            ->get();

        return response()->json([
            'devise' => $devise,
            'recharge' => $recharge,
            'echange' => $echange,
            'kit' => $kit,
            'accessoirs' => $accessoirs,
            'status' => 200
        ]);
    }

    public function getTransfertProductOptionsMObile()
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
}
