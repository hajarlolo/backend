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
        Schema::table('formations', function (Blueprint $table) {
            $table->dropForeign(['id_universite']);
            $table->dropColumn('id_universite');
            $table->string('etablissement', 150)->nullable()->after('filiere');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->dropColumn('etablissement');
            $table->integer('id_universite')->unsigned()->nullable()->after('filiere');
            $table->foreign('id_universite')->references('id_universite')->on('universites')->onDelete('set null');
        });
    }
};
