<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('members',function(Blueprint $t){$t->id();$t->string('ekanisa_number',50)->unique();$t->string('name',150);$t->string('phone',20);$t->string('status',20)->default('active');$t->timestamps();});
  Schema::create('giving_types',function(Blueprint $t){$t->id();$t->string('name',100)->unique();$t->timestamps();});
  Schema::create('givings',function(Blueprint $t){$t->id();$t->foreignId('member_id')->constrained()->restrictOnDelete();$t->foreignId('giving_type_id')->constrained()->restrictOnDelete();$t->decimal('amount',14,2);$t->string('payment_method',30);$t->string('transaction_reference',100)->unique();$t->string('external_reference',100)->nullable()->unique();$t->date('giving_date')->index();$t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();$t->timestamps();});
  Schema::create('sms_logs',function(Blueprint $t){$t->id();$t->foreignId('giving_id')->unique()->constrained()->restrictOnDelete();$t->string('phone',20);$t->text('message');$t->string('status',30)->default('pending');$t->string('provider_reference')->nullable();$t->text('error_message')->nullable();$t->timestamp('sent_at')->nullable();$t->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('sms_logs');Schema::dropIfExists('givings');Schema::dropIfExists('giving_types');Schema::dropIfExists('members');}
};
