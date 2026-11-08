<?php

namespace App\Enums;

/**
 * Staff roles. Permissions are mapped in code so that every change to
 * privileges is reviewed and deployed; role grants are stored per user.
 *
 * Administrators hold every permission listed for them below, deciding
 * applications included; each decision stays recorded under its actor. The
 * list is explicit on purpose: a new permission reaches nobody until it is
 * added here, reviewed and covered by PermissionMatrixTest. Auditors are
 * read-only.
 */
enum Role: string
{
    case Administrator = 'administrator';
    case Supervisor = 'supervisor';
    case Reviewer = 'reviewer';
    case Auditor = 'auditor';

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrator => [
                Permission::AdminAccess,
                Permission::ApplicationsView,
                Permission::ApplicationsViewAll,
                Permission::ApplicationsReview,
                Permission::ApplicationsDecide,
                Permission::ApplicationsAssign,
                Permission::ApplicationsReopen,
                Permission::DocumentsView,
                Permission::PeriodsManage,
                Permission::ContentManage,
                Permission::UsersManage,
                Permission::StaffCredentials,
                Permission::ReportsView,
                Permission::DatabaseView,
                Permission::DatabaseManage,
                Permission::BackupsManage,
            ],
            self::Supervisor => [
                Permission::AdminAccess,
                Permission::ApplicationsView,
                Permission::ApplicationsViewAll,
                Permission::ApplicationsReview,
                Permission::ApplicationsDecide,
                Permission::ApplicationsAssign,
                Permission::ApplicationsReopen,
                Permission::DocumentsView,
                Permission::ReportsView,
            ],
            self::Reviewer => [
                Permission::AdminAccess,
                Permission::ApplicationsView,
                Permission::ApplicationsReview,
                Permission::DocumentsView,
            ],
            self::Auditor => [
                Permission::AdminAccess,
                Permission::ApplicationsView,
                Permission::ApplicationsViewAll,
                Permission::ReportsView,
            ],
        };
    }

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
