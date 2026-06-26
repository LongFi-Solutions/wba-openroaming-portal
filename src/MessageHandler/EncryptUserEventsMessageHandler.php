<?php

namespace App\MessageHandler;

use App\Message\UserDeletion\EncryptUserEventsMessage;
use App\Repository\UserRepository;
use App\Service\UserDeletion\UserEventDataEncryptionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class EncryptUserEventsMessageHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserEventDataEncryptionService $userEventDataEncryptionService,
    ) {
    }

    public function __invoke(EncryptUserEventsMessage $message): void
    {
        $user = $this->userRepository->find($message->userId);
        if ($user === null) {
            return;
        }

        $this->userEventDataEncryptionService->encryptUserEventsMetadata($user);
    }
}
