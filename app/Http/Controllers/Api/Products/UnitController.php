<?php

namespace App\Http\Controllers\Api\Products;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class UnitController extends Controller
{
    #[OA\Get(
        path: "/api/v1/unitGetOptionsData",
        summary: "Lister",
        tags: ["Units"],
        responses: [
            new OA\Response(response: 200, description: "Liste des unites")
        ]
    )]

    public function getUnitsOptions()
    {
        $data = Unit::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: '/api/v1/unitStoreData',
        summary: 'Créer',
        tags: ['Units'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['designation'],
                properties: [
                    new OA\Property(property: "designation", type: "string", example: "Cylindre"),
                    new OA\Property(property: "abreviation", type: "string", example: "Cl"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Données créées avec succès'
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

    public function storeUnit(Request $request)
    {
        $rule = [
            'designation' => ['nullable', 'unique:units,designation'],
            'abreviation' => ['nullable', 'unique:units,abreviation']
        ];
        $message = [
            'designation.unique'   => 'Cette unité existe déjà.',
            'abreviation.unique'   => 'Cette abréviation existe déjà.',
        ];

        $validator = Validator::make($request->all(), $rule, $message);
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }
        $exists = Unit::where('designation', $request->designation)
            ->first();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'existe déjà.',
            ], 409); // 409 = Conflict
        }

        try {
            Unit::create([
                'designation' => $request->designation,
                'abreviation' => $request->abreviation
            ]);
            return response()->json([
                'status'  => true,
                'message' => 'ajouté avec succès.',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Une erreur est survenue lors de la création.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
