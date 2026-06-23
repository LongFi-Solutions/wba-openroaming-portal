<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class NetworkDTO
{

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public string $name;
    public ?string $description = null;
}