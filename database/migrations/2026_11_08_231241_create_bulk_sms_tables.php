<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('audience', 20);
            $table->string('audience_label', 150);
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('message');
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('matched_members')->default(0);
            $table->unsignedInteger('invalid_numbers')->default(0);
            $table->unsignedInteger('duplicate_numbers')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sms_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_name', 150);
            $table->string('phone', 20);
            $table->string('status', 30)->default('pending');
            $table->string('provider_reference')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['sms_campaign_id', 'phone']);
            $table->index(['sms_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_recipients');
        Schema::dropIfExists('sms_campaigns');
    }
};
