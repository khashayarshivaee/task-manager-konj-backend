<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_servers', function (Blueprint $table): void {
            $table->id();

            $table->string('slug', 48)->unique();
            $table->string('name', 100);
            $table->string('region', 80)->nullable();

            $table->enum('driver', [
                'local',
                'ssh',
            ]);

            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_servers');
    }
};
