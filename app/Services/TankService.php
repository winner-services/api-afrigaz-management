<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockByBranch;
use App\Models\Tank;
use App\Models\TankMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TankService
{
    public function createTank(array $data)
    {
        $data['addedBy'] = Auth::id();
        return Tank::create($data);
    }

    public function addGas($tankId, $qty, $operation_date)
    {
        return DB::transaction(function () use ($tankId, $qty, $operation_date) {

            $tank = Tank::findOrFail($tankId);

            if (($tank->current_level + $qty) > $tank->capacity) {
                throw new \Exception("Capacité dépassée");
            }

            $tank->increment('current_level', $qty);

            TankMovement::create([
                'tank_id' => $tank->id,
                'type' => 'entry',
                'quantity' => $qty,
                'addedBy' => Auth::id(),
                'note' => 'Achat gaz',
                'operation_date' => $operation_date
            ]);

            $gaz = Product::where('category_id', 1)->first();

            $stock = StockByBranch::firstOrCreate([
                'branche_id' => 1,
                'product_id' => $gaz->id,
            ], [
                'stock_quantity' => 0,
                'status' => 'created'
            ]);

            $stock->increment('stock_quantity', $qty);

            return $tank;
        });
    }

    public function consumeGas($tankId, $qty, $referenceType = null, $referenceId = null, $operation_date = null)
    {
        return DB::transaction(function () use ($tankId, $qty, $referenceType, $referenceId, $operation_date) {

            $tank = Tank::findOrFail($tankId);

            if ($tank->current_level < $qty) {
                throw new \Exception("Gaz insuffisant");
            }

            $tank->decrement('current_level', $qty);

            TankMovement::create([
                'tank_id' => $tank->id,
                'type' => 'exit',
                'quantity' => $qty,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'addedBy' => Auth::id(),
                'note' => 'Remplissage des bouteilles',
                'operation_date' => $operation_date
            ]);
            $gaz = Product::where('category_id', 1)->first();
            app(StockService::class)->decreaseStock(1, $gaz->id, $qty, null, null);

            return $tank;
        });
    }

    public function adjust($tankId, $qty, $type, $operation_date = null)
    {
        $tank = Tank::findOrFail($tankId);

        if ($type === 'augmentation') {

            if (($tank->current_level + $qty) > $tank->capacity) {
                throw new \Exception("Capacité dépassée");
            }

            $tank->increment('current_level', $qty);
        } else {

            if ($tank->current_level < $qty) {
                throw new \Exception("Niveau insuffisant");
            }

            $tank->decrement('current_level', $qty);
        }

        TankMovement::create([
            'tank_id' => $tank->id,
            'type' => 'adjustment',
            'quantity' => $qty,
            'addedBy' => Auth::id(),
            'note' => 'Ajustement quantité manuel',
            'operation_date' => $operation_date ?? now()
        ]);

        return $tank;
    }
}
