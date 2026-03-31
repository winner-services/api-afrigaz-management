<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\About;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API Laravel 13',
    description: 'Documentation API AFRIGAZ',
    contact: new OA\Contact(email: 'admin@admin.com')
)]
class AboutController extends Controller
{
   #[OA\Get(
        path: '/api/aboutGetAllData',
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
