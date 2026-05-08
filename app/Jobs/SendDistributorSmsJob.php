<?php

namespace App\Jobs;

use App\Models\CategoryDistributor;
use App\Models\Distributor;
use App\Services\EmessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDistributorSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(
        public int $distributorId
    ) {}

    public function handle(EmessService $emess): void
    {
        $distributor = Distributor::find($this->distributorId);
        $categoty = CategoryDistributor::find($distributor->category_distributor_id);

        if (! $distributor || ! $distributor->phone) {
            return;
        }
        $emess->sendSingle(
            $distributor->phone,
            "Bonjour {$distributor->name}, votre compte dans la catégorie {$categoty->designation} a été créé avec succès. Pour finaliser votre inscription et programmer votre première livraison, nous vous prions de passer à la caisse pour le règlement de la caution."
        );
    }
}
