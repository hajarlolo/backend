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
        Schema::create('projet_technologie', function (Blueprint $table) {
            $table->unsignedBigInteger('id_projet');
            $table->unsignedBigInteger('id_competence');

            $table->primary(['id_projet', 'id_competence']);
            $table->foreign('id_projet')->references('id_projet')->on('projets')->onDelete('cascade');
            $table->foreign('id_competence')->references('id_competence')->on('competences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projet_technologie');
    }
};
