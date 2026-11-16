<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributions_dossiers', function (Blueprint $table) {
            $table->dropForeign(['dossier_id']);
        });

        DB::statement('ALTER TABLE attributions_dossiers MODIFY dossier_id BIGINT UNSIGNED NULL');

        Schema::table('attributions_dossiers', function (Blueprint $table) {
            $table->foreign('dossier_id')->references('id')->on('dossiers')->cascadeOnDelete();
            $table->string('origine', 20)->default('dossier')->after('id');
            $table->foreignId('arrivee_id')->nullable()->after('dossier_id')->constrained('arrivees')->cascadeOnDelete();
            $table->foreignId('depart_id')->nullable()->after('arrivee_id')->constrained('departs')->cascadeOnDelete();
            $table->index(['origine', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('attributions_dossiers', function (Blueprint $table) {
            $table->dropIndex(['origine', 'statut']);
            $table->dropConstrainedForeignId('arrivee_id');
            $table->dropConstrainedForeignId('depart_id');
            $table->dropColumn('origine');
            $table->dropForeign(['dossier_id']);
        });

        DB::statement('ALTER TABLE attributions_dossiers MODIFY dossier_id BIGINT UNSIGNED NOT NULL');

        Schema::table('attributions_dossiers', function (Blueprint $table) {
            $table->foreign('dossier_id')->references('id')->on('dossiers')->cascadeOnDelete();
        });
    }
};
