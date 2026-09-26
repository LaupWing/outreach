<?php

namespace App\Enums;

enum MailboxStatus: string
{
    case Active = 'active';
    case WarmingUp = 'warming_up';
    case Paused = 'paused';
}
