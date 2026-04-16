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
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('user_type', ['admin', 'etudiant', 'lauriat', 'entreprise']);
            $table->enum('action_type', [
                'login', 
                'logout',
                'register', 
                'update_profile', 
                'apply_offer', 
                'create_offer', 
                'close_offer', 
                'accept_user', 
                'reject_user', 
                'request_revision', 
                'resubmit_document', 
                'send_message'
            ]);
            $table->enum('target_type', ['user', 'offer', 'application', 'verification', 'message'])->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('action_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
