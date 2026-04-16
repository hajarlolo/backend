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
        Schema::create('postulation_stages', function (Blueprint $table) {
            $table->unsignedBigInteger('id_offre_stage');
            $table->unsignedBigInteger('id_candidat');
            $table->timestamp('date_postulation')->nullable();
            $table->enum('statut', ['en_attente', 'acceptée', 'refusée'])->default('en_attente');
            $table->json('documents')->nullable();

            $table->primary(['id_offre_stage', 'id_candidat']);
            $table->foreign('id_offre_stage')->references('id_offre_stage')->on('offre_stages')->onDelete('cascade');
            $table->foreign('id_candidat')->references('id_candidat')->on('candidats')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postulation_stages');
    }
};
