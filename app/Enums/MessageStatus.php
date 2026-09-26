<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Bounced = 'bounced';
    case Replied = 'replied';
}
