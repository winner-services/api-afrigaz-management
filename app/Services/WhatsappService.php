<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    public static function send($message)
    {
        try {

            $response = Http::asForm()->post(

                env('ULTRAMSG_URL'),

                [

                    'token' => env('ULTRAMSG_TOKEN'),

                    'to' => env('ADMIN_WHATSAPP'),

                    'body' => $message

                ]

            );

            return $response->json();
        } catch (\Throwable $e) {

            Log::error('WhatsApp Error', [

                'message' => $e->getMessage()

            ]);

            return false;
        }
    }
}
