<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->string('gelar_depan')->nullable()->after('name'); // mis. "Dr."
            $table->string('gelar_belakang')->nullable()->after('gelar_depan'); // mis. "M.Kom"
            $table->string('no_wa')->nullable()->after('expertise_field');
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn(['gelar_depan', 'gelar_belakang', 'no_wa']);
        });
    }
};
