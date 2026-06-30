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
use App\Models\ProductLedger;
use App\Models\Sale;
use App\Models\StockByBranch;
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

            $about->point_vente = $branche ? $branche->name : null;
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
    // public function salesGetByBranche()
    // {
    //     $user = Auth::user();
    //     $branche = Branche::where('user_id', $user->id)->first();

    //     $brancheId = $branche ? $branche->id : null;
    //     $devise = Currency::where('status', 'created')
    //         ->orderByRaw("currency_type = 'devise_principale' DESC")
    //         ->latest()
    //         ->get();
    //     $about = About::first();
    //     if ($about) {
    //         $this->imageService->transform($about, ['logo', 'logo2']);
    //     }

    //     $branches = Branche::latest()->get();

    //     $search = request('q', null);
    //     $perPage = request('per_page', 10);

    //     $sales = Sale::with([
    //         'branch',
    //         'customer',
    //         'distributor',
    //         'user',
    //         'saleItems.product'
    //     ])
    //         ->when($search, function ($query) use ($search) {

    //             $query->where(function ($q) use ($search) {

    //                 $q->where('reference', 'like', "%$search%");

    //                 $q->orWhereHas('customer', function ($q2) use ($search) {
    //                     $q2->where('name', 'like', "%$search%");
    //                 });

    //                 $q->orWhereHas('distributor', function ($q3) use ($search) {
    //                     $q3->where('name', 'like', "%$search%");
    //                 });

    //                 $q->orWhereHas('saleItems.product', function ($q4) use ($search) {
    //                     $q4->where('name', 'like', "%$search%");
    //                 });

    //                 $q->orWhereDate('transaction_date', $search);
    //             });
    //         })
    //         ->where('branch_id', $brancheId)
    //         ->orderBy('sales.id', 'desc')
    //         ->paginate($perPage);

    //     return response()->json([
    //         'status' => 200,
    //         'devise' => $devise,
    //         'branches' => $branches,
    //         'info_company' => $about,
    //         'data' => $sales
    //     ]);
    // }

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
                        $price = $untPrc / $product->weight_kg;

                        $unitPrice = $price * $product->weight_kg;
                        $lineTotal = $unitPrice * $qty;
                        $totalGas += $gasQty;
                    } elseif ($categoryId >= 3) {
                        $unitPrice = $untPrc;
                        if ($unitPrice <= 0) {
                            throw new \Exception("Prix non défini pour {$product->name}");
                        }
                        $lineTotal = $unitPrice * $qty;
                    } else {
                        throw new \Exception("Le produit {$product->name} n'est pas autorisé sur une vente d'échange.");
                    }

                    $total += $lineTotal;

                    // --- NOUVELLE GESTION DU STOCK CONDITIONNELLE ---
                    if ($type === 'exchange') {

                        // 1. Logique exclusive pour le type 'exchange' (Bouteilles Pleines/Vides)
                        if ($categoryId !== 2) {
                            throw new \Exception("Seules les bouteilles (catégorie 2) sont autorisées pour un échange.");
                        }

                        $stockDecrease = StockByBranch::where([
                            'branche_id' => $branchId,
                            'product_id' => $product->id,
                            'is_empty' => 0,
                            'condition_state' => 'good'
                        ])->lockForUpdate()->first();

                        if (!$stockDecrease) {
                            throw new \Exception("Stock bouteilles pleines introuvable pour produit ID: {$product->id}");
                        }

                        if ($stockDecrease->stock_quantity < $qty) {
                            throw new \Exception("Stock insuffisant pour produit ID: {$product->id}");
                        }

                        $decBefore = $stockDecrease->stock_quantity;
                        $decAfter = $decBefore - $qty;

                        $stockDecrease->update(['stock_quantity' => $decAfter]);

                        ProductLedger::create([
                            'product_id' => $product->id,
                            'branch_id' => $branchId,
                            'operation_date' => $data['date_vente'] ?? now(),
                            'type' => 'sale',
                            'quantity' => $qty,
                            'stock_before' => $decBefore,
                            'stock_after' => $decAfter,
                            'movement' => 'out',
                            'reference_type' => 'exchange',
                            'reference_id' => $sale->id,
                            'notes' => 'Sortie bouteilles pleines',
                            'addedBy' => Auth::id() ?? 1,
                            'status' => 'posted',
                        ]);

                        $stockIncrease = StockByBranch::firstOrCreate([
                            'branche_id' => $branchId,
                            'product_id' => $product->id,
                            'is_empty' => 1,
                            'condition_state' => 'good'
                        ], [
                            'stock_quantity' => 0,
                            'status' => 'created'
                        ]);

                        $incBefore = $stockIncrease->stock_quantity;
                        $incAfter = $incBefore + $qty;

                        $stockIncrease->update(['stock_quantity' => $incAfter]);

                        ProductLedger::create([
                            'product_id' => $product->id,
                            'branch_id' => $branchId,
                            'operation_date' => $data['date_vente'] ?? now(),
                            'type' => 'exchange_in',
                            'quantity' => $qty,
                            'movement' => 'in',
                            'stock_before' => $incBefore,
                            'stock_after' => $incAfter,
                            'reference_type' => 'exchange',
                            'reference_id' => $sale->id,
                            'notes' => 'Retour bouteilles vides (échange)',
                            'addedBy' => Auth::id() ?? 1,
                            'status' => 'posted',
                        ]);
                    } else {

                        // 2. Si le type n'est PAS 'exchange' (donc kit, refill, accessory, etc.)
                        app(StockService::class)->decreaseKitStock(
                            $branchId,
                            $product->id,
                            $qty,
                            $type, // On passe dynamiquement le type actuel (ex: 'kit')
                            $sale->id,
                            $data['date_vente']
                        );
                    }
                    // --- FIN DE LA GESTION DU STOCK ---

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
                            ['sale_id' => $sale->id],
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
                            ['sale_id' => $sale->id],
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
                    throw new \Exception("Le montant payé ({$paidAmount}) ne peut pas être supérieur au total ({$total}).");
                }

                if ($paidAmount > 0) {
                    $last = CashTransaction::where('cash_account_id', $data['account_id'])
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                    $solde = ($last->solde ?? 0) + $paidAmount;
                    $paymentType = 'sale';
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

                    $paymentMethod = match (true) {
                        $paidAmount <= 0 => 'credit',
                        $paidAmount < $sale->total_amount => 'partie',
                        default => 'cash',
                    };

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
                        'description' => $label . ' - Dette #' . $sale->id
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
                'reference' => $sale->reference,
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
    //             $user = Auth::user();
    //             $branche = Branche::where('user_id', $user->id)->first();
    //             $branchId = $branche?->id ?? 1;
    //             $type = $data['type'];
    //             $items = $data['items'];
    //             $paidAmount = (float) $data['paid_amount'];

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

    //             $total = 0;
    //             $totalGas = 0;

    //             foreach ($items as $item) {

    //                 $product = Product::findOrFail($item['product_id']);
    //                 $categoryId = (int) $product->category_id;
    //                 $qty = (int) $item['quantity'];
    //                 $untPrc = (float) $item['unit_price'];

    //                 if ($qty <= 0) {
    //                     throw new \Exception("Quantité invalide pour {$product->name}");
    //                 }

    //                 $unitPrice = 0;
    //                 $lineTotal = 0;

    //                 if ($categoryId === 2) {
    //                     if (!$product->weight_kg) {
    //                         throw new \Exception("Poids non défini pour {$product->name}");
    //                     }
    //                     $gasQty = $product->weight_kg * $qty;
    //                     $price = $untPrc / $product->weight_kg;

    //                     $unitPrice = $price * $product->weight_kg;
    //                     $lineTotal = $unitPrice * $qty;
    //                     $totalGas += $gasQty;
    //                 } elseif ($categoryId >= 3) {
    //                     $unitPrice = $untPrc;
    //                     if ($unitPrice <= 0) {
    //                         throw new \Exception("Prix non défini pour {$product->name}");
    //                     }
    //                     $lineTotal = $unitPrice * $qty;
    //                 } else {
    //                     throw new \Exception("Le produit {$product->name} n'est pas autorisé sur une vente d'échange.");
    //                 }

    //                 $total += $lineTotal;

    //                 if ($categoryId === 2) {

    //                     $stockDecrease = StockByBranch::where([
    //                         'branche_id' => $branchId,
    //                         'product_id' => $product->id,
    //                         'is_empty' => 0,
    //                         'condition_state' => 'good'
    //                     ])->lockForUpdate()->first();

    //                     if (!$stockDecrease) {
    //                         throw new \Exception("Stock bouteilles pleines introuvable pour produit ID: {$product->id}");
    //                     }

    //                     if ($stockDecrease->stock_quantity < $qty) {
    //                         throw new \Exception("Stock insuffisant pour produit ID: {$product->id}");
    //                     }

    //                     $decBefore = $stockDecrease->stock_quantity;
    //                     $decAfter = $decBefore - $qty;

    //                     $stockDecrease->update(['stock_quantity' => $decAfter]);

    //                     ProductLedger::create([
    //                         'product_id' => $product->id,
    //                         'branch_id' => $branchId,
    //                         'operation_date' => $data['date_vente'] ?? now(),
    //                         'type' => 'sale',
    //                         'quantity' => $qty,
    //                         'stock_before' => $decBefore,
    //                         'stock_after' => $decAfter,
    //                         'movement' => 'out',
    //                         'reference_type' => 'exchange',
    //                         'reference_id' => $sale->id,
    //                         'notes' => 'Sortie bouteilles pleines',
    //                         'addedBy' => Auth::id() ?? 1,
    //                         'status' => 'posted',
    //                     ]);

    //                     $stockIncrease = StockByBranch::firstOrCreate([
    //                         'branche_id' => $branchId,
    //                         'product_id' => $product->id,
    //                         'is_empty' => 1,
    //                         'condition_state' => 'good'
    //                     ], [
    //                         'stock_quantity' => 0,
    //                         'status' => 'created'
    //                     ]);

    //                     $incBefore = $stockIncrease->stock_quantity;
    //                     $incAfter = $incBefore + $qty;

    //                     $stockIncrease->update(['stock_quantity' => $incAfter]);

    //                     ProductLedger::create([
    //                         'product_id' => $product->id,
    //                         'branch_id' => $branchId,
    //                         'operation_date' => $data['date_vente'] ?? now(),
    //                         'type' => 'exchange_in',
    //                         'quantity' => $qty,
    //                         'movement' => 'in',
    //                         'stock_before' => $incBefore,
    //                         'stock_after' => $incAfter,
    //                         'reference_type' => 'exchange',
    //                         'reference_id' => $sale->id,
    //                         'notes' => 'Retour bouteilles vides (échange)',
    //                         'addedBy' => Auth::id() ?? 1,
    //                         'status' => 'posted',
    //                     ]);
    //                 } else {

    //                     $stockKit = StockByBranch::where([
    //                         'branche_id' => $branchId,
    //                         'product_id' => $product->id
    //                     ])->lockForUpdate()->first();

    //                     if (!$stockKit) {
    //                         throw new \Exception("Stock introuvable pour produit ID: {$product->id}");
    //                     }

    //                     if ($stockKit->stock_quantity < $qty) {
    //                         throw new \Exception("Stock insuffisant pour produit ID: {$product->id}");
    //                     }

    //                     $kitBefore = $stockKit->stock_quantity;
    //                     $kitAfter = $kitBefore - $qty;

    //                     $stockKit->update(['stock_quantity' => $kitAfter]);

    //                     ProductLedger::create([
    //                         'product_id' => $product->id,
    //                         'branch_id' => $branchId,
    //                         'operation_date' => $data['date_vente'] ?? now(),
    //                         'type' => 'sale',
    //                         'quantity' => $qty,
    //                         'stock_before' => $kitBefore,
    //                         'stock_after' => $kitAfter,
    //                         'movement' => 'out',
    //                         'reference_type' => 'accessory',
    //                         'reference_id' => $sale->id,
    //                         'notes' => 'Sortie kit (accessoires)',
    //                         'addedBy' => Auth::id() ?? 1,
    //                         'status' => 'posted',
    //                     ]);
    //                 }

    //                 ItemSale::create([
    //                     'sale_id' => $sale->id,
    //                     'product_id' => $product->id,
    //                     'quantity' => $qty,
    //                     'unit_price' => $unitPrice,
    //                     'total_price' => $lineTotal
    //                 ]);
    //             }

    //             $status = match (true) {
    //                 $paidAmount <= 0 => 'pending',
    //                 $paidAmount < $total => 'partial',
    //                 default => 'completed',
    //             };

    //             $sale->update([
    //                 'total_amount' => $total,
    //                 'status' => $status
    //             ]);

    //             $remaining = $total - $paidAmount;

    //             if ($remaining > 0) {
    //                 if ($distributorId) {
    //                     DebtDistributor::updateOrCreate(
    //                         ['sale_id' => $sale->id],
    //                         [
    //                             'distributor_id' => $distributorId,
    //                             'loan_amount' => $total,
    //                             'remaining_amount' => $remaining,
    //                             'paid_amount' => $paidAmount,
    //                             'motif' => 'Dette Vente #' . $sale->id,
    //                             'reference' => $sale->reference,
    //                             'transaction_date' => $data['date_vente'],
    //                             'status' => $status,
    //                             'date_echeance' => $data['date_echeance'] ?? null,
    //                             'user_id' => Auth::id(),
    //                         ]
    //                     );
    //                 }

    //                 if ($customerId) {
    //                     CustomerDebt::updateOrCreate(
    //                         ['sale_id' => $sale->id],
    //                         [
    //                             'customer_id' => $customerId,
    //                             'loan_amount' => $total,
    //                             'remaining_amount' => $remaining,
    //                             'paid_amount' => $paidAmount,
    //                             'transaction_date' => $data['date_vente'],
    //                             'motif' => 'Dette Vente #' . $sale->id,
    //                             'status' => $status,
    //                             'date_echeance' => $data['date_echeance'] ?? null,
    //                             'user_id' => Auth::id(),
    //                         ]
    //                     );
    //                 }
    //             }

    //             if ($paidAmount > $total) {
    //                 throw new \Exception("Le montant payé ({$paidAmount}) ne peut pas être supérieur au total ({$total}).");
    //             }

    //             if ($paidAmount > 0) {
    //                 $last = CashTransaction::where('cash_account_id', $data['account_id'])
    //                     ->latest('id')
    //                     ->lockForUpdate()
    //                     ->first();

    //                 $solde = ($last->solde ?? 0) + $paidAmount;
    //                 $paymentType = 'sale';
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

    //                 $paymentMethod = match (true) {
    //                     $paidAmount <= 0 => 'credit',
    //                     $paidAmount < $sale->total_amount => 'partie',
    //                     default => 'cash',
    //                 };

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
    //                     'description' => $label . ' - Dette #' . $sale->id
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
    //             'reference' => $sale->reference,
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
