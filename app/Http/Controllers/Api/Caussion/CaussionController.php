<?php

namespace App\Http\Controllers\Api\Caussion;

use App\Http\Controllers\Controller;
use App\Models\Caussion;
use App\Models\CaussionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CaussionController extends Controller
{
    public function storeData(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'category_distributor_id' => 'required|exists:category_distributors,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $caussion = Caussion::create([
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'category_distributor_id' => $request->category_distributor_id,
                'addedBy' => Auth::id(),
            ]);

            foreach ($request->items as $item) {
                CaussionItem::create([
                    'caussion_id' => $caussion->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Caution créée avec succès',
                'data' => $caussion->load('items.product')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateData(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'category_distributor_id' => 'required|exists:category_distributors,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $caussion = Caussion::findOrFail($id);

            $caussion->update([
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'category_distributor_id' => $request->category_distributor_id,
            ]);

            // 🔥 delete old items
            CaussionItem::where('caussion_id', $id)->delete();

            // 🔥 insert new items
            foreach ($request->items as $item) {
                CaussionItem::create([
                    'caussion_id' => $caussion->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Caution mise à jour',
                'data' => $caussion->load('items.product')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function delete($id)
    {
        try {
            $caussion = Caussion::findOrFail($id);

            $caussion->status = 'deleted';
            $caussion->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Caution supprimée'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getData(Request $request)
    {
        try {

            $perPage = $request->query('per_page', 10);
            $search = $request->query('q', '');

            $query = Caussion::with(['items.product', 'distributor']);

            if (!empty($search)) {
                $query->whereHas('distributor', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                });
            }

            $data = $query
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $data
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
