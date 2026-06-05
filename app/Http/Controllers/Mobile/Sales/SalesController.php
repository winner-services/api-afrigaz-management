<?php

namespace App\Http\Controllers\Mobile\Sales;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Branche;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\CustomerDebt;
use App\Models\DebtDistributor;
use App\Models\ItemSale;
use App\Models\PaymentDistributor;
use App\Models\PaymentHistorie;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ImageService;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function salesGetByBranche()
    {
        $user = Auth::user();
        $branche = Branche::where('user_id', $user->id)->first();

        $brancheId = $branche ? $branche->id : null;
        $devise = Currency::where('status', 'created')
            ->orderByRaw("currency_type = 'devise_principale' DESC")
            ->latest()
            ->get();
        $about = About::first();
        if ($about) {
            $this->imageService->transform($about, ['logo', 'logo2']);
        }

        $branches = Branche::latest()->get();

        $search = request('q', null);
        $perPage = request('per_page', 10);

        $sales = Sale::with([
            'branch',
            'customer',
            'distributor',
            'user',
            'saleItems.product'
        ])
            ->when($search, function ($query) use ($search) {

                $query->where(function ($q) use ($search) {

                    $q->where('reference', 'like', "%$search%");

                    $q->orWhereHas('customer', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%$search%");
                    });

                    $q->orWhereHas('distributor', function ($q3) use ($search) {
                        $q3->where('name', 'like', "%$search%");
                    });

                    $q->orWhereHas('saleItems.product', function ($q4) use ($search) {
                        $q4->where('name', 'like', "%$search%");
                    });

                    $q->orWhereDate('transaction_date', $search);
                });
            })
            ->where('branch_id', $brancheId)
            ->orderBy('sales.id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 200,
            'devise' => $devise,
            'branches' => $branches,
            'info_company' => $about,
            'data' => $sales
        ]);
    }
    public function processSaleMobile(Request $request)
    {
        try {
            $about = About::first();
            if ($about) {
                $this->imageService->transform($about, ['logo', 'logo2']);
            }

            $devise = Currency::where('status', 'created')
                ->orderByRaw("currency_type = 'devise_principale' DESC")
                ->latest()
                ->get();

            $data = $request->validate([
                'montant_total' => 'nullable|numeric|min:0',
                'paid_amount' => 'nullable|numeric|min:0',
                'account_id' => 'nullable|exists:cash_accounts,id',
                'customer_id' => 'nullable|exists:customers,id|required_without:distributor_id|prohibits:distributor_id',
                'distributor_id' => 'nullable|exists:distributors,id|required_without:customer_id|prohibits:customer_id',
                'date_vente' => 'required|date',
                'date_echeance' => 'nullable|date',
                'reference_paiement' => 'nullable|string|max:255',
                'branch_id' => 'nullable|exists:branches,id',
                'type' => 'required|in:exchange,kit,refill,accessory',
                'tank_id' => 'nullable|exists:tanks,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0'
            ]);

            $sale = DB::transaction(function () use ($data) {

                $customerId = $data['customer_id'] ?? null;
                $distributorId = $data['distributor_id'] ?? null;

                $user = Auth::user();

                $branche = Branche::where('user_id', $user->id)->first();

                $branchId = $branche?->id ?? 1;

                $type = $data['type'];
                $items = $data['items'];

                $paidAmount = (float) $data['paid_amount'];

                $gasProduct = Product::where('category_id', 1)->first();

                if (in_array($type, ['refill', 'exchange']) && !$gasProduct) {
                    throw new \Exception("Produit gaz introuvable");
                }

                $sale = Sale::create([
                    'reference' => fake()->unique()->numerify('VENTE-#####'),
                    'customer_id' => $customerId,
                    'distributor_id' => $distributorId,
                    'branch_id' => $branchId,
                    'sale_type' => $type,
                    'total_amount' => 0,
                    'paid_amount' => $paidAmount,
                    'addedBy' => Auth::id(),
                    'transaction_date' => $data['date_vente'],
                    'status' => 'pending',
                ]);

                $total = 0;
                $totalGas = 0;

                foreach ($items as $item) {

                    $product = Product::findOrFail($item['product_id']);

                    $categoryId = (int) $product->category_id;
                    $qty = (int) $item['quantity'];
                    $untPrc = (float) $item['unit_price'];

                    if ($qty <= 0) {
                        throw new \Exception("Quantité invalide pour {$product->name}");
                    }

                    $unitPrice = 0;
                    $lineTotal = 0;

                    if ($categoryId === 2) {

                        if (!$product->weight_kg) {
                            throw new \Exception("Poids non défini pour {$product->name}");
                        }

                        $gasQty = $product->weight_kg * $qty;

                        $unitPrice = $gasProduct->wholesale_price * $product->weight_kg;

                        $lineTotal = $unitPrice * $qty;

                        $totalGas += $gasQty;
                    } elseif ($categoryId >= 3) {

                        $unitPrice = $untPrc;

                        if ($unitPrice <= 0) {
                            throw new \Exception("Prix non défini pour {$product->name}");
                        }

                        $lineTotal = $unitPrice * $qty;
                    } else {

                        throw new \Exception(
                            "Le produit {$product->name} n'est pas autorisé sur une vente d'échange."
                        );
                    }

                    $total += $lineTotal;

                    if ($categoryId === 2) {

                        app(StockService::class)->decreaseExchangeStock(
                            $branchId,
                            $product->id,
                            $qty,
                            'exchange',
                            $sale->id,
                            $data['date_vente']
                        );

                        app(StockService::class)->increaseStockExchange(
                            $branchId,
                            $product->id,
                            $qty,
                            'exchange',
                            $sale->id,
                            $data['date_vente']
                        );
                    } else {

                        app(StockService::class)->decreaseKitStock(
                            $branchId,
                            $product->id,
                            $qty,
                            'accessory',
                            $sale->id,
                            $data['date_vente']
                        );
                    }

                    ItemSale::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'total_price' => $lineTotal
                    ]);
                }

                $status = match (true) {
                    $paidAmount <= 0 => 'pending',
                    $paidAmount < $total => 'partial',
                    default => 'completed',
                };

                $sale->update([
                    'total_amount' => $total,
                    'status' => $status
                ]);
                $remaining = $total - $paidAmount;

                if ($remaining > 0) {

                    if ($distributorId) {

                        DebtDistributor::updateOrCreate(
                            [
                                'sale_id' => $sale->id,
                            ],
                            [
                                'distributor_id' => $distributorId,
                                'loan_amount' => $total,
                                'remaining_amount' => $remaining,
                                'paid_amount' => $paidAmount,
                                'motif' => 'Dette Vente #' . $sale->id,
                                'reference' => $sale->reference,
                                'transaction_date' => $data['date_vente'],
                                'status' => $status,
                                'date_echeance' => $data['date_echeance'] ?? null,
                                'user_id' => Auth::id(),
                            ]
                        );
                    }

                    if ($customerId) {

                        CustomerDebt::updateOrCreate(
                            [
                                'sale_id' => $sale->id,
                            ],
                            [
                                'customer_id' => $customerId,
                                'loan_amount' => $total,
                                'remaining_amount' => $remaining,
                                'paid_amount' => $paidAmount,
                                'transaction_date' => $data['date_vente'],
                                'motif' => 'Dette Vente #' . $sale->id,
                                'status' => $status,
                                'date_echeance' => $data['date_echeance'] ?? null,
                                'user_id' => Auth::id(),
                            ]
                        );
                    }
                }
                if ($paidAmount > $total) {
                    throw new \Exception(
                        "Le montant payé ({$paidAmount}) ne peut pas être supérieur au total ({$total})."
                    );
                }

                if ($paidAmount > 0) {

                    $last = CashTransaction::where('cash_account_id', $data['account_id'])
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                    $solde = ($last->solde ?? 0) + $paidAmount;
                    $paymentType = 'sale';
                    $reference = $customerId ? 'CUST-' . $customerId : 'DIST-' . $distributorId;
                    $label = $customerId ? 'Paiement vente' : 'Paiement Vente';

                    CashTransaction::create([
                        'reason' => 'Paiement vente #' . $sale->id,
                        'type' => 'Revenue',
                        'amount' => $paidAmount,
                        'transaction_date' => now(),
                        'solde' => $solde,
                        'reference' => 'SALE-' . $sale->reference,
                        'reference_id' => $sale->id,
                        'cash_account_id' => $data['account_id'],
                        'reference_paiement' => $data['reference_paiement'],
                        'cash_categorie_id' => 4,
                        'addedBy' => Auth::id()
                    ]);

                    $paymentMethod = 'cash';

                    if ($paidAmount <= 0) {
                        $paymentMethod = 'credit';
                    } elseif ($paidAmount < $sale->total_amount) {
                        $paymentMethod = 'partie';
                    } else {
                        $paymentMethod = 'cash';
                    }

                    PaymentHistorie::create([

                        'payment_type' => $paymentType,

                        'reference_id' => $sale->id,

                        'reference' => $sale->reference,

                        'customer_id' => $customerId,

                        'distributor_id' => $distributorId,

                        'cash_account_id' => $data['account_id'],

                        'paid_amount' => $paidAmount,

                        'payment_method' => $paymentMethod,

                        'payment_date' => $data['date_echeance'] ?? now(),

                        'reference_paiement' => $data['reference_paiement'],

                        'addedBy' => Auth::id(),

                        'status' => 'paid',

                        'description' =>
                        $label .
                            ' - Dette #' .
                            $sale->id
                    ]);

                    if ($distributorId) {
                        PaymentDistributor::create([
                            'distributor_id' => $distributorId,
                            'paid_amount' => $paidAmount,
                            'cash_account_id' => $data['account_id'],
                            'operation_date' => now(),
                            'reference' => $sale->reference,
                            'addedBy' => Auth::id()
                        ]);
                    }
                }

                return $sale->load([
                    'items.product',
                    'customer:id,name',
                    'distributor:id,name'
                ]);
            });
            $buyerName = $sale->customer->name ?? $sale->distributor->name ?? null;
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Vente enregistrée avec succès',
                'data' => [
                    ...$sale->toArray(),
                    'buyer_name' => $buyerName
                ],
                'info_company' => $about,
                'point_vente' => Branche::where('user_id', Auth::id())->value('name'),
                'devise' => $devise
            ], 201);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // public function processSaleMobile(Request $request)
    // {
    //     try {
    //         $about = About::first();
    //         if ($about) {
    //             $this->imageService->transform($about, ['logo', 'logo2']);
    //         }

    //         $devise = Currency::where('status', 'created')
    //             ->orderByRaw("currency_type = 'devise_principale' DESC")
    //             ->latest()
    //             ->get();

    //         $data = $request->validate([
    //             'montant_total' => 'nullable|numeric|min:0',
    //             'paid_amount' => 'nullable|numeric|min:0',
    //             'account_id' => 'nullable|exists:cash_accounts,id',
    //             'customer_id' => 'nullable|exists:customers,id|required_without:distributor_id|prohibits:distributor_id',
    //             'distributor_id' => 'nullable|exists:distributors,id|required_without:customer_id|prohibits:customer_id',
    //             'date_vente' => 'required|date',
    //             'date_echeance' => 'nullable|date',
    //             'reference_paiement' => 'nullable|string|max:255',
    //             'branch_id' => 'nullable|exists:branches,id',
    //             'type' => 'required|in:exchange,kit,refill,accessory',
    //             'tank_id' => 'nullable|exists:tanks,id',
    //             'items' => 'required|array|min:1',
    //             'items.*.product_id' => 'required|exists:products,id',
    //             'items.*.quantity' => 'required|integer|min:1',
    //             'items.*.unit_price' => 'required|numeric|min:0'
    //         ]);

    //         $sale = DB::transaction(function () use ($data) {

    //             $customerId = $data['customer_id'] ?? null;
    //             $distributorId = $data['distributor_id'] ?? null;

    //             $user1 = Auth::user();
    //             $branche = Branche::where('user_id', $user1->id)->first();

    //             $branchId = $branche?->id ?? 1;

    //             $type = $data['type'];
    //             $items = $data['items'];

    //             $totalAmount = (float) $data['montant_total'];
    //             $paidAmount = (float) $data['paid_amount'];

    //             $total = 0;
    //             $totalGas = 0;

    //             $gasProduct = Product::where('category_id', 1)->first();

    //             if (in_array($type, ['refill', 'exchange']) && !$gasProduct) {
    //                 throw new \Exception("Produit gaz introuvable");
    //             }

    //             $sale = Sale::create([
    //                 'reference' => fake()->unique()->numerify('VENTE-#####'),
    //                 'customer_id' => $customerId,
    //                 'distributor_id' => $distributorId,
    //                 'branch_id' => $branchId,
    //                 'sale_type' => $type,
    //                 'total_amount' => 0,
    //                 'paid_amount' => $paidAmount,
    //                 'addedBy' => Auth::id(),
    //                 'transaction_date' => $data['date_vente'],
    //                 'status' => 'pending',
    //             ]);

    //             $remaining = $totalAmount - $paidAmount;

    //             $sale->status = match (true) {
    //                 $paidAmount == 0 => 'pending',
    //                 $paidAmount < $totalAmount => 'partial',
    //                 default => 'completed',
    //             };

    //             $sale->save();

    //             if ($remaining > 0) {

    //                 if ($distributorId) {
    //                     DebtDistributor::updateOrCreate(
    //                         [
    //                             'sale_id' => $sale->id,
    //                         ],
    //                         [
    //                             'distributor_id' => $distributorId,
    //                             'loan_amount' => $totalAmount,
    //                             'remaining_amount' => $remaining,
    //                             'paid_amount' => $paidAmount,
    //                             'motif' => 'Dette Vente #' . $sale->id,
    //                             'reference' => $sale->reference,
    //                             'transaction_date' => now(),
    //                             'status' => $sale->status,
    //                             'date_echeance' => $data['date_echeance'] ?? null,
    //                             'user_id' => Auth::id(),
    //                         ]
    //                     );
    //                 }

    //                 if ($customerId) {
    //                     CustomerDebt::updateOrCreate(
    //                         [
    //                             'sale_id' => $sale->id,
    //                         ],
    //                         [
    //                             'customer_id' => $customerId,
    //                             'loan_amount' => $totalAmount,
    //                             'remaining_amount' => $remaining,
    //                             'paid_amount' => $paidAmount,
    //                             'transaction_date' => now(),
    //                             'motif' => 'Dette Vente #' . $sale->id,
    //                             'status' => $sale->status,
    //                             'date_echeance' => $data['date_echeance'] ?? null,
    //                             'user_id' => Auth::id(),
    //                         ]
    //                     );
    //                 }
    //             }

    //             if ($paidAmount > 0) {

    //                 $last = CashTransaction::where('cash_account_id', $data['account_id'])
    //                     ->latest('id')
    //                     ->lockForUpdate()
    //                     ->first();

    //                 $solde = ($last->solde ?? 0) + $paidAmount;
    //                 $paymentType = 'sale';
    //                 $reference = $customerId ? 'CUST-' . $customerId : 'DIST-' . $distributorId;
    //                 $label = $customerId ? 'Paiement vente' : 'Paiement Vente';

    //                 CashTransaction::create([
    //                     'reason' => 'Paiement vente #' . $sale->id,
    //                     'type' => 'Revenue',
    //                     'amount' => $paidAmount,
    //                     'transaction_date' => now(),
    //                     'solde' => $solde,
    //                     'reference' => 'SALE-' . $sale->reference,
    //                     'reference_id' => $sale->id,
    //                     'cash_account_id' => $data['account_id'],
    //                     'reference_paiement' => $data['reference_paiement'],
    //                     'cash_categorie_id' => 4,
    //                     'addedBy' => Auth::id()
    //                 ]);

    //                 $paymentMethod = 'cash';

    //                 if ($paidAmount == 0) {
    //                     $paymentMethod = 'credit';
    //                 } elseif ($paidAmount < $sale->montant_total) {
    //                     $paymentMethod = 'partie';
    //                 } elseif ($paidAmount >= $sale->montant_total) {
    //                     $paymentMethod = 'cash';
    //                 }

    //                 PaymentHistorie::create([

    //                     'payment_type' => $paymentType,

    //                     'reference_id' => $sale->id,

    //                     'reference' => $sale->reference,

    //                     'customer_id' => $customerId,

    //                     'distributor_id' => $distributorId,

    //                     'cash_account_id' => $data['account_id'],

    //                     'paid_amount' => $paidAmount,

    //                     'payment_method' => $paymentMethod,

    //                     'payment_date' => $data['date_echeance'] ?? now(),

    //                     'reference_paiement' => $data['reference_paiement'],

    //                     'addedBy' => Auth::id(),

    //                     'status' => 'paid',

    //                     'description' =>
    //                     $label .
    //                         ' - Dette #' .
    //                         $sale->id
    //                 ]);

    //                 if ($distributorId) {
    //                     PaymentDistributor::create([
    //                         'distributor_id' => $distributorId,
    //                         'paid_amount' => $paidAmount,
    //                         'cash_account_id' => $data['account_id'],
    //                         'operation_date' => now(),
    //                         'reference' => $sale->reference,
    //                         'addedBy' => Auth::id()
    //                     ]);
    //                 }
    //             }

    //             foreach ($items as $item) {

    //                 $product = Product::findOrFail($item['product_id']);
    //                 $categoryId = $product->category_id;
    //                 $qty = (int) $item['quantity'];
    //                 $untPrc = (float) $item['unit_price'];

    //                 $unitPrice = 0;
    //                 $lineTotal = 0;

    //                 if ($type === 'refill' || $type === 'exchange') {
    //                     if ($categoryId === 2) {
    //                         if (!$product->weight_kg) {
    //                             throw new \Exception("Poids non défini pour {$product->name}");
    //                         }

    //                         $gasQty = $product->weight_kg * $qty;
    //                         $price = $gasProduct->wholesale_price;
    //                         $lineTotal = $price * $gasQty;
    //                         $unitPrice = $price * $product->weight_kg;
    //                         $totalGas += $gasQty;
    //                     } else {
    //                         $unitPrice += $untPrc;
    //                         if ($unitPrice <= 0) {
    //                             throw new \Exception("Prix non défini pour {$product->name}");
    //                         }

    //                         $lineTotal = $unitPrice * $qty;
    //                     }
    //                 } else {

    //                     $unitPrice += $untPrc;
    //                     if ($unitPrice <= 0) {
    //                         throw new \Exception("Prix non défini pour {$product->name}");
    //                     }

    //                     $lineTotal = $unitPrice * $qty;
    //                 }

    //                 $total += $lineTotal;

    //                 if ($type === 'exchange') {
    //                     if ($categoryId === 2) {
    //                         app(StockService::class)->decreaseExchangeStock(
    //                             $branchId,
    //                             $product->id,
    //                             $qty,
    //                             'exchange',
    //                             $sale->id ?? null,
    //                             $data['date_vente'],
    //                         );

    //                         app(StockService::class)->increaseStockExchange(
    //                             $branchId,
    //                             $product->id,
    //                             $qty,
    //                             'exchange',
    //                             $sale->id ?? null,
    //                             $data['date_vente']
    //                         );
    //                     } elseif ($categoryId === 3) {
    //                         app(StockService::class)->decreaseKitStock(
    //                             $branchId,
    //                             $product->id,
    //                             $qty,
    //                             'kit',
    //                             $sale->id,
    //                             $data['date_vente']
    //                         );
    //                     }
    //                     app(StockService::class)->decreaseExchangeStock(
    //                         $branchId,
    //                         $product->id,
    //                         $qty,
    //                         'exchange',
    //                         $sale->id ?? null,
    //                         $data['date_vente'],
    //                     );

    //                     app(StockService::class)->increaseStockExchange(
    //                         $branchId,
    //                         $product->id,
    //                         $qty,
    //                         'exchange',
    //                         $sale->id ?? null,
    //                         $data['date_vente']
    //                     );
    //                 }
    //                 ItemSale::create([
    //                     'sale_id' => $sale->id,
    //                     'product_id' => $product->id,
    //                     'quantity' => $qty,
    //                     'unit_price' => $unitPrice,
    //                     'total_price' => $lineTotal
    //                 ]);
    //             }

    //             $sale->update([
    //                 'total_amount' => $total
    //             ]);

    //             return $sale->load([
    //                 'items.product',
    //                 'customer:id,name',
    //                 'distributor:id,name'
    //             ]);
    //         });
    //         $buyerName = $sale->customer->name ?? $sale->distributor->name ?? null;
    //         return response()->json([
    //             'success' => true,
    //             'status' => 201,
    //             'message' => 'Vente enregistrée avec succès',
    //             'data' => [
    //                 ...$sale->toArray(),
    //                 'buyer_name' => $buyerName
    //             ],
    //             'info_company' => $about,
    //             'point_vente' => Branche::where('user_id', Auth::id())->value('name'),
    //             'devise' => $devise
    //         ], 201);
    //     } catch (\Throwable $e) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Une erreur est survenue',
    //             'error' => config('app.debug') ? $e->getMessage() : null
    //         ], 500);
    //     }
    // }
}
