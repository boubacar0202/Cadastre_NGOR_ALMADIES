<?php

namespace App\Console\Commands;

use App\Services\AttributionDossierService;
use Illuminate\Console\Command;

class RappelerAttributionsEnRetard extends Command
{
    protected $signature = 'attributions:rappeler';

    protected $description = 'Envoie un rappel pour les dossiers attribués depuis plus de 10 jours';

    public function handle(AttributionDossierService $service): int
    {
        $envoyes = $service->rappelerEchus();
        $this->info($envoyes.' rappel(s) envoyé(s).');

        return self::SUCCESS;
    }
}
