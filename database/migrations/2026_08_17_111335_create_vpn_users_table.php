<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_users', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 100);

            $table
                ->uuid('uuid')
                ->unique();

            $table
                ->string('xray_email', 190)
                ->unique();

            $table
                ->boolean('is_active')
                ->default(true);

            $table
                ->string('flow', 50)
                ->default('xtls-rprx-vision');

            $table
                ->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index([
                'is_active',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_users');
    }
};
