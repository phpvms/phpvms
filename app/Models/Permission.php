<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SpatiePermission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, SpatiePermission> $teams
 * @property-read int|null $teams_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static Builder<static>|Permission newModelQuery()
 * @method static Builder<static>|Permission newQuery()
 * @method static Builder<static>|Permission permission($permissions, bool $without = false)
 * @method static Builder<static>|Permission query()
 * @method static Builder<static>|Permission role($roles, ?string $guard = null, bool $without = false)
 * @method static Builder<static>|Permission team($teams, bool $without = false)
 * @method static Builder<static>|Permission whereCreatedAt($value)
 * @method static Builder<static>|Permission whereGuardName($value)
 * @method static Builder<static>|Permission whereId($value)
 * @method static Builder<static>|Permission whereName($value)
 * @method static Builder<static>|Permission whereUpdatedAt($value)
 * @method static Builder<static>|Permission withoutPermission($permissions)
 * @method static Builder<static>|Permission withoutRole($roles, ?string $guard = null)
 * @method static Builder<static>|Permission withoutTeam($teams)
 *
 * @mixin \Eloquent
 */
class Permission extends SpatiePermission {}
