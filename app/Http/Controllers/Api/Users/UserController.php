<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/v1/userGetAllData',
        summary: 'Récupère les informations User',
        tags: ['Users'],
        responses: [
            new OA\Response(response: 200, description: 'Données récupérées avec succès'),
            new OA\Response(response: 422, description: 'Aucune donnée trouvée'),
        ]
    )]

    public function index()
    {
        try {
            $page = request('paginate', 10);
            $q = request('q', '');
            $sort_field = request('sort_field', 'id');
            $sort_direction = request('sort_direction', 'desc');

            $users = User::query()
                ->search($q)
                ->orderBy($sort_field, $sort_direction)
                ->paginate($page);

            $data = UserResource::collection($users)->response()->getData(true);

            $result = [
                'message' => "Liste des utilisateurs récupérée avec succès.",
                'success' => true,
                'status' => 200,
                'data' => $data
            ];

            return response()->json($result, 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => "Erreur de base de données. Vérifiez la requête ou le champ de tri.",
                'success' => false,
                'status' => 500,
                'data' => []
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => "Erreur interne du serveur : " . $e->getMessage(),
                'success' => false,
                'status' => 500,
                'data' => []
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/userGetOptionsData',
        summary: 'Récupère les informations User',
        tags: ['Users'],
        responses: [
            new OA\Response(response: 200, description: 'Données récupérées avec succès'),
            new OA\Response(response: 422, description: 'Aucune donnée trouvée'),
        ]
    )]
    public function getAllUsersOptions()
    {
        $q = request("q", "");
        $data = User::where('active', true)->search(trim($q))->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'status' => 200,
            'data' => $data
        ];
        return response()->json($result);
    }


    #[OA\Post(
        path: '/api/v1/userStoreData',
        summary: 'Créer',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", example: "john@example.com"),
                    new OA\Property(property: "phone", type: "string", nullable: true, example: "+243990000000"),
                    new OA\Property(property: "password", type: "string", example: "secret123"),
                    new OA\Property(property: "active", type: "boolean", example: true),
                    new OA\Property(property: "status", type: "string", example: "created"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Données créées avec succès'
            ),
            new OA\Response(
                response: 200,
                description: 'Données mises à jour avec succès'
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

    public function store(Request $request)
    {
        $rules = [
            'name'     => ['required', 'string', 'max:255', 'unique:users,name'],
            'email'    => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string'],
            'role_id'  => ['required', 'integer', 'exists:roles,id'],
        ];

        $messages = [
            'phone.unique'   => 'Le numéro de téléphone existe déjà.',
            'name.unique'    => 'Le nom d\'utilisateur existe déjà.',
            'email.unique'   => 'L\'adresse e-mail existe déjà.',
            'role_id.exists' => 'Le rôle spécifié n\'existe pas.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }
        $exists = User::where('name', $request->name)
            ->orWhere('email', $request->email)
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

            $user = User::create([
                'name'     => $request->input('name'),
                'email'    => $request->input('email'),
                'phone'    => $request->input('phone'),
                'password' => bcrypt($request->input('password')),
            ]);

            $user->assignRole($request->input('role_id'));

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Agent ajouté avec succès.',
                'data'    => $user,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue lors de la création de l\'utilisateur.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/userUpdateData/{id}",
        summary: "Mettre à jour un utilisateur",
        tags: ["Users"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "phone", type: "string", nullable: true),
                    new OA\Property(property: "password", type: "string", nullable: true),
                    new OA\Property(property: "active", type: "boolean"),
                    new OA\Property(property: "status", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Utilisateur mis à jour",
            ),
            new OA\Response(
                response: 404,
                description: "Utilisateur non trouvé"
            ),
            new OA\Response(
                response: 422,
                description: "Validation échouée"
            )
        ]
    )]

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        $rules = [
            'name'     => ['required', 'string', 'max:255', "unique:users,name,{$id}"],
            'email'    => ['nullable', 'string', 'email', 'max:255', "unique:users,email,{$id}"],
            'phone'    => ['required', 'string', 'max:20', "unique:users,phone,{$id}"],
            'password' => ['nullable', 'string'],
            'role_id'  => ['required', 'integer', 'exists:roles,id'],
        ];

        $messages = [
            'phone.unique'   => 'Le numéro de téléphone existe déjà.',
            'name.unique'    => 'Le nom d\'utilisateur existe déjà.',
            'email.unique'   => 'L\'adresse e-mail existe déjà.',
            'role_id.exists' => 'Le rôle spécifié n\'existe pas.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user->name  = $request->input('name');
            $user->email = $request->input('email');
            $user->phone = $request->input('phone');

            if ($request->filled('password')) {
                $user->password = bcrypt($request->input('password'));
            }

            $user->save();

            $user->syncRoles([$request->input('role_id')]);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Utilisateur mis à jour avec succès.',
                'data'    => $user,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue lors de la mise à jour de l\'utilisateur.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    #[OA\Delete(
        path: "/api/v1/userDestroyData/{id}",
        summary: "Supprimer un utilisateur",
        tags: ["Users"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Utilisateur supprimé avec succès"
            ),
            new OA\Response(
                response: 404,
                description: "Utilisateur non trouvé"
            ),
            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            )
        ]
    )]
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $user->roles()->detach();

            $user->delete();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Utilisateur supprimé avec succès.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/userActivate/{id}",
        summary: "Activer un utilisateur",
        tags: ["Users"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID de l'utilisateur à activer",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Utilisateur activé avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Utilisateur activé avec succès"),
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "status", type: "integer", example: 201),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Utilisateur non trouvé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Utilisateur non trouvé")
                    ]
                )
            )
        ]
    )]
    public function activateUser($id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $user->active = true;
        $user->save();

        return response()->json([
            'message' => 'Utilisateur activé avec succès',
            'success' => true,
            'status' => 201
        ], 200);
    }

    #[OA\Put(
        path: "/api/v1/userDisable/{id}",
        summary: "Désactiver un utilisateur",
        tags: ["Users"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID de l'utilisateur à désactiver",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Utilisateur désactivé avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Utilisateur désactivé avec succès"),
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "status", type: "integer", example: 201),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Utilisateur non trouvé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Utilisateur non trouvé")
                    ]
                )
            )
        ]
    )]
    public function disableUser($id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $user->active = false;
        $user->save();

        return response()->json([
            'message' => 'Utilisateur désactivé avec succès',
            'success' => true,
            'status' => 201
        ], 200);
    }
}
