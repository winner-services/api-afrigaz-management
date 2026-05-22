<?php

namespace App\Jobs;

use App\Models\Distributor;
use App\Models\CategoryDistributor;
use App\Services\EmessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDistributorSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(

        public int $distributorId,

        public string $type = 'registration'

    ) {}

    public function handle(EmessService $emess): void
    {
        $distributor = Distributor::find($this->distributorId);

        if (! $distributor || ! $distributor->phone) {
            return;
        }

        $category = CategoryDistributor::find(
            $distributor->category_distributor_id
        );

        $message = match ($this->type) {

            'registration' =>

            "Bonjour {$distributor->name}, votre compte AFRIGAZ EXPRESS dans la catégorie {$category?->designation} a été créé avec succès. Veuillez passer à la caisse pour le paiement de la caution afin d'activer votre compte.",

            'payment' =>

            "Bonjour {$distributor->name}, votre paiement a été reçu avec succès. AFRIGAZ EXPRESS vous remercie pour votre confiance.",

            'order_confirmed' =>

            "Bonjour {$distributor->name}, votre commande a été confirmée avec succès. AFRIGAZ EXPRESS vous remercie.",

            default =>

            "Bonjour {$distributor->name}, merci d'utiliser AFRIGAZ EXPRESS."
        };

        $emess->sendSingle(
            $this->formatPhone($distributor->phone),
            $message
        );
        // $emess->sendSingle(
        //     $distributor->phone,
        //     $message
        // );
    }
    private function formatPhone($phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        if (str_starts_with($phone, '+243')) {
            return $phone;
        }

        if (str_starts_with($phone, '243')) {
            return '+' . $phone;
        }

        if (str_starts_with($phone, '0')) {
            return '+243' . substr($phone, 1);
        }

        return $phone;
    }
}

// namespace App\Jobs;

// use App\Models\CategoryDistributor;
// use App\Models\Distributor;
// use App\Services\EmessService;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Bus\Dispatchable;
// use Illuminate\Foundation\Queue\Queueable;
// use Illuminate\Queue\InteractsWithQueue;
// use Illuminate\Queue\SerializesModels;

// class SendDistributorSmsJob implements ShouldQueue
// {
//     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

//     public int $tries = 5;

//     public int $timeout = 30;

//     public function __construct(
//         public int $distributorId
//     ) {}

//     public function handle(EmessService $emess): void
//     {
//         $distributor = Distributor::find($this->distributorId);
//         $categoty = CategoryDistributor::find($distributor->category_distributor_id);

//         if (! $distributor || ! $distributor->phone) {
//             return;
//         }
//         $emess->sendSingle(
//             $distributor->phone,
//             "Bonjour {$distributor->name} Distributeur AFRIGAZ EXPRESS, votre compte dans la catégorie {$categoty->designation} a été créé avec succès. Pour finaliser votre inscription et programmer votre première livraison, nous vous prions de passer à la caisse pour le règlement de la caution."
//         );
//     }
// }
