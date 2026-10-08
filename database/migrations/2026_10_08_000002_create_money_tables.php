<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('gateway', 20);                 // zaincash | qicard
            $table->unsignedInteger('amount');
            $table->string('status', 20)->index();         // pending | paid | failed | mismatch
            $table->uuid('external_ref')->unique();        // مرجعنا المرسل للبوابة
            $table->string('gateway_txn_id', 100)->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // دفتر قيود مزدوج: كل حركة مجموع أطرافها صفر، ولا تُعدَّل بعد كتابتها.
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('description');
            $table->nullableMorphs('reference');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained();
            $table->string('account', 30);   // external | revenue | escrow | fund | wallet | withdrawal_hold | refund_hold
            $table->nullableMorphs('holder');
            $table->bigInteger('amount');    // موجب = مال محفوظ في هذا الحساب
            $table->timestamp('created_at')->useCurrent();
            $table->index(['account', 'holder_type', 'holder_id']);
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('amount');
            $table->string('method', 20);                  // zaincash | qicard
            $table->string('account', 40);
            $table->string('status', 20)->index();         // pending | processing | paid | rejected | failed
            $table->string('gateway_ref', 100)->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->string('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('user_id')->constrained();   // المطوّر
            $table->foreignId('payment_id')->nullable()->constrained();
            $table->unsignedInteger('amount');
            $table->string('reason', 30);                  // campaign_failed | duplicate_payment
            $table->string('status', 20)->index();         // pending | paid | failed
            $table->string('gateway_ref', 100)->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->string('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('payments');
    }
};
