<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrated_marketplace_installations', function (Blueprint $table) {
            $table->string('latest_version')->nullable()->after('version');
            $table->boolean('update_available')->default(false)->after('latest_version');
            $table->timestamp('update_checked_at')->nullable()->after('update_available');
        });
    }

    public function down(): void
    {
        Schema::table('integrated_marketplace_installations', function (Blueprint $table) {
            $table->dropColumn(['latest_version', 'update_available', 'update_checked_at']);
        });
    }
};
