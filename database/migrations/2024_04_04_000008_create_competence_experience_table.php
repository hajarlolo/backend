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
        Schema::create('competence_experience', function (Blueprint $table) {
            $table->unsignedBigInteger('id_experience');
            $table->unsignedBigInteger('id_competence');
            $table->timestamps();

            $table->primary(['id_experience', 'id_competence']);
            $table->foreign('id_experience')->references('id_experience')->on('experiences')->onDelete('cascade');
            $table->foreign('id_competence')->references('id_competence')->on('competences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competence_experience');
    }
};
