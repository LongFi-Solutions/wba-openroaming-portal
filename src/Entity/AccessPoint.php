<?php

namespace App\Entity;

use App\Repository\AccessPointRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessPointRepository::class)]
class AccessPoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'accessPoints')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Network $network = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $ssid = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $macAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vendor = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $standard = null; // e.g. 802.11ax

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serialNumber = null;

    /**
     * GeoJSON Point stored as JSON for spatial queries.
     * Expected format: { "type": "Point", "coordinates": [longitude, latitude] }
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $location = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $altitudeMsl = null; // Altitude above Mean Sea Level (meters)

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $altitudeAgl = null; // Altitude above Ground Level (meters)

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

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSsid(): ?string
    {
        return $this->ssid;
    }

    public function setSsid(string $ssid): static
    {
        $this->ssid = $ssid;
        return $this;
    }

    public function getMacAddress(): ?string
    {
        return $this->macAddress;
    }

    public function setMacAddress(?string $macAddress): static
    {
        $this->macAddress = $macAddress;
        return $this;
    }

    public function getVendor(): ?string
    {
        return $this->vendor;
    }

    public function setVendor(?string $vendor): static
    {
        $this->vendor = $vendor;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getStandard(): ?string
    {
        return $this->standard;
    }

    public function setStandard(?string $standard): static
    {
        $this->standard = $standard;
        return $this;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): static
    {
        $this->serialNumber = $serialNumber;
        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getLocation(): ?array
    {
        return $this->location;
    }

    /** @param array<string, mixed>|null $location */
    public function setLocation(?array $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getAltitudeMsl(): ?float
    {
        return $this->altitudeMsl;
    }

    public function setAltitudeMsl(?float $altitudeMsl): static
    {
        $this->altitudeMsl = $altitudeMsl;
        return $this;
    }

    public function getAltitudeAgl(): ?float
    {
        return $this->altitudeAgl;
    }

    public function setAltitudeAgl(?float $altitudeAgl): static
    {
        $this->altitudeAgl = $altitudeAgl;
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
