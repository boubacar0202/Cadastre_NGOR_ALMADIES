<?php

namespace App\Rules;

use App\Models\Lotissement;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LotissementConnu implements ValidationRule
{
    /** @var list<string> */
    private array $valeursConservees;

    /**
     * @param  list<string|null>  $valeursConservees  Libellés déjà portés par ce courrier ou par l'arrivée liée.
     */
    public function __construct(array $valeursConservees = [])
    {
        $this->valeursConservees = array_values(array_filter(
            $valeursConservees,
            fn ($valeur) => is_string($valeur) && $valeur !== ''
        ));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (in_array($value, $this->valeursConservees, true)) {
            return;
        }

        $connu = Lotissement::query()
            ->where('txt_denomination', $value)
            ->exists();

        if (! $connu) {
            $fail("Le lotissement choisi n'existe pas.");
        }
    }
}
