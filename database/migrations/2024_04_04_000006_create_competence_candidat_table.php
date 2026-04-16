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
        Schema::create('competence_candidat', function (Blueprint $table) {
            $table->unsignedBigInteger('id_candidat');
            $table->unsignedBigInteger('id_competence');
            $table->timestamp('created_at')->nullable();

            $table->primary(['id_candidat', 'id_competence']);
            $table->foreign('id_candidat')->references('id_candidat')->on('candidats')->onDelete('cascade');
            $table->foreign('id_competence')->references('id_competence')->on('competences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competence_candidat');
    }
};
