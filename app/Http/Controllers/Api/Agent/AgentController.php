<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{
    public function rapportAgent()
    {
        try {

            $agents = Agent::with('fonction')

                ->latest()->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $agents
            ]);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOptionData()
    {
        try {

            $search = request('q');
            $agents = Agent::with('fonction')
                ->when($search, function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                ->latest()->get();
            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $agents
            ]);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {

            $search = request('q');

            $agents = Agent::with('fonction')
                ->when($search, function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhereHas('fonction', function ($q) use ($search) {
                            $q->where('designation', 'LIKE', "%{$search}%");
                        });
                })
                ->latest()
                ->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $agents
            ]);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {

            $request->validate([
                'name' => 'required|string|max:255',
                'gender' => 'required|string',
                'phone' => 'nullable|unique:agents,phone',
                'email' => 'nullable|email|unique:agents,email',
                'address' => 'nullable|string',
                'niveau_etude' => 'nullable|string',
                'identity_document' => 'nullable|string',
                'part_number' => 'nullable|string',
                'etat_civil' => 'nullable|string',
                'fonction_id' => 'nullable|exists:fonctions,id'
            ]);

            $agent = Agent::where('name', $request->name)->first();
            if ($agent) {
                return response()->json([
                    'status' => 422,
                    'success' => false,
                    'message' => 'Cet agent existe',
                ], 422);
            }

            $agent = Agent::create([
                'name' => $request->name,
                'gender' => $request->gender,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'niveau_etude' => $request->niveau_etude,
                'identity_document' => $request->identity_document,
                'part_number' => $request->part_number,
                'etat_civil' => $request->etat_civil,
                'fonction_id' => $request->fonction_id,
                'status' => 'created',
                'addedBy' => Auth::user()->id
            ]);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Agent enregistré avec succès',
                'data' => $agent
            ], 201);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {

            $agent = Agent::findOrFail($id);

            $request->validate([
                'name' => 'nullable|string|max:255|unique:agents,phone,' . $id,
                'gender' => 'nullable|string',
                'phone' => 'nullable|unique:agents,phone,' . $id,
                'email' => 'nullable|email|unique:agents,email,' . $id,
                'niveau_etude' => 'nullable|string',
                'identity_document' => 'nullable|string',
                'part_number' => 'nullable|string',
                'etat_civil' => 'nullable|string',
                'fonction_id' => 'nullable|exists:fonctions,id'
            ]);

            $agent->update([
                'name' => $request->name,
                'gender' => $request->gender,
                'phone' => $request->phone,
                'email' => $request->email,
                'niveau_etude' => $request->niveau_etude,
                'identity_document' => $request->identity_document,
                'part_number' => $request->part_number,
                'etat_civil' => $request->etat_civil,
                'fonction_id' => $request->fonction_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agent modifié avec succès',
                'data' => $agent
            ]);
        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Agent introuvable'
            ], 404);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {

            $agent = Agent::findOrFail($id);

            $agent->status = 'deleted';
            $agent->save();

            return response()->json([
                'success' => true,
                'message' => 'Agent supprimé avec succès'
            ]);
        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Agent introuvable'
            ], 404);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
