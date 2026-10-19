<?php

namespace App\Enums;

enum PublicationTargetStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RequiresReauthorization = 'requires_reauthorization';
    case OutcomeUnknown = 'outcome_unknown';
}
