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
        Schema::create('offre_emploi_competence', function (Blueprint $table) {
            $table->unsignedBigInteger('id_offre_emploi');
            $table->unsignedBigInteger('id_competence');

            $table->primary(['id_offre_emploi', 'id_competence']);
            $table->foreign('id_offre_emploi')->references('id_offre_emploi')->on('offre_emplois')->onDelete('cascade');
            $table->foreign('id_competence')->references('id_competence')->on('competences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offre_emploi_competence');
    }
};
