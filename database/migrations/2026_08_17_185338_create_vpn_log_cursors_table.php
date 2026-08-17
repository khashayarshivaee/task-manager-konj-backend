<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_log_cursors', function (Blueprint $table): void {
            $table->id();

            $table
                ->string('source', 100)
                ->unique();

            $table
                ->string('path', 500);

            $table
                ->unsignedBigInteger('offset')
                ->default(0);

            $table
                ->unsignedBigInteger('inode')
                ->nullable();

            $table
                ->timestamp('last_processed_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'vpn_log_cursors',
        );
    }
};
