<?php

namespace App\Http\Controllers\Distributor\Authenticate;

use App\Http\Controllers\Controller;
use App\Models\CategoryDistributor;
use App\Models\DebtDistributor;
use App\Models\Distributor;
use App\Models\PaymentDistributor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AuthDistribController extends Controller
{
    #[OA\Post(
        path: "/api/v1/auth/loginDistributor",
        summary: "Authentification",
        description: "Connexion avec email ou téléphone + mot de passe",
        tags: ["Distributeurs Auth"],
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
    public function loginDistrib(Request $request): JsonResponse
    {
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

        $distrib = Distributor::where('email', $request->email)
            ->orWhere('phone', $request->email)
            ->first();

        if (! $distrib || ! Hash::check($request->password, $distrib->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Email / téléphone ou mot de passe incorrect.'
            ], 401);
        }

        if ($distrib->status !== 'actif') {
            return response()->json([
                'status'  => false,
                'message' => 'Votre compte est désactivé.'
            ], 403);
        }

        try {

            $distrib->tokens()->delete();

            $token = $distrib->createToken(
                $request->userAgent() ?? 'distributor_device',
                ['*']
            )->plainTextToken;

            return response()->json([
                'status'  => true,
                'message' => 'Connexion réussie.',
                'data' => [
                    'token' => $token,
                    'distributor'  => [
                        'id'    => $distrib->id,
                        'name'  => $distrib->name,
                        'email' => $distrib->email,
                        'phone' => $distrib->phone,
                        'status' => $distrib->status,
                    ],
                ],
            ], 200);
        } catch (\Throwable $e) {

            return response()->json([
                'status'  => false,
                'message' => 'Erreur lors de la connexion.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    #[OA\Post(
        path: "/api/v1/auth/logoutDistributor",
        summary: "Déconnexion",
        description: "Déconnecte l'utilisateur authentifié en supprimant son token actif",
        tags: ["Distributeurs Auth"],
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
    public function logoutDistrib(Request $request)
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

    #[OA\Get(
        path: "/api/v1/auth/distributorProfil",
        summary: "Liste des Distributeurs",
        tags: ["Distributeurs Auth"],
        parameters: [
            new OA\Parameter(name: "paginate", in: "query", schema: new OA\Schema(type: "integer", example: 10)),
            new OA\Parameter(name: "q", in: "query", schema: new OA\Schema(type: "string", example: "Kinshasa"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Liste paginée")
        ]
    )]
    public function profile(): JsonResponse
    {
        $distributor = Auth::guard('distributor')->user();

        if (! $distributor) {
            return response()->json([
                'status' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        $category = CategoryDistributor::find($distributor->category_distributor_id);

        return response()->json([
            'status' => 200,
            'message' => 'Profil récupéré avec succès',
            'data' => [
                'id' => $distributor->id,
                'reference' => $distributor->reference,
                'type' => $distributor->type,
                'name' => $distributor->name,
                'gender' => $distributor->gender,

                'phone' => $distributor->phone,
                'email' => $distributor->email,

                'country' => $distributor->country,
                'city' => $distributor->city,
                'commune' => $distributor->commune,
                'quartier' => $distributor->quartier,
                'avenue' => $distributor->avenue,

                'identity_type' => $distributor->identity_type,
                'identity_number' => $distributor->identity_number,

                'rccm' => $distributor->rccm,
                'idnat' => $distributor->idnat,
                'tax_number' => $distributor->tax_number,

                'status' => $distributor->status,
                'category_distributor' => $category,
            ]
        ]);
    }

    #[OA\Get(
        path: "/api/v1/auth/getMyDebts",
        summary: "Lister",
        tags: ["istributeurs Auth"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function myDebts(): JsonResponse
    {
        try {

            $distributor = Distributor::find(
                Auth::guard('distributor')->user()->id
            );

            if (! $distributor) {

                return response()->json([
                    'success' => false,
                    'message' => 'Distributeur introuvable'
                ], 404);
            }

            $debts = DebtDistributor::with([
                'sale',
                'distributor',
                'user'
            ])
                ->where('distributor_id', $distributor->id)
                ->whereIn('status', [
                    'pending',
                    'partial'
                ])
                ->latest()
                ->get();

            $debts->transform(function ($item) {

                $item->remaining_amount =
                    $item->loan_amount - $item->paid_amount;

                return $item;
            });

            $total_remaining = $debts->sum('remaining_amount');

            return response()->json([
                'success' => true,
                'status' => 200,

                'summary' => [
                    'total_debts' => $debts->count(),
                    'total_remaining' => $total_remaining,
                ],

                'data' => $debts
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/auth/getmyPayments",
        summary: "Lister",
        tags: ["istributeurs Auth"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function myPayments(): JsonResponse
    {
        try {

            $distributor = Distributor::find(
                Auth::guard('distributor')->user()->id
            );

            if (! $distributor) {

                return response()->json([
                    'success' => false,
                    'message' => 'Distributeur introuvable'
                ], 404);
            }

            $payments = PaymentDistributor::with([
                'debt.sale',
                'cashAccount',
                'user'
            ])
                ->whereHas('debt', function ($query) use ($distributor) {

                    $query->where(
                        'distributor_id',
                        $distributor->id
                    );
                })
                ->latest()
                ->get();

            $payments->transform(function ($item) {

                $debt = $item->debtDistributor;

                $item->remaining_amount =
                    $debt->loan_amount - $debt->paid_amount;

                return $item;
            });

            $total_paid = $payments->sum('paid_amount');

            $total_remaining = $payments->sum('remaining_amount');

            return response()->json([
                'success' => true,
                'status' => 200,

                'summary' => [
                    'total_payments' => $payments->count(),
                    'total_paid_amount' => $total_paid,
                    'total_remaining_amount' => $total_remaining,
                ],

                'data' => $payments

            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
