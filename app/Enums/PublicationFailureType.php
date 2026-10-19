<?php

namespace App\Enums;

enum PublicationFailureType: string
{
    case Permanent = 'permanent';
    case Transient = 'transient';
    case Reauthorization = 'reauthorization';
    case Unknown = 'unknown';
}
