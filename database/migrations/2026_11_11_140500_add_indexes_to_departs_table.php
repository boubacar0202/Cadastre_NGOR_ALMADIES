<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departs', function (Blueprint $table) {
            $table->index('dt_datecouriercd');
            $table->index('txt_categoriecd');
            $table->index('txt_referencecourierarriveecd');
        });
    }

    public function down(): void
    {
        Schema::table('departs', function (Blueprint $table) {
            $table->dropIndex(['dt_datecouriercd']);
            $table->dropIndex(['txt_categoriecd']);
            $table->dropIndex(['txt_referencecourierarriveecd']);
        });
    }
};
