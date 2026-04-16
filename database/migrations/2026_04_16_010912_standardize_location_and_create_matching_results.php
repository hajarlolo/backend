<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. & 2. Modify candidats table
        Schema::table('candidats', function (Blueprint $table) {
            $table->dropColumn('adresse');
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
        });

        // Modify offre_emplois table
        Schema::table('offre_emplois', function (Blueprint $table) {
            $table->dropColumn('adresse');
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
        });

        // Modify offre_stages table
        Schema::table('offre_stages', function (Blueprint $table) {
            $table->dropColumn('adresse');
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
        });

        // Modify mission_freelances table
        Schema::table('mission_freelances', function (Blueprint $table) {
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
        });

        // 5. Create matching_results table
        Schema::create('matching_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('candidat_id')->nullable();
            $table->unsignedBigInteger('offer_id')->nullable();
            $table->enum('offer_type', ['emploi', 'stage', 'freelance']);
            $table->decimal('score_total', 5, 2)->nullable();
            $table->decimal('score_skills', 5, 2)->nullable();
            $table->decimal('score_experience', 5, 2)->nullable();
            $table->decimal('score_location', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('candidat_id')->references('id_candidat')->on('candidats')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_results');

        Schema::table('mission_freelances', function (Blueprint $table) {
            $table->dropColumn(['country', 'city']);
        });

        Schema::table('offre_stages', function (Blueprint $table) {
            $table->dropColumn(['country', 'city']);
            $table->string('adresse')->nullable();
        });

        Schema::table('offre_emplois', function (Blueprint $table) {
            $table->dropColumn(['country', 'city']);
            $table->string('adresse')->nullable();
        });

        Schema::table('candidats', function (Blueprint $table) {
            $table->dropColumn(['country', 'city']);
            $table->string('adresse')->nullable();
        });
    }
};
