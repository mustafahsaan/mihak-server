<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            // يفرّق بين رمز استُخدم فعلاً ورمز أُلغي لأن صاحبه طلب رمزاً أحدث
            $table->dateTime('verified_at')->nullable()->after('consumed_at');
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropColumn('verified_at');
        });
    }
};
