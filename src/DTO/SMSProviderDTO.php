<?php

namespace App\DTO;

use App\Entity\SMSProvider;
use App\Enum\SMSProviderType;
use Symfony\Component\Validator\Constraints as Assert;

class SMSProviderDTO
{
    public ?int $id = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(max: 255, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Url(message: 'fieldMustBeAValidUrl')]
    #[Assert\Length(max: 255, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $address = null;

    #[Assert\NotNull(message: 'fieldCannotBeBlank')]
    public ?SMSProviderType $smsProviderType = null;

    /**
     * @var SMSProviderParamDTO[]
     */
    #[Assert\Valid]
    #[Assert\Count(min: 1, minMessage: 'atLeastOneParamRequired')]
    public array $params = [];

    public static function fromEntity(SMSProvider $provider): self
    {
        $dto = new self();
        $dto->id = $provider->getId();
        $dto->name = $provider->getName();
        $dto->address = $provider->getAddress();
        $dto->smsProviderType = $provider->getSMSProviderType();

        foreach ($provider->getSmsProviderParams() as $param) {
            $dto->params[] = SMSProviderParamDTO::fromEntity($param);
        }

        return $dto;
    }
}
