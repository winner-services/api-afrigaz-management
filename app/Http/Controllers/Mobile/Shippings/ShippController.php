<?php

namespace App\Http\Controllers\Mobile\Shippings;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Branche;
use App\Models\Shipping;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShippController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function shippingByBranchGetMobile(Request $request)
    {
        $about = About::first();
        if ($about) {
            $this->imageService->transform($about, ['logo', 'logo2']);
        }
        $branches = Branche::latest()->get();
        $user = Auth::user();
        $branche = Branche::where('user_id', Auth::id())->first();

        $brancheId = $branche->id;

        $perPage = $request->query('per_page', 20);
        $search = request('q', '');

        $sales = Shipping::with(['branch', 'distributor', 'user', 'items.product'])
            ->where('branch_id', $brancheId)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('distributor', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                        ->orWhereHas('user', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.product', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        })
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'status' => 200,
            'branches' => $branches,
            'info_company' => $about,
            'data' => $sales
        ]);
    }
}
