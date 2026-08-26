<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_publications', function (Blueprint $table): void {
            $table->string('media_kind', 16)
                ->nullable()
                ->after('type');

            $table->json('options')
                ->nullable()
                ->after('caption');

            $table->timestamp('scheduled_at')
                ->nullable()
                ->index()
                ->after('status');

            $table->timestamp('processing_started_at')
                ->nullable()
                ->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_publications', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_at']);

            $table->dropColumn([
                'media_kind',
                'options',
                'scheduled_at',
                'processing_started_at',
            ]);
        });
    }
};
