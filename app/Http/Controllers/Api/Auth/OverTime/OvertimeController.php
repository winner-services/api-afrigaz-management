<?php

namespace App\Http\Controllers\Api\Auth\OverTime;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Services\EmessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

use function Illuminate\Support\now;

class OvertimeController extends Controller
{
    #[OA\Post(
        path: "/api/v1/overtimeRequest",
        summary: "Demande d'heures supplémentaires",
        description: "Permet à un utilisateur de soumettre une demande d'heures supplémentaires",
        tags: ["Overtime"],
        security: [["bearerAuth" => []]],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["minutes", "reason"],
                properties: [
                    new OA\Property(
                        property: "minutes",
                        type: "integer",
                        example: 60,
                        minimum: 1,
                        description: "Nombre de minutes demandées"
                    ),
                    new OA\Property(
                        property: "reason",
                        type: "string",
                        example: "Travail urgent sur production",
                        description: "Motif de la demande"
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: "Demande créée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "status", type: "integer", example: 201),
                        new OA\Property(property: "message", type: "string", example: "Demande envoyée avec succès"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "user_id", type: "integer", example: 5),
                                new OA\Property(property: "requested_minutes", type: "integer", example: 60),
                                new OA\Property(property: "reason", type: "string", example: "Travail urgent"),
                                new OA\Property(property: "status", type: "string", example: "pending"),
                                new OA\Property(property: "requested_at", type: "string", format: "date-time")
                            ]
                        )
                    ]
                )
            ),

            new OA\Response(
                response: 422,
                description: "Erreur de validation"
            ),

            new OA\Response(
                response: 500,
                description: "Erreur serveur",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Une erreur est survenue lors de la création")
                    ]
                )
            )
        ]
    )]
    public function request(Request $request)
    {
        try {
            $request->validate([
                'minutes' => 'required|integer|min:1',
                'reason' => 'required|string'
            ]);

            $overtime = OvertimeRequest::create([

                'user_id' => Auth::id(),

                'operation_date' => now(),

                'requested_at' => now(),

                'requested_minutes' => $request->minutes,

                'reason' => $request->reason,

                'status' => 'pending'

            ]);

            return response()->json([

                'success' => true,
                'status' => 201,
                'message' => 'Demande envoyée avec succès',
                'data' => $overtime
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue lors de la création',
                'error'   => config('app.debug') ? $th->getMessage() : null
            ], 500);
        }
    }

    #[OA\Patch(
        path: "/api/v1/approveRequest/{id}",
        summary: "Approuver une demande d'heures supplémentaires",
        description: "Permet à un administrateur d'approuver une demande d'heures supplémentaires",
        tags: ["Overtime"],
        security: [["bearerAuth" => []]],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID de la demande d'heures supplémentaires",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],

        responses: [
            new OA\Response(
                response: 200,
                description: "Demande approuvée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "status", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Heures supp approuvées")
                    ]
                )
            ),

            new OA\Response(
                response: 404,
                description: "Demande introuvable"
            ),

            new OA\Response(
                response: 500,
                description: "Erreur serveur",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Une erreur est survenue lors de la création")
                    ]
                )
            )
        ]
    )]
    public function approve($id)
    {
        try {
            $overtime = OvertimeRequest::findOrFail($id);

            $until = now()->addMinutes(
                $overtime->requested_minutes
            );

            $overtime->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_until' => $until,
                'approved_at' => now(),
            ]);

            $overtime->user->update([
                'overtime_until' => $until
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Heures supp approuvées'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue lors de la création',
                'error'   => config('app.debug') ? $th->getMessage() : null
            ], 500);
        }
    }

    #[OA\Patch(
        path: "/api/v1/rejecteRequest/{id}",
        summary: "Rejeter une demande d'heures supplémentaires",
        description: "Permet à un administrateur de rejeter une demande d'heures supplémentaires",
        tags: ["Overtime"],
        security: [["bearerAuth" => []]],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID de la demande d'heures supplémentaires",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],

        responses: [
            new OA\Response(
                response: 200,
                description: "Demande rejetée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "status", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Heures supp rejetées")
                    ]
                )
            ),

            new OA\Response(
                response: 404,
                description: "Demande introuvable"
            ),

            new OA\Response(
                response: 500,
                description: "Erreur serveur",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Une erreur est survenue lors de la création")
                    ]
                )
            )
        ]
    )]
    public function rejecte($id)
    {
        try {
            $overtime = OvertimeRequest::findOrFail($id);

            $overtime->update([
                'status' => 'rejected',
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Heures supp rejectées'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue lors de la création',
                'error'   => config('app.debug') ? $th->getMessage() : null
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/overtimeGetData",
        summary: "Lister les demandes d'heures supplémentaires",
        description: "Retourne la liste avec filtres + pagination",
        tags: ["Overtime"],
        security: [["bearerAuth" => []]],

        parameters: [
            new OA\Parameter(
                name: "status",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "pending")
            ),

            new OA\Parameter(
                name: "user_id",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 5)
            ),

            new OA\Parameter(
                name: "date",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", format: "date", example: "2026-05-03")
            ),

            new OA\Parameter(
                name: "search",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "john")
            ),

            new OA\Parameter(
                name: "per_page",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 10)
            )
        ],

        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des demandes",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "status", type: "integer", example: 200),

                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "current_page", type: "integer", example: 1),

                                new OA\Property(
                                    property: "data",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 1),
                                            new OA\Property(property: "user_id", type: "integer", example: 5),
                                            new OA\Property(property: "requested_minutes", type: "integer", example: 60),
                                            new OA\Property(property: "reason", type: "string", example: "Travail urgent"),
                                            new OA\Property(property: "status", type: "string", example: "pending")
                                        ]
                                    )
                                ),

                                new OA\Property(property: "total", type: "integer", example: 100)
                            ]
                        )
                    ]
                )
            ),

            new OA\Response(
                response: 500,
                description: "Erreur serveur"
            )
        ]
    )]

    public function index(Request $request)
    {
        try {
            $query = OvertimeRequest::with('user', 'approvedBy', 'rejectedBy')
                ->orderByDesc('created_at');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('date')) {
                $query->whereDate('created_at', $request->date);
            }

            if ($request->filled('search')) {
                $search = $request->search;

                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            if (!Auth::user()->is_admin) {
                $query->where('user_id', Auth::id());
            }

            $overtimes = $query->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $overtimes
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Erreur lors de la récupération des données',
                'error' => config('app.debug') ? $th->getMessage() : null
            ], 500);
        }
    }


    public function sms(EmessService $sms)
    {
        return response()->json(

            $sms->sendSms(
                '+243997604471',
                'Bonjour depuis Laravel'
            )

        );
    }
}
