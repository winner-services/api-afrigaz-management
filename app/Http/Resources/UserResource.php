<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'email'     => $this->email,
            'phone'     => $this->phone,
            'status'     => $this->status,
            'is_active'    => $this->active ? 'active' : 'disabled',
            'roles'     => $this->roles->pluck('name'),
            'role_id'    => $this->roles->pluck('id'),
            'permissions' => $this->permissions_list,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
