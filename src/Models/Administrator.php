<?php

namespace Dcat\Admin\Models;

use Dcat\Admin\Traits\HasDateTimeFormatter;
use Dcat\Admin\Traits\HasPermissions;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Class Administrator.
 *
 * @property Role[] $roles
 */
class Administrator extends Model implements AuthenticatableContract, Authorizable
{
    use Authenticatable,
        HasPermissions,
        HasDateTimeFormatter;

    const DEFAULT_ID = 1;

    protected $fillable = ['username', 'password', 'name', 'avatar'];

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->init();

        parent::__construct($attributes);
    }

    protected function init()
    {
        $connection = config('admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('admin.database.users_table'));
    }

    /**
     * Get avatar attribute.
     *
     * @return mixed|string
     */
    public function getAvatar()
    {
        $avatar = $this->avatar;

        if ($avatar) {
            if (! URL::isValidUrl($avatar)) {
                $avatar = Storage::disk(config('admin.upload.disk'))->url($avatar);
            }

            return $avatar;
        }

        return admin_asset(config('admin.default_avatar') ?: '@admin/images/default-avatar.jpg');
    }

    /**
     * A user has and belongs to many roles.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $pivotTable = config('admin.database.role_users_table');

        $relatedModel = config('admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'user_id', 'role_id')->withTimestamps();
    }

    /**
     * 判断是否允许查看菜单.
     *
     * @param  array|Menu  $menu
     * @return bool
     */
    public function canSeeMenu($menu)
    {
        // 如果用戶是管理員，則可以查看所有菜單
        if ($this->isAdministrator()) {
            return true;
        }

        // 如果菜單是一個 Menu 實例
        if ($menu instanceof Menu) {
            // 檢查用戶是否有菜單所需的權限
            if (Menu::withPermission()) {
                $permissions = $menu->permissions->pluck('slug')->toArray();
                foreach ($permissions as $permission) {
                    if ($this->can($permission)) {
                        return true;
                    }
                }
            }

            // 檢查用戶是否在菜單允許的角色中
            if (Menu::withRole()) {
                $roles = $menu->roles->pluck('slug')->toArray();
                if ($this->inRoles($roles)) {
                    return true;
                }
            }

            return false;
        }

        // 如果菜單是一個數組
        if (is_array($menu)) {
            // 檢查菜單是否設置了權限
            if (isset($menu['permissions']) && count($menu['permissions']) > 0) {
                $permissionSlugOrIds = array_map(function ($permission) {
                    return empty($permission['slug']) ? $permission['id'] : $permission['slug'];
                }, $menu['permissions']);
                foreach ($permissionSlugOrIds as $slugOrId) {
                    if ($this->can($slugOrId)) {
                        return true;
                    }
                }
            }

            // 檢查菜單是否設置了角色
            if (isset($menu['roles']) && count($menu['roles']) > 0) {
                $roleSlugOrIds = array_map(function ($role) {
                    return empty($role['slug']) ? $role['id'] : $role['slug'];
                }, $menu['roles']);
                return $this->inRoles($roleSlugOrIds);
            }
        }

        // 如果沒有設置任何權限或角色限制，默認允許查看
        return false;
    }
}
