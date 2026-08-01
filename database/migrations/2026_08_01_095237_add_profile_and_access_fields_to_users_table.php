<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add profile and access fields to the users table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table
                ->string('role', 30)
                ->default('user')
                ->index()
                ->after('password');

            $table
                ->boolean('is_active')
                ->default(true)
                ->index()
                ->after('role');

            $table
                ->string('avatar_path')
                ->nullable()
                ->after('is_active');

            $table
                ->timestamp('last_login_at')
                ->nullable()
                ->after('avatar_path');

            $table
                ->string('last_login_ip', 45)
                ->nullable()
                ->after('last_login_at');
        });
    }

    /**
     * Remove profile and access fields from the users table.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'is_active',
                'avatar_path',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
