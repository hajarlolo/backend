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
        Schema::create('mission_freelances', function (Blueprint $table) {
            $table->id('id_mission');
            $table->unsignedBigInteger('id_entreprise');
            $table->string('titre', 180);
            $table->text('description')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->integer('duree_days')->nullable();
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
        Schema::dropIfExists('mission_freelances');
    }
};
