<?php

namespace App\Services;

use App\Contracts\SocialPublisher;
use App\Enums\SocialPlatform;
use App\Services\SocialPublishers\InstagramPublisher;
use App\Services\SocialPublishers\LinkedInPublisher;
use App\Services\SocialPublishers\XPublisher;

class SocialPublisherManager
{
    public function for(SocialPlatform $platform): SocialPublisher
    {
        return match ($platform) {
            SocialPlatform::LinkedIn => app(LinkedInPublisher::class),
            SocialPlatform::Instagram => app(InstagramPublisher::class),
            SocialPlatform::X => app(XPublisher::class),
        };
    }
}
