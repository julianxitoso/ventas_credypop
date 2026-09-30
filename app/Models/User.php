<?php

namespace App\Models;

use App\Concerns\HasTeams;
use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $usuario
 * @property Rol $rol
 * @property bool $activo
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read int|null $ventas_count
 */
#[Fillable(['name', 'usuario', 'rol', 'activo', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'rol' => Rol::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * Ventas registradas por el usuario como asesor.
     *
     * @return HasMany<Venta, $this>
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'asesor_id');
    }

    public function esAsesor(): bool
    {
        return $this->rol === Rol::Asesor;
    }

    public function puedeFacturar(): bool
    {
        return $this->rol === Rol::Facturador;
    }

    public function puedeVerDashboard(): bool
    {
        return in_array($this->rol, [Rol::Gerente, Rol::Admin], true);
    }

    public function puedeVerTodo(): bool
    {
        return in_array($this->rol, [Rol::Facturador, Rol::Admin], true);
    }

    public function esAdmin(): bool
    {
        return $this->rol === Rol::Admin;
    }

    /**
     * Pantalla a la que llega el usuario después de iniciar sesión.
     */
    public function rutaDeInicio(): string
    {
        return match (true) {
            $this->esAsesor() => route('ventas.create'),
            $this->puedeVerDashboard() => route('resumen'),
            default => route('panel'),
        };
    }
}
