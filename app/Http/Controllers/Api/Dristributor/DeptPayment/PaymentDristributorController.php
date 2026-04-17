<?php

namespace App\Http\Controllers\Api\Dristributor\DeptPayment;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaymentDristributorController extends Controller
{
    public function index(): JsonResponse
    {
        $page = request('paginate', 10);
        $q = request('q', '');
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');

        // 🔒 Sécurité tri
        $allowedSortFields = ['id', 'name', 'phone', 'address', 'created_at', 'zone', 'caution_amount', 'operation_date'];

        if (!in_array($sort_field, $allowedSortFields)) {
            $sort_field = 'id';
        }

        if (!in_array(strtolower($sort_direction), ['asc', 'desc'])) {
            $sort_direction = 'desc';
        }

        $data = Distributor::query()
            ->leftJoin('users', 'distributors.addedBy', '=', 'users.id')
            ->select(
                'distributors.*',
                'users.name as addedBy'
            )
            ->where('distributors.is_deleted', false)

            // 🔍 Recherche
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('distributors.name', 'LIKE', "%{$q}%")
                        ->orWhere('distributors.phone', 'LIKE', "%{$q}%")
                        ->orWhere('distributors.address', 'LIKE', "%{$q}%")
                        ->orWhere('users.name', 'LIKE', "%{$q}%");
                });
            })

            ->orderBy("distributors.$sort_field", $sort_direction)
            ->paginate($page);

        return response()->json([
            'status' => true,
            'message' => 'succès',
            'data' => $data
        ]);
    }
}
