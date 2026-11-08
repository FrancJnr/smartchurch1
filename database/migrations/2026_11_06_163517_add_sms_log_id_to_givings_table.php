<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('givings', function (Blueprint $table): void {
            $table->foreignId('sms_log_id')->nullable()->constrained('sms_logs')->nullOnDelete();
        });

        DB::table('sms_logs')->orderBy('id')->chunkById(200, function ($logs): void {
            foreach ($logs as $log) {
                DB::table('givings')->where('id', $log->giving_id)->update(['sms_log_id' => $log->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('givings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sms_log_id');
        });
    }
};
