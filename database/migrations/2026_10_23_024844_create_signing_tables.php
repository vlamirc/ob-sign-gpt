<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->string('original_name');
            $table->string('disk');
            $table->string('path');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->string('status')->default('draft');
            $table->text('consent_text')->nullable();
            $table->string('consent_version')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->string('evidence_path')->nullable();
            $table->char('evidence_sha256', 64)->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('signers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('email');
            $table->char('token_hash', 64)->nullable()->unique();
            $table->string('otp_hash')->nullable();
            $table->timestampTz('otp_expires_at')->nullable();
            $table->timestampTz('otp_sent_at')->nullable();
            $table->unsignedSmallInteger('otp_attempts')->default(0);
            $table->char('grant_hash', 64)->nullable();
            $table->timestampTz('grant_expires_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('viewed_at')->nullable();
            $table->timestampTz('signed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['document_id', 'email']);
        });
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('document_id')->constrained()->restrictOnDelete();
            $table->uuid('signer_id')->nullable();
            $table->string('type');
            $table->text('payload');
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64);
            $table->timestampTz('recorded_at');
            $table->index(['document_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('signers');
        Schema::dropIfExists('documents');
    }
};
