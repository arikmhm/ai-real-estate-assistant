<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable()->unique();
            $table->string('title');
            $table->string('type', 16);
            $table->unsignedBigInteger('price_idr');
            $table->string('province');
            $table->string('city');
            $table->string('district')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('land_area_m2', 10, 2)->nullable();
            $table->decimal('building_area_m2', 10, 2)->nullable();
            $table->unsignedSmallInteger('rooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->json('facilities')->nullable();
            $table->text('description')->nullable();
            $table->string('publication_status', 16)->default('draft');
            $table->string('availability_status', 16)->default('available');
            $table->timestamps();
            $table->index(['publication_status', 'availability_status', 'created_at']);
        });

        Schema::create('listing_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('disk', 32);
            $table->string('object_key');
            $table->string('mime_type', 100);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['disk', 'object_key']);
            $table->index(['listing_id', 'kind', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_media');
        Schema::dropIfExists('listings');
    }
};
