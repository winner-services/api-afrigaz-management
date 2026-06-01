<?php

namespace App\Http\Controllers\Mobile\Auth;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthenticateController extends Controller
{
    public function mobileLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'string'],
            'password' => ['required', 'string'],
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
        
        // if (!$user->role || mb_strtolower(trim($user->role->name)) !== 'vendeur') {
        //     return response()->json([
        //         'status'  => false,
        //         'message' => 'Accès refusé'
        //     ], 403);
        // }

        if (!$user->active) {
            return response()->json([
                'status'  => false,
                'message' => 'Votre compte est désactivé.'
            ], 403);
        }

        try {
            $branche = Branche::where('user_id', $user->id)->first();
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

            $device_name = $request->userAgent() ?? 'unknown_device';
            $token = $user->createToken($device_name, ['*'])->plainTextToken;

            dd([
            'role_id' => $user->role_id,
            'role' => $user->role,
        ]);

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
                        'branch_id'   => $branche->id ?? null,
                        'branche'     => $branche ? $branche->name : null,
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
}
