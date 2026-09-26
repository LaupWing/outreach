<?php

namespace App\Enums;

enum NicheStatus: string
{
    case Testing = 'testing';
    case Idea = 'idea';
    case Proven = 'proven';
    case Dropped = 'dropped';
}
