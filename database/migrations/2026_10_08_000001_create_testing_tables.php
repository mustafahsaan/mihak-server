<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('android_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained('users');
            $table->string('name');
            $table->string('package_name', 190);
            $table->string('opt_in_url', 500)->nullable();
            $table->timestamps();
            $table->unique(['developer_id', 'package_name']);
        });

        // حملة = دورة اختبار مدفوعة واحدة لتطبيق. الشروط المالية تُنسخ إليها عند الإنشاء.
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('android_app_id')->constrained();
            $table->foreignId('parent_campaign_id')->nullable()->constrained('campaigns');
            $table->unsignedTinyInteger('cycle')->default(1);
            $table->string('status', 20)->index(); // unpaid | recruiting | testing | completed | failed | refunded | rerun
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('commission_percent');
            $table->unsignedInteger('share');
            $table->unsignedSmallInteger('slots');
            $table->unsignedSmallInteger('required');
            $table->unsignedSmallInteger('days');
            $table->unsignedTinyInteger('miss_allowed');
            $table->timestamp('paid_at')->nullable();
            $table->date('started_on')->nullable();      // اليوم الأول من الاختبار
            $table->unsignedSmallInteger('days_done')->default(0);
            $table->date('last_closed_on')->nullable();
            $table->unsignedSmallInteger('completers')->nullable();
            $table->string('failure_choice', 10)->nullable(); // refund | rerun
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('tester_id')->constrained('users');
            $table->string('status', 20)->index(); // active | removed | completed | voided
            $table->unsignedInteger('accrued')->default(0);
            $table->unsignedSmallInteger('verified_days')->default(0);
            $table->unsignedSmallInteger('misses')->default(0);
            // dateTime وليس timestamp: في MariaDB القديمة (XAMPP) أول عمود timestamp إلزامي
            // يتحدث تلقائياً عند كل تعديل للصف، فيضيع وقت الانضمام الحقيقي
            $table->dateTime('joined_at');
            $table->timestamp('installed_confirmed_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 40)->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'tester_id']);
        });

        Schema::create('daily_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->date('check_date');
            $table->boolean('installed');
            $table->unsignedInteger('foreground_seconds')->default(0);
            $table->unsignedSmallInteger('reports')->default(0);
            $table->dateTime('reported_at');
            $table->timestamps();
            $table->unique(['enrollment_id', 'day_number']);
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('tester_id')->constrained('users');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('daily_checks');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('android_apps');
    }
};
