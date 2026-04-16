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
        Schema::create('projets', function (Blueprint $table) {
            $table->id('id_projet');
            $table->unsignedBigInteger('id_candidat');
            $table->string('titre', 180);
            $table->text('description')->nullable();
            $table->string('lien_demo')->nullable();
            $table->string('lien_code')->nullable();
            $table->string('image_apercu')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();

            $table->foreign('id_candidat')->references('id_candidat')->on('candidats')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projets');
    }
};
