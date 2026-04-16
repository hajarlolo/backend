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
        Schema::create('certificats', function (Blueprint $table) {
            $table->id('id_certificat');
            $table->unsignedBigInteger('id_candidat');
            $table->string('titre');
            $table->string('organisme')->nullable();
            $table->date('date_obtention')->nullable();
            $table->string('certificat_document')->nullable();
            $table->timestamps();

            $table->foreign('id_candidat')->references('id_candidat')->on('candidats')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificats');
    }
};
