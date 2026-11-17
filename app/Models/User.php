<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'fonction',
        'is_validated',
    ];

    public const FONCTIONS = [
        'Chef de Bureau',
        'Géomètre',
        'Agent',
        'Particulier',
        'Secrétaire',
    ];

    public const FONCTION_CHEF_DE_BUREAU = 'Chef de Bureau';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'is_validated' => 'boolean',
        ];
    }

    /**
     * Ramène un libellé saisi (admin, Administrateur, utilisateur…) à la valeur stockée.
     */
    public function estProtege(): bool
    {
        return (int) $this->id === 4
            && strcasecmp(trim((string) $this->email), 'mbengueboubacar095@gmail.com') === 0;
    }

    public static function chefDeBureau(): ?self
    {
        return static::query()
            ->where('role', 'admin')
            ->where('fonction', self::FONCTION_CHEF_DE_BUREAU)
            ->orderBy('id')
            ->first();
    }

    public function libererAutreChefDeBureau(): void
    {
        if ($this->fonction !== self::FONCTION_CHEF_DE_BUREAU) {
            return;
        }

        static::query()
            ->where('id', '!=', $this->id)
            ->where('fonction', self::FONCTION_CHEF_DE_BUREAU)
            ->update(['fonction' => null]);
    }

    public static function normalizeRole(mixed $role): string
    {
        $value = strtolower(trim((string) $role));

        return match ($value) {
            'admin', 'administrateur' => 'admin',
            'user', 'utilisateur' => 'user',
            default => $value,
        };
    }
}
