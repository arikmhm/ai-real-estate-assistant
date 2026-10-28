<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('phone_e164', 20);
            $table->string('source', 100);
            $table->string('stage', 20)->default('baru');
            $table->date('followup_on')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'stage', 'followup_on']);
            $table->index(['listing_id', 'phone_e164']);
        });

        Schema::create('prospect_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->text('body')->nullable();
            $table->json('details')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['prospect_id', 'created_at']);
        });

        Schema::create('visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->restrictOnDelete();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('scheduled_at');
            $table->string('status', 20)->default('dijadwalkan');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['scheduled_at', 'status']);
            $table->index(['prospect_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
        Schema::dropIfExists('prospect_activities');
        Schema::dropIfExists('prospects');
    }
};
