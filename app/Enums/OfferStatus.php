<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Active = 'active';
    case Idea = 'idea';
    case Stopped = 'stopped';
}
