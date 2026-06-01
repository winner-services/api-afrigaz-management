<?php

namespace App\Http\Controllers\Mobile\Sales;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Branche;
use App\Models\Currency;
use App\Models\Sale;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
}
