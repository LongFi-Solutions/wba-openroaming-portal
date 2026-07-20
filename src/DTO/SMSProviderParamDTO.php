<?php

namespace App\DTO;

use App\Entity\SMSProviderParam;
use Symfony\Component\Validator\Constraints as Assert;

class SMSProviderParamDTO
{
    public ?int $id = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(max: 255, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $paramType = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(max: 255, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $value = null;

    public static function fromEntity(SMSProviderParam $param): self
    {
        $dto = new self();
        $dto->id = $param->getId();
        $dto->paramType = $param->getParamType();
        $dto->value = $param->getValue();

        return $dto;
    }
}
