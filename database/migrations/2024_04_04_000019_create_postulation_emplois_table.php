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
        Schema::create('postulation_emplois', function (Blueprint $table) {
            $table->unsignedBigInteger('id_offre_emploi');
            $table->unsignedBigInteger('id_candidat');
            $table->timestamp('date_postulation')->nullable();
            $table->enum('statut', ['en_attente', 'acceptée', 'refusée'])->default('en_attente');
            $table->json('documents')->nullable();

            $table->primary(['id_offre_emploi', 'id_candidat']);
            $table->foreign('id_offre_emploi')->references('id_offre_emploi')->on('offre_emplois')->onDelete('cascade');
            $table->foreign('id_candidat')->references('id_candidat')->on('candidats')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postulation_emplois');
    }
};
