<?php

namespace Database\Seeders;

use App\Models\Commune;
use App\Models\Region;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Territoire du bureau du Cadastre de Ngor Almadies :
     * région de Dakar, département de Dakar, arrondissement des Almadies.
     * Les codes commune restent vides : ils servent de préfixe NICAD
     * et doivent être ceux officiellement utilisés par le bureau.
     */
    public function run(): void
    {
        $region = Region::firstOrCreate(['slt_region' => 'Dakar']);

        $dakar = $region->departements()->firstOrCreate(['slt_departement' => 'Dakar']);

        $almadies = $dakar->arrondissements()->firstOrCreate(['slt_arrondissement' => 'Almadies']);

        $communes = [
            'Ngor',
            'Ouakam',
            'Yoff',
            'Mermoz-Sacré-Cœur',
        ];

        foreach ($communes as $nom) {
            Commune::unguarded(function () use ($almadies, $nom) {
                $almadies->communes()->firstOrCreate(
                    ['slt_commune' => $nom],
                    ['txt_codeCommune' => '']
                );
            });
        }
    }
}
