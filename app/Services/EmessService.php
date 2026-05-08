<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EmessService
{
    // protected string $baseUrl = 'https://emess.cd/api/v0';

    /**
     * Générer token JWT
     */
    public function getToken(): ?string
    {
        return Cache::remember('emess_token', 3500, function () {

            $response = Http::asJson()
                ->post('https://emess.cd/api/v0/auth/token', [
                    'appId' => env('EMESS_APP_ID'),
                    'secretKey' => env('EMESS_SECRET_KEY'),
                ]);

            return $response->json()['token'] ?? null;
        });
    }

    /**
     * Envoyer SMS
     */
    public function sendSms(string $phone, string $message): array
    {
        $token = $this->getToken();

        if (! $token) {
            return [
                'success' => false,
                'message' => 'Impossible de récupérer le token'
            ];
        }

        $response = Http::timeout(30)
            ->retry(3, 500)
            ->withToken($token)
            ->asJson()
            ->post('https://emess.cd/api/v0/sms/bulk', [
                'numbers' => $phone,
                'message' => $message,
            ]);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json(),
        ];
    }

    public function sendSingle(string $phone, string $message): array
    {
        return $this->sendBulk([$phone], $message);
    }
    public function sendBulk(array $numbers, string $message): array
    {
        $token = $this->getToken();

        if (! $token) {

            return [
                'success' => false,
                'message' => 'Impossible de récupérer le token'
            ];
        }

        $response = Http::timeout(30)
            ->retry(3, 500)
            ->withToken($token)
            ->asJson()
            ->post('https://emess.cd/api/v0/sms/bulk', [
                'numbers' => $numbers,
                'message' => $message,
            ]);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json(),
        ];
    }
}
