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
        Schema::create('postulation_freelances', function (Blueprint $table) {
            $table->unsignedBigInteger('id_mission');
            $table->unsignedBigInteger('id_candidat');
            $table->timestamp('date_postulation')->nullable();
            $table->enum('statut', ['en_attente', 'acceptée', 'refusée'])->default('en_attente');
            $table->json('documents')->nullable();

            $table->primary(['id_mission', 'id_candidat']);
            $table->foreign('id_mission')->references('id_mission')->on('mission_freelances')->onDelete('cascade');
            $table->foreign('id_candidat')->references('id_candidat')->on('candidats')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postulation_freelances');
    }
};
