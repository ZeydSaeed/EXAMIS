<?php

namespace App\Enums;

/**
 * Staff roles. Permissions are mapped in code so that every change to
 * privileges is reviewed and deployed; role grants are stored per user.
 *
 * Separation of duties: administrators configure the system and manage users
 * but cannot decide applications; auditors are read-only.
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
                Permission::PeriodsManage,
                Permission::ContentManage,
                Permission::UsersManage,
                Permission::AuditView,
                Permission::ReportsView,
                Permission::OperationsView,
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
                Permission::AuditView,
                Permission::ReportsView,
            ],
        };
    }

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
