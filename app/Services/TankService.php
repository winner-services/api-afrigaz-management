<?php

namespace App\Services;

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

    public function addGas($tankId, $qty)
    {
        return DB::transaction(function () use ($tankId, $qty) {

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
                'note' => 'Achat gaz'
            ]);

            return $tank;
        });
    }

    public function consumeGas($tankId, $qty, $referenceType = null, $referenceId = null)
    {
        return DB::transaction(function () use ($tankId, $qty, $referenceType, $referenceId) {

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
                'note' => 'Remplissage des bouteilles'
            ]);

            return $tank;
        });
    }

    public function adjust($tankId, $qty, $type)
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
            'note' => 'Ajustement quantité manuel'
        ]);

        return $tank;
    }
}
