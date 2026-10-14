<?php

namespace App\Enums;

enum ReferenceType: string
{
    case Governorate = 'governorate';
    case Directorate = 'directorate';
    case Branch = 'branch';
    case Category = 'category';
}
