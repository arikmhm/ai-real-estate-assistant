<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_status_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('field', 32);
            $table->string('from_value', 32);
            $table->string('to_value', 32);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['listing_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_status_changes');
    }
};
