<?php

namespace App\Http\Controllers\Api\DettCylindre;

use App\Http\Controllers\Controller;
use App\Models\DetteCylindre;
use App\Models\Product;
use App\Models\StockByBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetteCylindreController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DetteCylindre::with(['details.product', 'distributor', 'addedBy'])->latest();

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->whereHas('distributor', function ($distributorQuery) use ($search) {
                        $distributorQuery->where('name', 'like', "%{$search}%");
                    })
                        ->orWhere('status', 'like', "%{$search}%");
                });
            }

            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
            }

            $dettes = $query->paginate(15);

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $dettes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Impossible de récupérer la liste des dettes.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'distributor_id' => 'nullable|exists:distributors,id',
            'transaction_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        try {

            $branchId = 1;

            $dette = DB::transaction(function () use ($validated, $branchId, &$totalGas) {

                $detteCylindre = DetteCylindre::create([
                    'distributor_id'   => $validated['distributor_id'],
                    'transaction_date' => $validated['transaction_date'],
                    'addedBy'          => Auth::id(),
                    'reference' => fake()->randomNumber(5),
                ]);

                $details = [];

                foreach ($validated['products'] as $productData) {
                    $productId = $productData['product_id'];
                    $qty = $productData['quantity'];

                    $emptyStock = StockByBranch::where('branche_id', $branchId)
                        ->where('product_id', $productId)
                        ->where('is_empty', 1)
                        ->where('condition_state', 'good')
                        ->first();

                    if (!$emptyStock || $emptyStock->stock_quantity < $qty) {
                        throw new \Exception("Stock insuffisant de bouteilles vides pour produit ID: $productId");
                    }

                    $emptyStock->decrement('stock_quantity', $qty);

                    $details[] = [
                        'product_id' => $productId,
                        'quantity'   => $qty,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $detteCylindre->details()->createMany($details);

                return $detteCylindre;
            });

            return response()->json([
                'status' => 201,
                'success' => true,
                'message' => 'Enregistré avec succès !',
                'data' => $dette->load('details')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'distributor_id' => 'nullable|exists:distributors,id',
            'transaction_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $branchId = 1;

            $dette = DB::transaction(function () use ($validated, $branchId, $id) {
                $detteCylindre = DetteCylindre::with('details')->findOrFail($id);

                foreach ($detteCylindre->details as $oldDetail) {
                    StockByBranch::where('branche_id', $branchId)
                        ->where('product_id', $oldDetail->product_id)
                        ->where('is_empty', 1)
                        ->where('condition_state', 'good')
                        ->increment('stock_quantity', $oldDetail->quantity);
                }

                $detteCylindre->update([
                    'distributor_id'   => $validated['distributor_id'],
                    'transaction_date' => $validated['transaction_date'],
                ]);

                $detteCylindre->details()->delete();

                $newDetails = [];
                foreach ($validated['products'] as $productData) {
                    $productId = $productData['product_id'];
                    $qty = $productData['quantity'];

                    $emptyStock = StockByBranch::where('branche_id', $branchId)
                        ->where('product_id', $productId)
                        ->where('is_empty', 1)
                        ->where('condition_state', 'good')
                        ->first();

                    if (!$emptyStock || $emptyStock->stock_quantity < $qty) {
                        throw new \Exception("Stock insuffisant de bouteilles vides pour produit ID: $productId lors de la modification.");
                    }

                    $emptyStock->decrement('stock_quantity', $qty);

                    $newDetails[] = [
                        'product_id' => $productId,
                        'quantity'   => $qty,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $detteCylindre->details()->createMany($newDetails);

                return $detteCylindre;
            });

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Modification enregistrée et stock mis à jour avec succès !',
                'data' => $dette->load('details')
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Une erreur est survenue lors de la modification.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
