<?php

namespace App\Http\Controllers\Api\Permission;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use OpenApi\Attributes as OA;

class PermissionController extends Controller
{
    #[OA\Get(
        path: '/api/v1/permissionsGetAllData',
        summary: 'Récupère les informations',
        tags: ['Permissions'],
        responses: [
            new OA\Response(response: 200, description: 'Données récupérées avec succès'),
            new OA\Response(response: 422, description: 'Aucune donnée trouvée'),
        ]
    )]
    public function getPemissionData()
    {
        $data = Permission::all();
        return response()->json([
            'message' => 'succes',
            'status' => 200,
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/getPermissionDataByRole/{id}",
        summary: "Supprimer un utilisateur",
        tags: ["Permissions"],
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
                description: "succès"
            ),
            new OA\Response(
                response: 404,
                description: "non trouvé"
            ),
            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            )
        ]
    )]
    public function getPermissionDataByRole($id)
    {
        $rows = DB::table('role_permission_actions')
            ->join('permissions', 'permissions.id', '=', 'role_permission_actions.permission_id')
            ->where('role_permission_actions.role_id', $id)
            ->select('permissions.name as permission_name', 'role_permission_actions.*')
            ->get();

        $actionMap = [
            'voir'     => "Voir",
            'ajouter'  => "Ajouter",
            'modifier' => "Modifier",
            'supprimer' => "Supprimer",
        ];

        $result = [];

        foreach ($rows as $row) {
            foreach ($actionMap as $col => $prefix) {
                if ($row->$col) {
                    $result[] = $prefix . "_" . $row->permission_name;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'success',
            'data'    => $result
        ]);
    }
}
