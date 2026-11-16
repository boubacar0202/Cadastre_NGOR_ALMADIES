<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arrivees', function (Blueprint $table) {
            $table->index(['deleted_at', 'dt_datearrivee']);
            $table->index(['txt_categorie', 'dt_date']);
            $table->index('txt_designation');
        });
    }

    public function down(): void
    {
        Schema::table('arrivees', function (Blueprint $table) {
            $table->dropIndex(['deleted_at', 'dt_datearrivee']);
            $table->dropIndex(['txt_categorie', 'dt_date']);
            $table->dropIndex(['txt_designation']);
        });
    }
};
