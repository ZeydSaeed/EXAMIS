<?php

namespace App\Enums;

enum ApplicationEventType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case DocumentUploaded = 'document_uploaded';
    case DocumentReplaced = 'document_replaced';
    case DocumentRejected = 'document_rejected';
    case DataCorrected = 'data_corrected';
}
