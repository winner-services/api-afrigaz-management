<?php

namespace App\Http\Controllers\Api\Branches;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;
use OpenApi\Attributes as OA;

class BrancheController extends Controller
{
    #[OA\Get(
        path: "/api/v1/brancheGetAllData",
        summary: "Lister les branches",
        tags: ["Branches"],
        responses: [
            new OA\Response(response: 200, description: "Liste des branches")
        ]
    )]
    public function get(): JsonResponse
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $branches = Branche::leftjoin('users as u', 'branches.user_id', '=', 'u.id')
            ->leftjoin('users as a', 'branches.addedBy', '=', 'a.id')
            ->select('branches.*', 'u.name as agent_name', 'a.name as addedBy')
            ->where('branches.status', '!=', 'deleted')
            ->orderBy('branches.created_at', 'desc')
            ->search(trim($q))
            ->paginate($page);
        // $branches = Branche::with(['user', 'addedBy'])->where('status', '!=', 'deleted')
        //     ->orderBy('created_at', 'desc')
        //     ->search(trim($q))
        //     ->paginate($page);

        return response()->json([
            'status' => true,
            'data' => $branches
        ]);
    }


    #[OA\Post(
        path: "/api/v1/brancheStoreData",
        summary: "Créer une branche",
        tags: ["Branches"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Agence Gombe"),
                    new OA\Property(property: "phone", type: "string", example: "0999999999"),
                    new OA\Property(property: "city", type: "string", example: "Kinshasa"),
                    new OA\Property(property: "address", type: "string", example: "Av. de la paix"),
                    new OA\Property(property: "user_id", type: "integer", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Branche créée"),
            new OA\Response(response: 422, description: "Erreur validation")
        ]
    )]
    public function storeData(Request $request): JsonResponse
    {
        $rules = [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
        ];

        $messages = [
            'name.required'    => 'Le nom de la branche est obligatoire.',
            'phone.unique'     => 'Ce numéro de téléphone est déjà utilisé.',
            'user_id.exists'   => 'L\'utilisateur spécifié n\'existe pas.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Les données sont invalides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $exists = Branche::where('name', $request->name)
            ->orWhere('phone', $request->phone)
            ->first();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'Un utilisateur avec ce nom, e-mail ou téléphone existe déjà.',
            ], 409); // 409 = Conflict
        }
        try {
            DB::beginTransaction();

            $userId = Auth::id();

            $branch = Branche::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'city' => $request->city,
                'address' => $request->address,
                'user_id' => $request->user_id,
                'addedBy' => $userId,
                'reference' => fake()->unique()->numerify('BR-#####')
            ]);

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Branche créée avec succès',
                'data' => $branch
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue lors de la création',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/brancheUpdate/{id}",
        summary: "Modifier une branche",
        tags: ["Branches"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "phone", type: "string"),
                    new OA\Property(property: "city", type: "string"),
                    new OA\Property(property: "address", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Branche mise à jour"),
            new OA\Response(response: 404, description: "Non trouvée")
        ]
    )]

    public function update(Request $request, $id): JsonResponse
    {
        $branch = Branche::findOrFail($id);

        if (!$branch) {
            return response()->json([
                'status'  => false,
                'message' => 'Branche introuvable'
            ], 404);
        }

        $rules = [
            'name'     => ['sometimes', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:20', 'unique:branches,phone,' . $branch->id],
            'city'     => ['nullable', 'string', 'max:100'],
            'address'  => ['nullable', 'string', 'max:255'],
            'user_id'  => ['nullable', 'integer', 'exists:users,id'],
        ];

        $messages = [
            'phone.unique'   => 'Ce numéro de téléphone est déjà utilisé.',
            'user_id.exists' => 'L\'utilisateur spécifié n\'existe pas.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Les données sont invalides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            $branch->update($request->only([
                'name',
                'phone',
                'city',
                'address',
                'user_id'
            ]));
            DB::commit();
            return response()->json([
                'status'  => true,
                'message' => 'Branche mise à jour',
                'data'    => $branch
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Erreur serveur'
            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/brancheDelete/{id}",
        summary: "Supprimer une branche",
        tags: ["Branches"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Supprimée"),
            new OA\Response(response: 404, description: "Non trouvée")
        ]
    )]
    public function destroy($id)
    {
        try {
            $branch = Branche::find($id);

            if (!$branch) {
                return response()->json([
                    'status' => false,
                    'message' => 'Branche introuvable'
                ], 404);
            }

            $branch->status = 'deleted';
            $branch->save();

            return response()->json([
                'status' => true,
                'message' => 'Branche supprimée'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Erreur serveur'
            ], 500);
        }
    }
}
