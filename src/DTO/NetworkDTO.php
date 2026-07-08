<?php

namespace App\DTO;

use App\Entity\Network;
use App\Validator\Constraints as AppAssert;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\ValidNetworkGeometry]
class NetworkDTO
{
    public ?int $networkId = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $name = null;
    public ?string $description = null;
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $geometryJson = null;

    /**
     * @throws \JsonException
     */
    public function updateEntity(Network $network): Network
    {
        $network->setName($this->name);
        $network->setDescription($this->description);

        if ($this->geometryJson) {
            $network->setGeometry(
                json_decode(
                    $this->geometryJson,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );
        } else {
            $network->setGeometry(null);
        }

        $network->setUpdatedAt(new DateTimeImmutable());

        return $network;
    }
}
