<?php

namespace App\Http\Controllers\Api\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{

    #[OA\Post(
        path: "/api/v1/roleStore",
        summary: "Créer un rôle avec permissions",
        tags: ["Roles"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Administrateur"),
                    new OA\Property(
                        property: "permissions",
                        type: "array",
                        description: "Liste des permissions au format Action_Permission (ex: Voir_User, Ajouter_Post)",
                        items: new OA\Items(type: "string"),
                        example: ["Voir_User", "Ajouter_Post"]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Rôle créé avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "success"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "Administrateur"),
                                new OA\Property(property: "guard_name", type: "string", example: "web"),
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: "Erreur serveur",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Erreur lors de la création du rôle")
                    ]
                )
            )
        ]
    )]

    public function storeRole(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|unique:roles,name',
                'permissions' => 'array',
            ]);

            $role = null;

            DB::transaction(function () use ($request, &$role) {
                $role = Role::create([
                    'name' => $request->name,
                    'guard_name' => 'web',
                ]);

                $permissions = $request->permissions ?? [];
                $actionMap = ['Voir' => 'voir', 'Ajouter' => 'ajouter', 'Modifier' => 'modifier', 'Supprimer' => 'supprimer'];

                foreach ($permissions as $permission) {
                    $parts = explode('_', $permission);
                    if (count($parts) !== 2) throw new \Exception("Format de permission invalide: $permission");

                    [$user_permission, $role_permission] = $parts;
                    $perm = Permission::where('name', $role_permission)->first();
                    if (!$perm) throw new \Exception("Permission $role_permission non trouvée");

                    $column = $actionMap[$user_permission] ?? null;
                    if ($column) {
                        DB::table('role_permission_actions')->updateOrInsert(
                            ['role_id' => $role->id, 'permission_id' => $perm->id],
                            [$column => true]
                        );
                    }
                }
            });

            return response()->json(['success' => true, 'message' => 'success', 'data' => $role], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[OA\Put(
        path: "/api/v1/roleUpdate/{id}",
        summary: "Mettre à jour un rôle et ses permissions",
        tags: ["Roles"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID du rôle",
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Administrateur"),
                    new OA\Property(
                        property: "permissions",
                        type: "array",
                        description: "Liste des permissions au format Action_Permission (ex: Voir_User, Ajouter_Post)",
                        items: new OA\Items(type: "string"),
                        example: ["Voir_User", "Modifier_User"]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Rôle mis à jour avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Rôle mis à jour avec succès"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "Administrateur"),
                                new OA\Property(property: "guard_name", type: "string", example: "web"),
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Erreur de validation des permissions",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Format de permission invalide")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Rôle non trouvé"
            ),
            new OA\Response(
                response: 500,
                description: "Erreur serveur",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Erreur interne")
                    ]
                )
            )
        ]
    )]
    public function updateRole(Request $request, $id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);

            $request->validate([
                'name' => 'required|string|unique:roles,name,' . $role->id,
                'permissions' => 'array',
            ]);

            $role->update([
                'name' => $request->name,
                'guard_name' => 'web',
            ]);

            $permissions = $request->permissions ?? [];

            DB::table('role_permission_actions')
                ->where('role_id', $role->id)
                ->update([
                    'voir' => false,
                    'ajouter' => false,
                    'modifier' => false,
                    'supprimer' => false,
                ]);

            $actionMap = [
                'Voir' => 'voir',
                'Ajouter' => 'ajouter',
                'Modifier' => 'modifier',
                'Supprimer' => 'supprimer',
            ];

            foreach ($permissions as $permission) {
                $parts = explode('_', $permission);

                if (count($parts) !== 2) {
                    return response()->json([
                        'success' => false,
                        'message' => "Format de permission invalide: $permission"
                    ], 400);
                }

                [$user_permission, $role_permission] = $parts;

                $perm = Permission::where('name', $role_permission)->first();
                if (!$perm) {
                    return response()->json([
                        'success' => false,
                        'message' => "Permission $role_permission non trouvée"
                    ], 400);
                }

                $column = $actionMap[$user_permission] ?? null;
                if ($column) {
                    DB::table('role_permission_actions')->updateOrInsert(
                        [
                            'role_id' => $role->id,
                            'permission_id' => $perm->id,
                        ],
                        [
                            $column => true,
                        ]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Rôle mis à jour avec succès',
                'data' => $role,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
