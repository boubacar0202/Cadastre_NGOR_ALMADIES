<?php

namespace App\Services;

use App\Repositories\UserRepository;

class AgentInscritService
{
    public function __construct(private UserRepository $utilisateurs) {}

    /**
     * @return list<string>
     */
    public function noms(): array
    {
        return $this->utilisateurs->noms();
    }
}
