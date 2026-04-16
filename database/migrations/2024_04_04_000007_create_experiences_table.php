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
        Schema::create('experiences', function (Blueprint $table) {
            $table->id('id_experience');
            $table->unsignedBigInteger('id_candidat');
            $table->enum('type', ['stage', 'emploi', 'freelance']);
            $table->string('titre', 180);
            $table->string('entreprise_nom', 150)->nullable();
            $table->text('description')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->boolean('en_cours')->default(false);
            $table->timestamps();

            $table->foreign('id_candidat')->references('id_candidat')->on('candidats')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
