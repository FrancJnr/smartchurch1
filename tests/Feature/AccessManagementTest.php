<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_create_custom_role_and_user_without_granting_system_privileges(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->post(route('roles.store'), ['name' => 'Member clerk', 'permissions' => ['members.manage'], 'is_system' => true])
            ->assertRedirect(route('roles.index'))->assertSessionHasNoErrors();
        $role = Role::where('name', 'Member clerk')->sole();
        $this->assertFalse($role->is_system);
        $this->assertSame(['members.manage', 'members.view'], $role->permissions);

        $this->post(route('users.store'), [
            'name' => 'New clerk', 'email' => 'clerk@example.test', 'role_id' => $role->id,
            'is_active' => '1', 'password' => 'a-strong-password-123', 'password_confirmation' => 'a-strong-password-123',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $user = User::where('email', 'clerk@example.test')->sole();
        $this->assertTrue(Hash::check('a-strong-password-123', $user->password));
        $this->assertSame($role->id, $user->role_id);
        $this->actingAs($user)->get(route('members.create'))->assertOk()->assertSee('District')->assertSee('Group');
        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('home'))->assertOk()->assertDontSee('Period total:')->assertDontSee(route('users.index'));
    }

    public function test_viewer_cannot_modify_records_or_access_configuration_by_direct_url(): void
    {
        $viewer = User::factory()->create(['role_id' => Role::where('name', 'Viewer')->sole()->id]);
        $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertDontSee('href="'.route('givings.create').'"', false);
        $this->get(route('members.index'))->assertOk()->assertDontSee('Register Member');
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('catalogs.index', 'groups'))->assertForbidden();
        $this->get(route('reports.export'))->assertForbidden();
        $this->get(route('members.lookup', 'ANY'))->assertForbidden();
        $this->post(route('members.store'), [])->assertForbidden();
        $this->post(route('givings.store'), [])->assertForbidden();
        $this->post(route('users.store'), [])->assertForbidden();
        $this->post(route('roles.store'), [])->assertForbidden();
        $this->post(route('catalogs.store', 'districts'), ['name' => 'Unapproved'])->assertForbidden();
        $this->assertDatabaseCount('districts', 0);
        $this->assertDatabaseCount('givings', 0);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_users_without_a_role_have_no_module_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->get(route('members.index'))->assertForbidden();
        $this->get(route('home'))->assertOk()->assertSee('Not assigned');
    }

    public function test_administrator_role_and_last_active_administrator_are_protected(): void
    {
        $admin = User::factory()->administrator()->create();
        $adminRole = $admin->role;
        $this->actingAs($admin)->put(route('roles.update', $adminRole), ['name' => 'Changed', 'permissions' => []])->assertForbidden();
        $this->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role_id' => $adminRole->id, 'is_active' => 0])->assertSessionHasErrors('role_id');
        $managerRole = Role::factory()->create(['permissions' => ['access.manage']]);
        $manager = User::factory()->create(['role_id' => $managerRole->id]);
        $this->actingAs($manager)->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role_id' => $managerRole->id, 'is_active' => 1])->assertSessionHasErrors('role_id');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role_id' => $adminRole->id, 'is_active' => true]);
        $this->put(route('roles.update', $managerRole), ['name' => 'Changed', 'permissions' => []])->assertForbidden();
    }

    public function test_inactive_account_cannot_log_in_or_keep_using_a_session(): void
    {
        $user = User::factory()->administrator()->create(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get(route('members.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_edit_preserves_blank_password_and_supports_reset_and_deactivation(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = User::factory()->create(['role_id' => Role::where('name', 'Viewer')->sole()->id]);
        $oldPassword = $target->password;
        $this->actingAs($admin)->put(route('users.update', $target), ['name' => 'Updated name', 'email' => $target->email, 'role_id' => $target->role_id, 'is_active' => 1, 'password' => ''])->assertSessionHasNoErrors();
        $this->assertSame($oldPassword, $target->fresh()->password);
        $this->put(route('users.update', $target), ['name' => 'Updated name', 'email' => $target->email, 'role_id' => $target->role_id, 'is_active' => 0, 'password' => 'new-strong-password-123', 'password_confirmation' => 'new-strong-password-123'])->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->is_active);
        $this->assertTrue(Hash::check('new-strong-password-123', $target->fresh()->password));
    }

    public function test_role_changes_take_effect_and_unknown_permissions_are_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        $role = Role::factory()->create(['permissions' => ['members.view']]);
        $reader = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($reader)->get(route('members.index'))->assertOk();
        $this->actingAs($admin)->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => ['superuser']])->assertSessionHasErrors('permissions.0');
        $this->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => []])->assertSessionHasNoErrors();
        $this->actingAs($reader->fresh())->get(route('members.index'))->assertForbidden();
    }

    public function test_management_pages_render_for_administrators(): void
    {
        $admin = User::factory()->administrator()->create();
        $role = Role::where('name', 'Treasurer')->sole();
        $this->actingAs($admin);
        foreach ([route('users.index'), route('users.create'), route('users.edit', $admin), route('roles.index'), route('roles.create'), route('roles.edit', $role), route('catalogs.index', 'districts'), route('catalogs.index', 'groups'), route('catalogs.index', 'giving-types')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('catalogs.index', 'users'))->assertNotFound();
    }
}
