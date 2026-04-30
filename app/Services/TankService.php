<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductLedger;
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

    public function addGas($tankId, $qty, $operation_date, $unit_price)
    {
        return DB::transaction(function () use ($tankId, $qty, $operation_date, $unit_price) {

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
                'note' => 'Approvisionnement du gaz',
                'operation_date' => $operation_date
            ]);

            $gaz = Product::where('category_id', 1)->firstOrFail();

            $stock = StockByBranch::firstOrCreate([
                'branche_id' => 1,
                'product_id' => $gaz->id,
            ], [
                'stock_quantity' => 0,
                'status' => 'created'
            ]);
            $stockBefore = $stock->stock_quantity;
            $stockAfter = $stockBefore + $qty;

            $stock->increment('stock_quantity', $qty);

            ProductLedger::create([
                'product_id' => $gaz->id,
                'branch_id' => 1,
                'operation_date' => $operation_date,
                'type' => 'purchase',

                'quantity' => $qty,
                'unit_price' => $unit_price,
                'condition_state' => 'good',
                'is_empty' => 0,
                'movement' => 'in',

                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,

                'reference_type' => 'tank',
                'reference_id' => $tank->id,

                'notes' => 'Entrée gaz via tank',

                'addedBy' => Auth::id(),
                'status' => 'posted',
            ]);
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

            $operation_date = $operation_date ?? now();

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

            $gaz = Product::where('category_id', 1)->firstOrFail();

            $stock = StockByBranch::firstOrCreate([
                'branche_id' => 1,
                'product_id' => $gaz->id,
            ], [
                'stock_quantity' => 0,
                'status' => 'created'
            ]);

            $stockBefore = $stock->stock_quantity;
            $stockAfter = $stockBefore - $qty;

            $stock->decrement('stock_quantity', $qty);

            ProductLedger::create([
                'product_id' => $gaz->id,
                'branch_id' => $tank->branch_id ?? 1,
                'operation_date' => $operation_date,
                'type' => 'sale',
                'movement' => 'out',
                'is_empty' => 0,
                'condition_state' => 'good',
                'unit_price' => $gaz->wholesale_price,
                'quantity' => -$qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference_type' => $referenceType ?? 'tank_consumption',
                'reference_id' => $referenceId ?? $tank->id,
                'notes' => 'Consommation gaz (remplissage bouteilles)',
                'addedBy' => Auth::id(),
                'status' => 'posted',
            ]);

            return $tank;
        });
    }

    public function adjust($tankId, $qty, $type, $operation_date = null)
    {
        return DB::transaction(function () use ($tankId, $qty, $type, $operation_date) {

            $tank = Tank::findOrFail($tankId);

            $operation_date = $operation_date ?? now();

            $gaz = Product::where('category_id', 1)->firstOrFail();

            $stock = StockByBranch::firstOrCreate([
                'branche_id' => $tank->branch_id ?? 1,
                'product_id' => $gaz->id,
            ], [
                'stock_quantity' => 0,
                'status' => 'created'
            ]);

            $stockBefore = $stock->stock_quantity;

            if ($type === 'augmentation') {

                if (($tank->current_level + $qty) > $tank->capacity) {
                    throw new \Exception("Capacité dépassée");
                }

                $tank->increment('current_level', $qty);

                $stockAfter = $stockBefore + $qty;
                $stock->increment('stock_quantity', $qty);

                $ledgerType = 'adjustment_in';
            } else {

                if ($tank->current_level < $qty) {
                    throw new \Exception("Niveau insuffisant");
                }

                $tank->decrement('current_level', $qty);

                $stockAfter = $stockBefore - $qty;
                $stock->decrement('stock_quantity', $qty);

                $ledgerType = 'adjustment_out';
            }

            TankMovement::create([
                'tank_id' => $tank->id,
                'type' => 'adjustment',
                'quantity' => $qty,
                'addedBy' => Auth::id(),
                'note' => 'Ajustement quantité',
                'operation_date' => $operation_date
            ]);

            ProductLedger::create([
                'product_id' => $gaz->id,
                'branch_id' => $tank->branch_id ?? 1,
                'operation_date' => $operation_date,
                'type' => $ledgerType,

                'quantity' => $type === 'augmentation' ? $qty : -$qty,

                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,

                'reference_type' => 'tank_adjustment',
                'reference_id' => $tank->id,

                'notes' => 'Ajustement quantité du tank',

                'addedBy' => Auth::id(),
                'status' => 'posted',
            ]);

            return $tank;
        });
    }
}
