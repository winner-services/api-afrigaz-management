<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\About;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class CompanyController extends Controller
{
    #[OA\Get(
        path: '/api/v1/aboutGetAllData',
        summary: 'Récupère les informations About',
        tags: ['About'],
        responses: [
            new OA\Response(response: 200, description: 'Données récupérées avec succès'),
            new OA\Response(response: 422, description: 'Aucune donnée trouvée'),
        ]
    )]
    public function getData(): JsonResponse
    {
        try {
            $about = About::query()->first();

            if (!$about) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Aucune donnée trouvée',
                    'data'    => null,
                ], 422);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Données récupérées avec succès',
                'data'    => $about,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Erreur serveur',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/v1/aboutStoreData',
        summary: 'Créer ou mettre à jour les informations About',
        tags: ['About'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['denomination', 'details'],
                properties: [
                    new OA\Property(property: 'denomination', type: 'string', example: 'Afrigaz Express'),
                    new OA\Property(property: 'details', type: 'string', example: 'Détails de la société'),
                    new OA\Property(property: 'register', type: 'string', example: 'RC12345'),
                    new OA\Property(property: 'national_id', type: 'string', example: '123456789'),
                    new OA\Property(property: 'tax_number', type: 'string', example: 'TAX123456'),
                    new OA\Property(property: 'phone', type: 'string', example: '+243990000000'),
                    new OA\Property(property: 'address', type: 'string', example: 'Kinshasa, RDC'),
                    new OA\Property(property: 'email', type: 'string', example: 'contact@afrigaz.com'),
                    new OA\Property(property: 'logo', type: 'string', format: 'binary', description: 'Fichier image'),
                    new OA\Property(property: 'caution_amount', type: 'number', format: 'float', example: 1000.00),
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->all();

        try {
            $about = DB::transaction(function () use ($request, $validated) {

                $about = About::query()->first();

                if ($request->hasFile('logo')) {

                    if ($about && $about->logo) {
                        Storage::disk('public')->delete($about->logo);
                    }

                    $validated['logo'] = $request->file('logo')->store('about', 'public');
                }

                if ($about) {
                    $about->update($validated);
                } else {
                    $about = About::create($validated);
                }

                return $about;
            });

            return response()->json([
                'status'  => true,
                'message' => $about->wasRecentlyCreated
                    ? 'Données créées avec succès'
                    : 'Données mises à jour avec succès',
                'data'    => $about
            ], $about->wasRecentlyCreated ? 201 : 200);
        } catch (\Throwable $e) {

            return response()->json([
                'status'  => false,
                'message' => 'Erreur serveur',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
