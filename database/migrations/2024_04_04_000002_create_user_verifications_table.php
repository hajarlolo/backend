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
        Schema::create('user_verifications', function (Blueprint $table) {
            $table->id('id_verification');
            $table->unsignedBigInteger('id_user');
            $table->string('verification_code', 6)->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->string('verification_document')->nullable();
            $table->enum('status', ['pending_email', 'pending_document', 'revision_required', 'approved', 'rejected'])->default('pending_email');
            $table->text('status_note')->nullable();
            $table->timestamps();

            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_verifications');
    }
};
