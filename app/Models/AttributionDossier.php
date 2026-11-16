<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributionDossier extends Model
{
    public const STATUT_ATTRIBUE = 'attribue';

    public const STATUT_TRAITE = 'traite';

    public const STATUT_SIGNE = 'signe';

    public const DELAI_JOURS = 10;

    public const TENTATIVES_MAX = 5;

    protected $table = 'attributions_dossiers';

    protected $fillable = [
        'origine',
        'dossier_id',
        'arrivee_id',
        'depart_id',
        'user_id',
        'attribue_par',
        'signe_par',
        'statut',
        'date_attribution',
        'date_echeance',
        'date_traitement',
        'date_signature',
        'rappel_envoye_at',
        'tentatives_nicad',
        'verrouille_jusqua',
        'journal',
    ];

    protected $casts = [
        'date_attribution' => 'datetime',
        'date_echeance' => 'datetime',
        'date_traitement' => 'datetime',
        'date_signature' => 'datetime',
        'rappel_envoye_at' => 'datetime',
        'verrouille_jusqua' => 'datetime',
        'tentatives_nicad' => 'integer',
    ];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function arrivee(): BelongsTo
    {
        return $this->belongsTo(Arrivee::class);
    }

    public function depart(): BelongsTo
    {
        return $this->belongsTo(Depart::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attribue_par');
    }

    public function signataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signe_par');
    }

    public function estEnRetard(): bool
    {
        return $this->statut === self::STATUT_ATTRIBUE
            && $this->date_echeance !== null
            && $this->date_echeance->lt(now());
    }

    public function estVerrouille(): bool
    {
        return $this->verrouille_jusqua !== null && $this->verrouille_jusqua->isFuture();
    }

    public function noter(string $ligne): void
    {
        $entree = now()->format('d/m/Y H:i').' — '.$ligne;
        $this->journal = trim(($this->journal ? $this->journal."\n" : '').$entree);
    }

    public function joursRestants(): int
    {
        if ($this->date_echeance === null) {
            return 0;
        }

        return intdiv($this->date_echeance->getTimestamp() - now()->getTimestamp(), 86400);
    }
}
