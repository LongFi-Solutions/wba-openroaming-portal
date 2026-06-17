<?php

namespace App\Entity;

use App\Repository\NetworkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NetworkRepository::class)]
class Network
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $operator = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, AccessPoint> */
    #[ORM\OneToMany(targetEntity: AccessPoint::class, mappedBy: 'network', orphanRemoval: true)]
    private Collection $accessPoints;

    /** @var Collection<int, CoveragePolygon> */
    #[ORM\OneToMany(targetEntity: CoveragePolygon::class, mappedBy: 'network', orphanRemoval: true)]
    private Collection $coveragePolygons;

    public function __construct()
    {
        $this->accessPoints = new ArrayCollection();
        $this->coveragePolygons = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): static
    {
        $this->operator = $operator;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    /** @return Collection<int, AccessPoint> */
    public function getAccessPoints(): Collection
    {
        return $this->accessPoints;
    }

    public function addAccessPoint(AccessPoint $accessPoint): static
    {
        if (!$this->accessPoints->contains($accessPoint)) {
            $this->accessPoints->add($accessPoint);
            $accessPoint->setNetwork($this);
        }
        return $this;
    }

    public function removeAccessPoint(AccessPoint $accessPoint): static
    {
        if ($this->accessPoints->removeElement($accessPoint) && $accessPoint->getNetwork() === $this) {
            $accessPoint->setNetwork(null);
        }
        return $this;
    }

    /** @return Collection<int, CoveragePolygon> */
    public function getCoveragePolygons(): Collection
    {
        return $this->coveragePolygons;
    }

    public function addCoveragePolygon(CoveragePolygon $polygon): static
    {
        if (!$this->coveragePolygons->contains($polygon)) {
            $this->coveragePolygons->add($polygon);
            $polygon->setNetwork($this);
        }
        return $this;
    }

    public function removeCoveragePolygon(CoveragePolygon $polygon): static
    {
        if ($this->coveragePolygons->removeElement($polygon) && $polygon->getNetwork() === $this) {
            $polygon->setNetwork(null);
        }
        return $this;
    }
}
