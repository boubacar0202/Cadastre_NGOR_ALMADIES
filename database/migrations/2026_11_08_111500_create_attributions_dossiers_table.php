<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributions_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained('dossiers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('attribue_par')->constrained('users')->restrictOnDelete();
            $table->foreignId('signe_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut', 20)->default('attribue')->index();
            $table->dateTime('date_attribution');
            $table->dateTime('date_echeance');
            $table->dateTime('date_traitement')->nullable();
            $table->dateTime('date_signature')->nullable();
            $table->dateTime('rappel_envoye_at')->nullable();
            $table->unsignedTinyInteger('tentatives_nicad')->default(0);
            $table->dateTime('verrouille_jusqua')->nullable();
            $table->text('journal')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'statut']);
            $table->index(['statut', 'date_echeance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributions_dossiers');
    }
};
