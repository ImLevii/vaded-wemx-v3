<?php

use Database\Seeders\KnowledgebaseSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['permission' => 'admin.knowledgebase.manage'],
            ['description' => 'Manage knowledgebase categories and articles']
        );

        (new KnowledgebaseSeeder)->run();
    }

    public function down(): void
    {
        DB::table('permissions')->where('permission', 'admin.knowledgebase.manage')->delete();
    }
};
