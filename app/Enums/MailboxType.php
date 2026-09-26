<?php

namespace App\Enums;

enum MailboxType: string
{
    case Gmail = 'gmail';
    case Imap = 'imap';
}
