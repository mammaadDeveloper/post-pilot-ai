<?php

namespace App\Data;

class PublishedPostResult
{
    public function __construct(public readonly string $remotePostId) {}
}
