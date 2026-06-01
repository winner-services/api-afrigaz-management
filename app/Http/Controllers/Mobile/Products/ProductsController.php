<?php

namespace App\Http\Controllers\Mobile\Products;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\Currency;
use App\Models\StockByBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
}
