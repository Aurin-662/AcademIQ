<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('employer')->after('email');
            $table->string('institution_code')->nullable()->after('role');
            $table->string('organization')->nullable()->after('institution_code');
            $table->boolean('approved')->default(false)->after('organization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'institution_code', 'organization', 'approved']);
        });
    }
};
