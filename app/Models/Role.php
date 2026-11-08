<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const PERMISSIONS = [
        'dashboard.view' => 'View dashboard and giving charts',
        'members.view' => 'View members',
        'members.manage' => 'Register and edit members',
        'givings.view' => 'View giving transactions',
        'givings.create' => 'Record giving and queue SMS',
        'reports.view' => 'View giving reports',
        'reports.export' => 'Export giving reports',
        'communications.view' => 'View bulk SMS history and recipients',
        'communications.send' => 'Compose and send bulk SMS',
        'settings.manage' => 'Manage districts, groups and giving types',
        'access.manage' => 'Manage users and role permissions (full access administration)',
    ];

    protected $fillable = ['name', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function normalizePermissions(array $permissions): array
    {
        foreach (['members.manage' => 'members.view', 'givings.create' => 'givings.view', 'reports.export' => 'reports.view', 'communications.send' => 'communications.view'] as $permission => $dependency) {
            if (in_array($permission, $permissions, true)) {
                $permissions[] = $dependency;
            }
        }

        return array_values(array_unique($permissions));
    }
}
