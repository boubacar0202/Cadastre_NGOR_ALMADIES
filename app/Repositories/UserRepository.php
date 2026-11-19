<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    /**
     * Noms des utilisateurs inscrits.
     * Le mot de passe, l’e-mail et l’identifiant ne sortent pas.
     *
     * @return list<string>
     */
    public function noms(): array
    {
        return User::query()
            ->select('name')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($nom) => trim((string) $nom))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
