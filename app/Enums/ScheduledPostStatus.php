<?php

namespace App\Enums;

enum ScheduledPostStatus: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Published = 'published';
    case PartiallyPublished = 'partially_published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case OutcomeUnknown = 'outcome_unknown';
}
