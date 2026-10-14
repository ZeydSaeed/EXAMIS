<?php

return [
    'saved' => 'Draft saved.',
    'withdrawn' => 'The application was withdrawn.',
    'transitioned' => 'Application status updated.',
    'assigned' => 'Responsible reviewer updated.',

    'errors' => [
        'invalid_transition' => 'This action is not possible for the application in its current status.',
        'stale_version' => 'This application was changed in another session. Reload the page and re-enter your changes.',
        'period_closed' => 'Applications for this exam period are closed.',
        'not_editable' => 'The application cannot be edited in its current status.',
        'reason_required' => 'A reason is required for this action.',
        'incomplete' => 'The application is incomplete. Fill in the required fields and documents before submitting.',
        'not_eligible' => 'This choice is not eligible for this exam period.',
        'document_limit' => 'The maximum number of attachments for this application has been reached.',
        'assignee_not_eligible' => 'The selected user is not allowed to review applications.',
    ],

    'track' => [
        'not_found' => 'No application matches these details. Check the reference number and date of birth.',
    ],

    'gender' => [
        'male' => 'Male',
        'female' => 'Female',
    ],

    'events' => [
        'created' => 'Application created',
        'status_changed' => 'Status changed',
        'assigned' => 'Application assigned',
        'document_uploaded' => 'Document uploaded',
        'document_replaced' => 'Document replaced',
        'document_rejected' => 'Document rejected automatically',
        'data_corrected' => 'Data corrected',
    ],

    'actors' => [
        'system' => 'System',
        'applicant' => 'Applicant',
        'staff' => 'Reviewer',
    ],

    'actions' => [
        'under_review' => 'Start review',
        'requires_correction' => 'Request correction',
        'accepted' => 'Accept',
        'rejected' => 'Reject',
    ],
];
