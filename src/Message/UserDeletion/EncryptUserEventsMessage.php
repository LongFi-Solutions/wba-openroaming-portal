<?php

namespace App\Message\UserDeletion;

readonly class EncryptUserEventsMessage
{
    public function __construct(
        public int $userId
    ) {}
}
