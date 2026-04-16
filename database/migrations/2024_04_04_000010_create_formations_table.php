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
        Schema::create('formations', function (Blueprint $table) {
            $table->id('id_formation');
            $table->unsignedBigInteger('id_candidat');
            $table->string('diplome', 150)->nullable();
            $table->string('filiere', 150)->nullable();
            $table->integer('id_universite')->unsigned()->nullable();
            $table->enum('niveau', ['bac', 'bac+2', 'licence', 'master', 'doctorat', 'autre'])->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->boolean('en_cours')->default(false);
            $table->timestamps();

            $table->foreign('id_candidat')->references('id_candidat')->on('candidats')->onDelete('cascade');
            $table->foreign('id_universite')->references('id_universite')->on('universites')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
