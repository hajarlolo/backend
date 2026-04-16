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
        Schema::create('offre_emplois', function (Blueprint $table) {
            $table->id('id_offre_emploi');
            $table->unsignedBigInteger('id_entreprise');
            $table->string('poste', 180);
            $table->text('description')->nullable();
            $table->text('document_requise')->nullable();
            $table->string('experience_requise', 120)->nullable();
            $table->string('adresse')->nullable();
            $table->decimal('salaire', 12, 2)->nullable();
            $table->enum('statut', ['publie', 'ferme'])->default('publie');
            $table->timestamp('date_publication')->nullable();
            $table->timestamps();

            $table->foreign('id_entreprise')->references('id_entreprise')->on('entreprises')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offre_emplois');
    }
};
