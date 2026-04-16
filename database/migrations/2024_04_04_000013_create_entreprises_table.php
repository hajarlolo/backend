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
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id('id_entreprise');
            $table->unsignedBigInteger('id_user')->unique();
            $table->string('ice')->nullable();
            $table->string('email_professionnel')->nullable();
            $table->string('localisation')->nullable();
            $table->text('description')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('secteur_activite', 120)->nullable();
            $table->enum('taille', ['TPE', 'PME', 'Grande'])->default('PME');
            $table->string('site_web')->nullable();
            $table->string('logo_url')->nullable();
            $table->timestamps();

            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entreprises');
    }
};
