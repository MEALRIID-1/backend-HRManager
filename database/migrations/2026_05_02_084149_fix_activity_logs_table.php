<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('module')->nullable()->change();
            $table->string('entity_name')->nullable()->after('module');
            $table->text('old_value')->nullable()->after('entity_name');
            $table->text('new_value')->nullable()->after('old_value');
            $table->timestamp('timestamp')->nullable()->after('new_value');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('module')->nullable(false)->change();
            $table->dropColumn(['entity_name', 'old_value', 'new_value', 'timestamp']);
        });
    }
};