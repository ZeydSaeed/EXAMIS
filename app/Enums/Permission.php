<?php

namespace App\Enums;

/**
 * Staff permissions. Each case is registered as a Gate ability of the same name.
 */
enum Permission: string
{
    case AdminAccess = 'admin.access';
    case ApplicationsView = 'applications.view';
    case ApplicationsViewAll = 'applications.view_all';
    case ApplicationsReview = 'applications.review';
    case ApplicationsDecide = 'applications.decide';
    case ApplicationsAssign = 'applications.assign';
    case ApplicationsReopen = 'applications.reopen';
    case DocumentsView = 'documents.view';
    case PeriodsManage = 'periods.manage';
    case ContentManage = 'content.manage';
    case UsersManage = 'users.manage';
    case StaffCredentials = 'staff.credentials';
    case ReportsView = 'reports.view';
    case DatabaseView = 'database.view';
    case DatabaseManage = 'database.manage';
    case BackupsManage = 'backups.manage';

    /**
     * Whether a per-staff exception may touch this permission. These three
     * stay with roles, so no exception can make someone an administrator or
     * lock them out of the area.
     */
    public function isOverridable(): bool
    {
        return ! in_array($this, [self::AdminAccess, self::UsersManage, self::StaffCredentials], true);
    }

    public function label(): string
    {
        return __('permissions.'.$this->value);
    }
}
