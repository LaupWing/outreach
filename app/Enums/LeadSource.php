<?php

namespace App\Enums;

enum LeadSource: string
{
    case Places = 'places';
    case Register = 'register';
    case Manual = 'manual';
}
