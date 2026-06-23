<?php

namespace App\Http\Controllers\Api\DettCylindre;

use App\Http\Controllers\Controller;
use App\Models\DetteCylindre;
use App\Models\DetteCylindreDetail;
use App\Models\HistoriqueRetourDetteCylindre;
use App\Models\Product;
use App\Models\StockByBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetteCylindreController extends Controller
{
    public function rapprotDetteCylindre()
    {
        try {
            $data = DetteCylindre::with(['details.product', 'details.product.unit', 'distributor', 'addedBy'])->latest()->get();

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $data
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
    public function index(Request $request)
    {
        try {
            $query = DetteCylindre::with(['details.product', 'details.product.unit', 'distributor', 'addedBy'])->latest();

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

    public function receiveCylindreRetour(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|integer|exists:dette_cylindre_details,id',
                'items.*.received_quantity' => 'required|integer|min:1'
            ]);

            $branchId = 1;
            $userId = Auth::user()->id;

            return DB::transaction(function () use ($data, $branchId, $userId) {
                $updatedDetails = [];

                foreach ($data['items'] as $row) {
                    $detail = DetteCylindreDetail::where('id', $row['id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($detail->status === 'completed') {
                        throw new \Exception(
                            "Le retour est déjà totalement complété pour le produit ID {$detail->product_id}"
                        );
                    }

                    $alreadyReturned = $detail->returned_quantity ?? 0;
                    $remaining = $detail->quantity - $alreadyReturned;

                    if ($row['received_quantity'] > $remaining) {
                        throw new \Exception(
                            "La quantité retournée ({$row['received_quantity']}) dépasse le restant à recevoir ({$remaining}) pour la ligne ID {$detail->id}"
                        );
                    }

                    $detail->returned_quantity = $alreadyReturned + $row['received_quantity'];

                    if ($detail->returned_quantity >= $detail->quantity) {
                        $detail->status = 'completed';
                        $detail->date_retour = now();
                    } else {
                        $detail->status = 'partial';
                    }

                    $detail->save();

                    $distributorId = $detail->detteCylindre ? $detail->detteCylindre->distributor_id : null;

                    HistoriqueRetourDetteCylindre::create([
                        'dette_detail_id'   => $detail->id,
                        'distributor_id'    => $distributorId,
                        'product_id'        => $detail->product_id,
                        'returned_quantity' => $row['received_quantity'],
                        'date_retour'       => now(),
                        'addedBy'           => $userId,
                    ]);

                    $emptyStock = StockByBranch::where('branche_id', $branchId)
                        ->where('product_id', $detail->product_id)
                        ->where('is_empty', 1)
                        ->where('condition_state', 'good')
                        ->first();

                    if ($emptyStock) {
                        $emptyStock->increment('stock_quantity', $row['received_quantity']);
                    } else {
                        StockByBranch::create([
                            'branche_id' => $branchId,
                            'product_id' => $detail->product_id,
                            'is_empty' => 1,
                            'condition_state' => 'good',
                            'stock_quantity' => $row['received_quantity'],
                        ]);
                    }

                    $updatedDetails[] = $detail->fresh();
                }

                $detteIds = collect($updatedDetails)->pluck('dette_cylindre_id')->unique();

                foreach ($detteIds as $detteId) {
                    $dette = DetteCylindre::find($detteId);

                    if (
                        $dette &&
                        $dette->details()->where('status', '!=', 'completed')->count() === 0
                    ) {
                        $dette->update([
                            'status' => 'completed'
                        ]);
                    } else {
                        $dette->update([
                            'status' => 'partial'
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Retour de cylindres validé avec succès',
                    'status' => 200,
                    'data' => $updatedDetails
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Retour Cylindre Error', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Impossible de valider le retour',
                'errors' => [$e->getMessage()],
                'status' => 422
            ], 422);
        }
    }

    // public function receiveCylindreRetour(Request $request): JsonResponse
    // {
    //     try {
    //         $data = $request->validate([
    //             'items' => 'required|array|min:1',
    //             'items.*.id' => 'required|integer|exists:dette_cylindre_details,id',
    //             'items.*.received_quantity' => 'required|integer|min:1'
    //         ]);

    //         $branchId = 1;

    //         return DB::transaction(function () use ($data, $branchId) {
    //             $updatedDetails = [];

    //             foreach ($data['items'] as $row) {
    //                 $detail = DetteCylindreDetail::where('id', $row['id'])
    //                     ->lockForUpdate()
    //                     ->firstOrFail();

    //                 if ($detail->status === 'completed') {
    //                     throw new \Exception(
    //                         "Le retour est déjà totalement complété pour le produit ID {$detail->product_id}"
    //                     );
    //                 }

    //                 $alreadyReturned = $detail->returned_quantity ?? 0;
    //                 $remaining = $detail->quantity - $alreadyReturned;

    //                 if ($row['received_quantity'] > $remaining) {
    //                     throw new \Exception(
    //                         "La quantité retournée ({$row['received_quantity']}) dépasse le restant à recevoir ({$remaining}) pour la ligne ID {$detail->id}"
    //                     );
    //                 }

    //                 $detail->returned_quantity = $alreadyReturned + $row['received_quantity'];

    //                 if ($detail->returned_quantity >= $detail->quantity) {
    //                     $detail->status = 'completed';
    //                     $detail->date_retour = now();
    //                 } else {
    //                     $detail->status = 'partial';
    //                 }

    //                 $detail->save();

    //                 $emptyStock = StockByBranch::where('branche_id', $branchId)
    //                     ->where('product_id', $detail->product_id)
    //                     ->where('is_empty', 1)
    //                     ->where('condition_state', 'good')
    //                     ->first();

    //                 if ($emptyStock) {
    //                     $emptyStock->increment('stock_quantity', $row['received_quantity']);
    //                 } else {
    //                     StockByBranch::create([
    //                         'branche_id' => $branchId,
    //                         'product_id' => $detail->product_id,
    //                         'is_empty' => 1,
    //                         'condition_state' => 'good',
    //                         'stock_quantity' => $row['received_quantity'],
    //                     ]);
    //                 }

    //                 $updatedDetails[] = $detail->fresh();
    //             }

    //             $detteIds = collect($updatedDetails)->pluck('dette_cylindre_id')->unique();

    //             foreach ($detteIds as $detteId) {
    //                 $dette = DetteCylindre::find($detteId);

    //                 if (
    //                     $dette &&
    //                     $dette->details()->where('status', '!=', 'completed')->count() === 0
    //                 ) {
    //                     $dette->update([
    //                         'status' => 'completed'
    //                     ]);
    //                 } else {
    //                     $dette->update([
    //                         'status' => 'partial'
    //                     ]);
    //                 }
    //             }

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Retour de cylindres validé avec succès',
    //                 'status' => 200,
    //                 'data' => $updatedDetails
    //             ]);
    //         });
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return response()->json([
    //             'message' => 'Erreur de validation',
    //             'errors' => $e->errors(),
    //             'status' => 422
    //         ], 422);
    //     } catch (\Throwable $e) {
    //         Log::error('Retour Cylindre Error', ['error' => $e->getMessage()]);

    //         return response()->json([
    //             'message' => 'Impossible de valider le retour',
    //             'errors' => [$e->getMessage()],
    //             'status' => 422
    //         ], 422);
    //     }
    // }
}
