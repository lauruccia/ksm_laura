<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('header_variant')->nullable();
            $table->string('header_background', 7)->nullable();
            $table->string('header_color', 7)->nullable();
            $table->string('header_accent', 7)->nullable();
            $table->string('header_tagline')->nullable();
            $table->string('header_subline')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('domains', fn (Blueprint $table) => $table->dropColumn([
            'header_variant', 'header_background', 'header_color', 'header_accent', 'header_tagline', 'header_subline',
        ]));
    }
};
