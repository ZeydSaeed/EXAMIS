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
    case AuditView = 'audit.view';
    case ReportsView = 'reports.view';
    case OperationsView = 'operations.view';
}
