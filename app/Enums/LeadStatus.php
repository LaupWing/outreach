<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Emailed = 'emailed';
    case FollowedUp = 'followed_up';
    case Replied = 'replied';
    case Customer = 'customer';
    case No = 'no';
    case Undeliverable = 'undeliverable';
}
