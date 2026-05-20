<?php

namespace App\Http\Controllers\Api\Oders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OdersController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [

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
                'integer',
                'exists:products,id'
            ],

            'products.*.quantity' => [
                'required',
                'integer',
                'min:1'
            ],

        ]);

        if ($validator->fails()) {

            return response()->json([

                'success' => false,

                'status' => 422,

                'message' => 'Erreur de validation',

                'errors' => $validator->errors(),

                'data_received' => $request->all()

            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {

            $distributor = Auth::guard('distributor')->user();

            if (! $distributor) {

                return response()->json([

                    'success' => false,

                    'status' => 401,

                    'message' => 'Non authentifié'

                ], 401);
            }

            $total = 0;

            $order = Order::create([

                'distributor_id' => $distributor->id,

                'reference' => 'ORD-' . strtoupper(uniqid()),

                'status' => 'pending',

                'delivery_address' => $validated['delivery_address'] ?? null,

                'note' => 'Commande des produits',

                'order_date' => $validated['order_date'],

                'amount' => 0,

                'total' => 0,
            ]);

            foreach ($validated['products'] as $item) {

                $product = Product::find($item['product_id']);

                if (! $product) {

                    DB::rollBack();

                    return response()->json([

                        'success' => false,

                        'status' => 404,

                        'message' => 'Produit introuvable',

                        'product_id' => $item['product_id']

                    ], 404);
                }

                $quantity = (int) $item['quantity'];

                $unitPrice = (float) $product->wholesale_price;

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
                    : 'Erreur interne du serveur'

            ], 500);
        }
    }
    public function updateData(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [

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
                'integer',
                'exists:products,id'
            ],

            'products.*.quantity' => [
                'required',
                'integer',
                'min:1'
            ],

        ]);

        if ($validator->fails()) {

            return response()->json([

                'success' => false,

                'status' => 422,

                'message' => 'Erreur de validation',

                'errors' => $validator->errors()

            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {

            $distributor = Auth::guard('distributor')->user();

            if (! $distributor) {

                return response()->json([

                    'success' => false,

                    'status' => 401,

                    'message' => 'Non authentifié'

                ], 401);
            }

            $order = Order::with('items')->find($id);

            if (! $order) {

                return response()->json([

                    'success' => false,

                    'status' => 404,

                    'message' => 'Commande introuvable'

                ], 404);
            }

            if ($order->distributor_id !== $distributor->id) {

                return response()->json([

                    'success' => false,

                    'status' => 403,

                    'message' => 'Accès refusé'

                ], 403);
            }

            $order->items()->delete();

            $total = 0;

            $order->update([

                'delivery_address' => $validated['delivery_address'] ?? null,

                'order_date' => $validated['order_date'],
            ]);

            foreach ($validated['products'] as $item) {

                $product = Product::find($item['product_id']);

                if (! $product) {

                    DB::rollBack();

                    return response()->json([

                        'success' => false,

                        'status' => 404,

                        'message' => 'Produit introuvable',

                        'product_id' => $item['product_id']

                    ], 404);
                }

                $quantity = (int) $item['quantity'];

                $unitPrice = (float) $product->wholesale_price;

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

                'message' => 'Commande modifiée avec succès',

                'data' => $order

            ], 200);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'status' => 500,

                'message' => 'Erreur lors de la modification de la commande',

                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Erreur interne du serveur'

            ], 500);
        }
    }
}
