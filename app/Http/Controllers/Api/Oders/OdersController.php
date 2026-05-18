<?php

namespace App\Http\Controllers\Api\Oders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OdersController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([

            // 'payment_method' => [
            //     'nullable',
            //     'string',
            //     'max:255'
            // ],

            'delivery_address' => [
                'nullable',
                'string'
            ],

            // 'paid_amount' => [
            //     'nullable',
            //     'numeric',
            //     'min:1'
            // ],

            'order_date' => [
                'required',
                'date'
            ],

            'products' => [
                'required',
                'array',
                'min:1'
            ],

            'products.*.product_id' => [
                'required',
                'exists:products,id'
            ],

            'products.*.quantity' => [
                'required',
                'integer',
                'min:1'
            ],
        ]);

        DB::beginTransaction();
        $distributor = Auth::guard('distributor')->user();

        if (! $distributor) {
            return response()->json([
                'status' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        try {

            $total = 0;

            $order = Order::create([

                'distributor_id' => $distributor->id,

                'reference' => 'ORD-' . strtoupper(uniqid()),

                'status' => 'pending',

                'delivery_address' => $validated['delivery_address'] ?? null,

                'note' => 'Commande des produits',

                'order_date' => $validated['order_date'],
                'paid_amount' => 0,
            ]);


            foreach ($validated['products'] as $item) {

                $product = Product::findOrFail(
                    $item['product_id']
                );

                $quantity = (int) $item['quantity'];

                $unitPrice = $product->wholesale_price;

                $subtotal = $quantity * $unitPrice;

                $total += $subtotal;

                $order->items()->create([

                    'product_id' => $product->id,

                    'quantity' => $quantity,

                    'unit_price' => $unitPrice,

                    'subtotal' => $subtotal,
                ]);
            }

            $order->update([
                'total' => $total
            ]);

            DB::commit();

            $order->load([
                'distributor',
                'items.product'
            ]);

            return response()->json([

                'success' => true,

                'status' => 201,

                'message' => 'Commande créée avec succès',

                'data' => $order

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'status' => 500,

                'message' => 'Erreur lors de la création de la commande',

                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null

            ], 500);
        }
    }
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([

            'delivery_address' => [
                'nullable',
                'string'
            ],

            'order_date' => [
                'required',
                'date'
            ],

            'products' => [
                'required',
                'array',
                'min:1'
            ],

            'products.*.product_id' => [
                'required',
                'exists:products,id'
            ],

            'products.*.quantity' => [
                'required',
                'integer',
                'min:1'
            ],
        ]);

        DB::beginTransaction();

        try {

            $distributor = Auth::guard('distributor')->user();

            if (! $distributor) {

                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            $order = Order::with('items')
                ->where('distributor_id', $distributor->id)
                ->findOrFail($id);

            if (
                in_array(
                    $order->status,
                    ['confirmed', 'processing', 'delivered']
                )
            ) {

                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'Cette commande ne peut plus être modifiée'
                ], 403);
            }

            $total = 0;

            $order->update([

                'delivery_address' => $validated['delivery_address'] ?? null,

                'order_date' => $validated['order_date'],
            ]);

            $order->items()->delete();

            foreach ($validated['products'] as $item) {

                $product = Product::findOrFail(
                    $item['product_id']
                );

                $quantity = (int) $item['quantity'];

                $unitPrice = $product->wholesale_price;

                $subtotal = $quantity * $unitPrice;

                $total += $subtotal;

                $order->items()->create([

                    'product_id' => $product->id,

                    'quantity' => $quantity,

                    'unit_price' => $unitPrice,

                    'subtotal' => $subtotal,
                ]);
            }

            $order->update([
                'total' => $total
            ]);

            DB::commit();

            $order->load([
                'distributor',
                'items.product'
            ]);

            return response()->json([

                'success' => true,

                'status' => 200,

                'message' => 'Commande mise à jour avec succès',

                'data' => $order

            ], 200);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'status' => 500,

                'message' => 'Erreur lors de la mise à jour de la commande',

                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null

            ], 500);
        }
    }
}
