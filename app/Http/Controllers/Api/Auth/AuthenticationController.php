<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;


class AuthenticationController extends Controller
{

    #[OA\Post(
        path: "/api/v1/auth/login",
        summary: "Authentification utilisateur",
        description: "Connexion avec email ou téléphone + mot de passe",
        tags: ["Authenticate"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(
                        property: "email",
                        type: "string",
                        example: "user@example.com ou 0990000000"
                    ),
                    new OA\Property(
                        property: "password",
                        type: "string",
                        example: "password123"
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Connexion réussie",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Connexion réussie."),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "token", type: "string", example: "1|abcdefg123456"),
                                new OA\Property(
                                    property: "user",
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", example: 1),
                                        new OA\Property(property: "name", type: "string", example: "John Doe"),
                                        new OA\Property(property: "email", type: "string", example: "user@example.com"),
                                        new OA\Property(property: "phone", type: "string", example: "0990000000"),
                                        new OA\Property(property: "active", type: "boolean", example: true),
                                        new OA\Property(property: "role", type: "string", example: "Admin"),
                                        new OA\Property(
                                            property: "permissions",
                                            type: "array",
                                            items: new OA\Items(type: "string"),
                                            example: ["Voir_User", "Ajouter_User"]
                                        ),
                                    ],
                                    type: "object"
                                ),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Identifiants incorrects"
            ),
            new OA\Response(
                response: 403,
                description: "Compte désactivé"
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            ),
            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            ),
        ]
    )]

    public function login(Request $request)
    {
        // 🔹 Validation
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'string'],
            'password' => ['required', 'string', 'min:4'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
            ->orWhere('phone', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Email / téléphone ou mot de passe incorrect.'
            ], 401);
        }

        if (!$user->active) {
            return response()->json([
                'status'  => false,
                'message' => 'Votre compte est désactivé.'
            ], 403);
        }

        try {
            // 🔹 Récupération des permissions
            $rolePermissions = DB::table('role_permission_actions as rpa')
                ->join('permissions as p', 'rpa.permission_id', '=', 'p.id')
                ->where('rpa.role_id', $user->role_id)
                ->select(
                    'p.name as permission_name',
                    'rpa.voir',
                    'rpa.ajouter',
                    'rpa.modifier',
                    'rpa.supprimer'
                )
                ->get();

            // 🔹 Transformation en tableau lisible
            $permissions = [];
            $actionMap = [
                'voir'      => 'Voir',
                'ajouter'   => 'Ajouter',
                'modifier'  => 'Modifier',
                'supprimer' => 'Supprimer'
            ];

            foreach ($rolePermissions as $rp) {
                foreach ($actionMap as $col => $prefix) {
                    if ((bool) $rp->$col === true) {
                        $permissions[] = $prefix . '_' . $rp->permission_name;
                    }
                }
            }

            // 🔹 Génération du token
            $device_name = $request->userAgent() ?? 'unknown_device';
            $token = $user->createToken($device_name, ['*'])->plainTextToken;

            return response()->json([
                'status'  => true,
                'message' => 'Connexion réussie.',
                'data' => [
                    'token' => $token,
                    'user'  => [
                        'id'          => $user->id,
                        'name'        => $user->name,
                        'email'       => $user->email,
                        'phone'       => $user->phone,
                        'active'      => $user->active,
                        'role'        => $user->role->name ?? null,
                        'permissions' => $permissions,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Erreur lors de la connexion.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    #[OA\Post(
        path: "/api/v1/auth/logout",
        summary: "Déconnexion utilisateur",
        description: "Déconnecte l'utilisateur authentifié en supprimant son token actif",
        tags: ["Authenticate"],
        security: [
            ["bearerAuth" => []]
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Déconnexion réussie",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "status",
                            type: "boolean",
                            example: true
                        ),
                        new OA\Property(
                            property: "message",
                            type: "string",
                            example: "Déconnexion réussie."
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Utilisateur non authentifié"
            ),
            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            )
        ]
    )]

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Déconnexion réussie.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Erreur lors de la déconnexion.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
