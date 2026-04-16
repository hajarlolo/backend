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
        Schema::create('candidats', function (Blueprint $table) {
            $table->id('id_candidat');
            $table->unsignedBigInteger('id_user')->unique()->nullable();
            $table->integer('universite_id')->unsigned()->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('adresse')->nullable();
            $table->string('lien_portfolio')->nullable();
            $table->string('photo_profil')->nullable();
            $table->string('cv_url')->nullable();
            $table->enum('profile_mode', ['manual', 'cv'])->default('manual');
            $table->timestamps();

            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
            $table->foreign('universite_id')->references('id_universite')->on('universites')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidats');
    }
};
