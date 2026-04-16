<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'active',
    'status'
])]
#[Hidden(['password', 'remember_token'])]
#[Appends([
    'role_id',
    'role_name',
    'permissions_list',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function scopeSearch($query, string $term): void
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('users.name', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('email', 'like', $term);
        });
    }

    public static function findUser(string $email, array $selects = ['*']): ?self
    {
        return self::select($selects)
            ->where('email', $email)
            ->first();
    }

    public function getRoleData(): array
    {
        $roles = $this->roles->map(fn($role) => [
            'id'   => $role->id,
            'name' => $role->name,
        ]);
        return $roles->first() ?? [];
    }

    public function getRoleIdAttribute(): ?int
    {
        return $this->getRoleData()['id'] ?? null;
    }
    public function getRoleNameAttribute(): ?string
    {
        return $this->getRoleData()['name'] ?? null;
    }

    public function getPermissionsListAttribute(): array
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->toArray();
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branche::class);
    }
}
