<?php

namespace Meva\Api\V1\Transformers;

use PHPOpenSourceSaver\Fractal\TransformerAbstract;
use Meva\Entities\Permission\Models\Permission as AppPermission;
use Spatie\Permission\Models\Permission;

/**
 * Class PermissionTransformer
 *
 * @package Tempest\Api\V1\Transformers
 */
class PermissionIdTransformer extends TransformerAbstract
{

    /**
     * Transform user data
     *
     * @param Permission $permission
     * @return array
     */
    public function transform(Permission $permission)
    {
        return [
            'id'    => (int)$permission->{AppPermission::ID},
        ];
    }
}
