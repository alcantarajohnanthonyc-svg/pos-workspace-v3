<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use App\Services\ModuleRegistryService;
use App\Services\OutletAccessService;
use App\Services\ActiveSessionService;
use App\Services\AuditLogService;
use App\Services\SuperadminAccessPolicy;
use App\Services\SuperadminDashboardService;
use App\Services\CredentialLoginLimiter;
use App\Services\UserManagementService;
use App\Services\UserLookupService;
use App\Services\OrganizationManagementService;

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
    public static function auditLog(bool $getShared = true): AuditLogService
    {
        if ($getShared) return static::getSharedInstance('auditLog');
        return new AuditLogService(db_connect());
    }

    public static function activeSession(bool $getShared = true): ActiveSessionService
    {
        if ($getShared) return static::getSharedInstance('activeSession');
        return new ActiveSessionService(db_connect(), static::auditLog(false));
    }

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

    public static function googleWorkspaceDomainPolicy(bool $getShared = true): \App\Services\GoogleWorkspaceDomainPolicy
    {
        if ($getShared) return static::getSharedInstance('googleWorkspaceDomainPolicy');
        return new \App\Services\GoogleWorkspaceDomainPolicy();
    }

    public static function credentialLoginLimiter(bool $getShared = true): CredentialLoginLimiter
    {
        if ($getShared) return static::getSharedInstance('credentialLoginLimiter');
        return new CredentialLoginLimiter(service('throttler'));
    }

    public static function superadminAccessPolicy(bool $getShared = true): SuperadminAccessPolicy
    {
        if ($getShared) return static::getSharedInstance('superadminAccessPolicy');
        return new SuperadminAccessPolicy();
    }

    public static function superadminDashboard(bool $getShared = true): SuperadminDashboardService
    {
        if ($getShared) return static::getSharedInstance('superadminDashboard');
        return new SuperadminDashboardService(db_connect());
    }

    public static function userManagement(bool $getShared = true): UserManagementService
    {
        if ($getShared) return static::getSharedInstance('userManagement');
        return new UserManagementService(db_connect(), static::outletAccess(false), static::auditLog(false));
    }

    public static function userLookup(bool $getShared = true): UserLookupService
    {
        if ($getShared) return static::getSharedInstance('userLookup');
        return new UserLookupService(db_connect(), static::auditLog(false));
    }

    public static function organizationManagement(bool $getShared = true): OrganizationManagementService
    {
        if ($getShared) return static::getSharedInstance('organizationManagement');
        return new OrganizationManagementService(db_connect(), static::auditLog(false));
    }

    public static function outletList(bool $getShared = true): OutletListService
    {
        if ($getShared) return static::getSharedInstance('outletList');
        return new OutletListService(db_connect(), static::outletAccess(false));
    }
}
