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
        Schema::create('mission_freelance_competence', function (Blueprint $table) {
            $table->unsignedBigInteger('id_mission');
            $table->unsignedBigInteger('id_competence');

            $table->primary(['id_mission', 'id_competence']);
            $table->foreign('id_mission')->references('id_mission')->on('mission_freelances')->onDelete('cascade');
            $table->foreign('id_competence')->references('id_competence')->on('competences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_freelance_competence');
    }
};
