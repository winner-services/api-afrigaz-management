<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Jobs\SendDistributorSmsJob;
use App\Models\About;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\CustomerDebtPayment;
use App\Models\DebtDistributor;
use App\Models\Distributor;
use App\Models\PaymentDistributor;
use App\Models\PaymentHistorie;
use App\Models\Sale;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PayementController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    #[OA\Post(
        path: '/api/v1/debtPaymentStore',
        summary: 'Créer',
        tags: ['Payment'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['customer_id', 'distributor_id', 'paid_amount', 'account_id', 'transaction_date', 'due_anount'],
                properties: [
                    new OA\Property(property: "customer_id", type: "integer", example: 1),
                    new OA\Property(property: "distributor_id", type: "integer", example: 1),
                    new OA\Property(property: "paid_amount", type: "number", format: "float", example: 100.00),
                    new OA\Property(property: "due_anount", type: "number", format: "float", example: 100.00),
                    new OA\Property(property: "account_id", type: "integer", example: 1),
                    new OA\Property(property: "transaction_date", type: "string", format: "date", example: "2023-01-01"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Données créées avec succès'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation des données échouée'
            ),
            new OA\Response(
                response: 500,
                description: 'Erreur serveur'
            )
        ]
    )]

    public function paymentDebt(Request $request)
    {
        $request->validate([
            'distributor_id'   => 'nullable|exists:distributors,id',
            'customer_id'      => 'nullable|exists:customers,id',
            'paid_amount'      => 'required|numeric|min:0.01',
            'account_id'       => 'required|exists:cash_accounts,id',
            'transaction_date' => 'nullable|date',
            'payment_method'   => 'nullable|string',
        ]);

        if (!$request->distributor_id && !$request->customer_id) {

            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Veuillez sélectionner un client ou un distributeur.'
            ], 422);
        }

        if ($request->distributor_id && $request->customer_id) {

            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Choisissez soit un client soit un distributeur.'
            ], 422);
        }

        DB::beginTransaction();

        try {


            $about = About::first();

            if ($about) {
                $this->imageService->transform($about, ['logo', 'logo2']);
            }

            $devise = Currency::where('status', 'created')
                ->orderByRaw("currency_type = 'devise_principale' DESC")
                ->latest()
                ->get();

            $remainingAmount = (float) $request->paid_amount;

            $totalPaid = 0;

            $operationDate = $request->transaction_date ?? now();

            $reference = fake()->unique()->numerify('PAY-#####');

            $lastTransaction = CashTransaction::where(
                'cash_account_id',
                $request->account_id
            )
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $currentSolde = $lastTransaction?->solde ?? 0;


            $customerId = null;

            $distributorId = null;

            $buyerName = null;


            if ($request->distributor_id) {

                $distributor = Distributor::lockForUpdate()->find(
                    $request->distributor_id
                );

                $debts = DebtDistributor::where(
                    'distributor_id',
                    $request->distributor_id
                )
                    ->where('remaining_amount', '>', 0)
                    ->orderBy('transaction_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $paymentModel = PaymentDistributor::class;

                $foreignKey = 'debt_distributor_id';

                $peopleKey = 'distributor_id';

                $cashCategory = 5;

                $paymentType = 'distributor_debt';

                $label = 'Paiement dette distributeur';

                $buyerName = $distributor?->name;

                $distributorId = $request->distributor_id;

                if ($distributor && $distributor->phone) {

                    SendDistributorSmsJob::dispatch(
                        $distributor->id,
                        'payment'
                    )->onQueue('sms');
                }
            } else {

                $customer = Customer::lockForUpdate()->find(
                    $request->customer_id
                );

                $debts = CustomerDebt::where(
                    'customer_id',
                    $request->customer_id
                )
                    ->where('remaining_amount', '>', 0)
                    ->orderBy('transaction_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $paymentModel = CustomerDebtPayment::class;

                $foreignKey = 'customer_debt_id';

                $peopleKey = 'customer_id';

                $cashCategory = 4;

                $paymentType = 'customer_debt';

                $label = 'Paiement dette client';

                $buyerName = $customer?->name;

                $customerId = $request->customer_id;
            }

            if ($debts->isEmpty()) {

                DB::rollBack();

                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'Aucune dette impayée trouvée.'
                ], 404);
            }


            $previousDebt = $debts->sum('remaining_amount');


            $peopleValue = $distributorId ?? $customerId;


            foreach ($debts as $debt) {

                if ($remainingAmount <= 0) {
                    break;
                }

                $debtRemaining = (float) $debt->remaining_amount;

                if ($debtRemaining <= 0) {
                    continue;
                }


                $payAmount = min(
                    $remainingAmount,
                    $debtRemaining
                );


                $payment = $paymentModel::create([

                    $foreignKey => $debt->id,

                    $peopleKey => $peopleValue,

                    'paid_amount' => $payAmount,

                    'cash_account_id' => $request->account_id,

                    'addedBy' => Auth::id(),

                    'operation_date' => $operationDate,

                    'reference' => $reference,
                ]);


                $debt->paid_amount += $payAmount;

                $debt->remaining_amount -= $payAmount;

                if ($debt->remaining_amount < 0) {
                    $debt->remaining_amount = 0;
                }

                $debt->status =
                    $debt->remaining_amount <= 0
                    ? 'paid'
                    : 'partial';

                $debt->save();

                if ($debt->sale_id) {

                    $sale = Sale::lockForUpdate()->find(
                        $debt->sale_id
                    );

                    if ($sale) {

                        $sale->paid_amount += $payAmount;

                        if (
                            $sale->paid_amount >
                            $sale->total_amount
                        ) {

                            $sale->paid_amount =
                                $sale->total_amount;
                        }

                        $saleRemaining = max(
                            0,
                            $sale->total_amount -
                                $sale->paid_amount
                        );

                        $sale->status =
                            $saleRemaining <= 0
                            ? 'paid'
                            : 'partial';

                        $sale->save();
                    }
                }

                $currentSolde += $payAmount;

                CashTransaction::create([

                    'reason' => $label,

                    'type' => 'Revenue',

                    'amount' => $payAmount,

                    'transaction_date' => $operationDate,

                    'solde' => $currentSolde,

                    'reference' => $reference,

                    'reference_id' => $debt->id,

                    'cash_account_id' => $request->account_id,

                    'cash_categorie_id' => $cashCategory,

                    'addedBy' => Auth::id()
                ]);

                PaymentHistorie::create([

                    'payment_type' => $paymentType,

                    'reference_id' => $payment->id,

                    'reference' => $reference,

                    'customer_id' => $customerId,

                    'distributor_id' => $distributorId,

                    'cash_account_id' => $request->account_id,

                    'paid_amount' => $payAmount,

                    'payment_method' =>
                    $request->payment_method ?? 'cash',

                    'payment_date' => $operationDate,

                    'addedBy' => Auth::id(),

                    'status' => 'paid',

                    'description' =>
                    $label .
                        ' - Dette #' .
                        $debt->id
                ]);

                $remainingAmount -= $payAmount;

                $totalPaid += $payAmount;
            }

            DB::commit();

            return response()->json([

                'success' => true,

                'status' => 200,

                'message' => 'Paiement effectué avec succès.',

                'data' => [

                    'reference' => $reference,

                    'buyer_name' => $buyerName,

                    'payer_type' =>
                    $request->distributor_id
                        ? 'distributor'
                        : 'customer',

                    'payer_id' =>
                    $request->distributor_id
                        ?? $request->customer_id,

                    'paid_amount' => $totalPaid,

                    'previous_debt' => $previousDebt,

                    'remaining_unallocated' => $remainingAmount,

                    'new_balance' => $currentSolde,

                    'transaction_date' => $operationDate,
                ],

                'info_company' => $about,

                'devise' => $devise

            ], 200);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'message' => 'Erreur lors du paiement.',

                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null

            ], 500);
        }
    }
}
