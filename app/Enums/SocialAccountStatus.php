<?php

namespace App\Enums;

enum SocialAccountStatus: string
{
    case Connected = 'connected';
    case RequiresReauthorization = 'requires_reauthorization';
    case Disconnected = 'disconnected';
}
