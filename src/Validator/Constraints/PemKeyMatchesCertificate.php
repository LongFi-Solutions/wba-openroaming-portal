<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PemKeyMatchesCertificate extends Constraint
{
    public string $message = 'privateKeyDoesntMatchCertificate';

    /**
     * @param array<string, mixed> $options
     * @param array<string>|null $groups
     * @param mixed $payload
     */
    public function __construct(
        public string $certificateField = 'client',
        public string $privateKeyField = 'key',
        ?string $message = null,
        array $options = [],
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->certificateField = $options['certificateField'] ?? $this->certificateField;
        $this->privateKeyField = $options['privateKeyField'] ?? $this->privateKeyField;
    }

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    #[\Override]
    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
