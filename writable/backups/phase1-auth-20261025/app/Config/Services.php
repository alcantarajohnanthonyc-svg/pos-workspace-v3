<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use App\Services\ModuleRegistryService;
use App\Services\OutletAccessService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function moduleRegistry(bool $getShared = true): ModuleRegistryService
    {
        if ($getShared) {
            return static::getSharedInstance('moduleRegistry');
        }

        return new ModuleRegistryService(db_connect());
    }

    public static function outletAccess(bool $getShared = true): OutletAccessService
    {
        if ($getShared) return static::getSharedInstance('outletAccess');
        return new OutletAccessService(db_connect());
    }
}
