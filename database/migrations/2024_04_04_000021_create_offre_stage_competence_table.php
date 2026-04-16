<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offre_stage_competence', function (Blueprint $table) {
            $table->unsignedBigInteger('id_offre_stage');
            $table->unsignedBigInteger('id_competence');

            $table->primary(['id_offre_stage', 'id_competence']);
            $table->foreign('id_offre_stage')->references('id_offre_stage')->on('offre_stages')->onDelete('cascade');
            $table->foreign('id_competence')->references('id_competence')->on('competences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offre_stage_competence');
    }
};
