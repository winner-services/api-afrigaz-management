<?php

namespace App\Http\Controllers\Mobile\Transct;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Branche;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function accountGetOptionsDataMobile()
    {
        try {

            $branche = Branche::where('user_id', Auth::id())->first();

            $devise = Currency::where('status', 'created')
                ->orderByRaw("currency_type = 'devise_principale' DESC")
                ->latest()
                ->get();
            $about = About::first();
            if ($about) {
                $this->imageService->transform($about, ['logo', 'logo2']);
            }


            $brancheId = $branche->id;

            $accountId = CashAccount::where('branche_id', $brancheId)->value('id');

            dd(
                CashTransaction::where('cash_account_id', $accountId)->count()
            );

            $perPage = request('per_page', 10);
            $search = request('q', '');
            $sortField = request('sort_field', 'id');
            $sortDirection = request('sort_direction', 'desc');

            $allowedSortFields = ['id', 'amount', 'transaction_date', 'type'];
            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'id';
            }

            $query = CashTransaction::query()
                ->with(['account:id,designation,branche_id', 'addedBy:id,name'])
                ->where('cash_account_id', $accountId);

            if ($brancheId) {
                $query->whereHas('account', function ($q) use ($brancheId) {
                    $q->where('branche_id', $brancheId);
                });
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'LIKE', "%$search%")
                        ->orWhere('reference', 'LIKE', "%$search%")
                        ->orWhere('type', 'LIKE', "%$search%");
                });
            }

            $transactions = $query->orderBy($sortField, $sortDirection)
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'info_company' => $about,
                'data' => $transactions,
                'devise' => $devise
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
