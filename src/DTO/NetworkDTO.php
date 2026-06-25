<?php

namespace App\DTO;

use App\Entity\Network;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class NetworkDTO
{

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $name;
    public ?string $description = null;
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $geometryJson = null;

    public function updateEntity(Network $network): Network
    {
        $network->setName($this->name);
        $network->setDescription($this->description);

        if ($this->geometryJson) {
            $network->setGeometry(json_decode($this->geometryJson, true));
        } else {
            $network->setGeometry(null);
        }

        $network->setUpdatedAt(new DateTimeImmutable());

        return $network;
    }
}