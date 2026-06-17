<?php

namespace App\Entity;

use App\Repository\CoveragePolygonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoveragePolygonRepository::class)]
class CoveragePolygon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'coveragePolygons')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Network $network = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /** @var array<int, array{float, float}> */
    #[ORM\Column(type: 'json')]
    private array $geometry = [];


    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNetwork(): ?Network
    {
        return $this->network;
    }

    public function setNetwork(?Network $network): static
    {
        $this->network = $network;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /** @return array<int, array{float, float}> */
    public function getGeometry(): array
    {
        return $this->geometry;
    }

    /** @param array<int, array{float, float}> $geometry */
    public function setGeometry(array $geometry): static
    {
        $this->geometry = $geometry;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
