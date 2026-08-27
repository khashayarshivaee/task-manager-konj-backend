<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'instagram_publication_media_items',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('instagram_publication_id')
                    ->constrained('instagram_publications')
                    ->cascadeOnDelete();

                $table->string('media_kind', 32)
                    ->default('image');

                $table->string('staging_path')
                    ->nullable();
                $table->unsignedSmallInteger('position');

                $table->string('container_id')
                    ->nullable()
                    ->index();

                $table->string('container_status', 32)
                    ->nullable();

                $table->text('error_message')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'instagram_publication_id',
                    'position',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'instagram_publication_media_items'
        );
    }
};
