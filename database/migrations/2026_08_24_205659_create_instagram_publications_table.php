<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_publications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('instagram_account_id')
                ->constrained('instagram_accounts')
                ->cascadeOnDelete();

            $table->string('type', 32)
                ->default('image');

            $table->text('caption')
                ->nullable();

            $table->string('staging_path')
                ->nullable();

            $table->string('container_id')
                ->nullable()
                ->index();

            $table->string('media_id')
                ->nullable()
                ->index();

            $table->string('status', 32)
                ->default('pending')
                ->index();

            $table->text('error_message')
                ->nullable();

            $table->timestamp('published_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_publications');
    }
};
