<?php

namespace App\Http\Controllers\Api\Agent\Fonction;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Fonction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FonctionController extends Controller
{
    public function getDataOption()
    {
        try {
            $search = request('q');

            $data = Fonction::with('addedBy')
                ->when($search, function ($query) use ($search) {
                    $query->where('designation', 'LIKE', "%{$search}%")
                        ->orWhere('montant', 'LIKE', "%{$search}%");
                })
                ->latest()->get();

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Erreur lors de la récupération des fonctions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {
            $search = request('q');
            $devise = Currency::where('status', 'created')
                ->orderByRaw("currency_type = 'devise_principale' DESC")
                ->latest()
                ->get();

            $data = Fonction::with('addedBy')
                ->when($search, function ($query) use ($search) {
                    $query->where('designation', 'LIKE', "%{$search}%")
                        ->orWhere('montant', 'LIKE', "%{$search}%")
                        ->orWhere('status', 'LIKE', "%{$search}%");
                })
                ->latest()
                ->paginate(10);

            return response()->json([
                'status' => 200,
                'success' => true,
                'devise' => $devise,
                'data' => $data
            ]);
        } catch (Exception $e) {

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Erreur lors de la récupération des fonctions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'designation' => 'required|string|max:255',
            'montant' => 'nullable',
        ]);
        $getFonction = Fonction::where('designation', $request->designation)->first();
        if ($getFonction) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Cette fonction existe',
            ], 422);
        }
        $fonction = Fonction::create([
            'designation' => $request->designation,
            'montant' => $request->montant,
            'reference' => fake()->randomNumber(5),
            'addedBy' => Auth::id()
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Fonction créée avec succès',
            'data' => $fonction
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'designation' => 'required|string|max:255|unique:fonction_agents,designation,' . $id,
            'montant' => 'nullable|numeric|min:0'
        ]);

        $fonction = Fonction::findOrFail($id);

        $fonction->update([
            'designation' => $request->designation,
            'montant' => $request->montant
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Fonction modifiée avec succès',
            'data' => $fonction
        ]);
    }

    public function destroy($id)
    {
        $fonction = Fonction::findOrFail($id);

        $fonction->status = 'deleted';
        $fonction->save();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Fonction supprimée avec succès'
        ]);
    }
}
