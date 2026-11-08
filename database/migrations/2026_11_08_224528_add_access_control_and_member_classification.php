<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
        $defaults = [
            'Administrator' => [],
            'Treasurer' => ['dashboard.view', 'members.view', 'givings.view', 'givings.create', 'reports.view', 'reports.export'],
            'Secretary' => ['members.view', 'members.manage'],
            'Viewer' => ['dashboard.view', 'members.view', 'givings.view', 'reports.view'],
        ];
        foreach ($defaults as $name => $permissions) {
            DB::table('roles')->insert(['name' => $name, 'permissions' => json_encode($permissions), 'is_system' => $name === 'Administrator', 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
        });
        $firstUser = DB::table('users')->orderBy('id')->value('id');
        DB::table('users')->update(['role_id' => DB::table('roles')->where('name', 'Viewer')->value('id')]);
        if ($firstUser !== null) {
            DB::table('users')->where('id', $firstUser)->update(['role_id' => DB::table('roles')->where('is_system', true)->value('id')]);
        }
        foreach (['districts', 'groups'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        Schema::table('members', function (Blueprint $table): void {
            $table->foreignId('district_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('giving_types', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('giving_types', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('group_id');
        });
        Schema::dropIfExists('groups');
        Schema::dropIfExists('districts');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('is_active');
        });
        Schema::dropIfExists('roles');
    }
};
