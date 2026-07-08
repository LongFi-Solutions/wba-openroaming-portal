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
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'revisor_geometry', nullable: false, columnDefinition: 'GEOMETRY SRID 4326')]
    private ?string $geometry = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, AccessPoint> */
    #[ORM\OneToMany(targetEntity: AccessPoint::class, mappedBy: 'network', orphanRemoval: true)]
    private Collection $accessPoints;

    public function __construct()
    {
        $this->accessPoints = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGeometry(): ?array
    {
        if ($this->geometry === null) {
            return null;
        }

        // Retorna identificador WKT estruturado
        return ['type' => 'WKT', 'value' => $this->geometry];
    }

    public function setGeometry(?array $geometry): static
    {
        if ($geometry === null || !isset($geometry['type'])) {
            throw new \InvalidArgumentException('A geometria da rede não pode ser nula.');
        }

        // Desembrulhar FeatureCollection se necessário
        if (strtoupper($geometry['type']) === 'FEATURECOLLECTION' && !empty($geometry['features'])) {
            $geometry = $geometry['features'][0]['geometry'] ?? null;
        }

        $type = strtoupper($geometry['type']);
        $coords = $geometry['coordinates'];

        if ($type === 'POLYGON') {
            $rings = [];
            foreach ($coords as $ring) {
                $points = [];
                foreach ($ring as $point) {
                    // 💡 Ordem estrita do MySQL 8 SRID 4326: Latitude Longitude
                    $points[] = sprintf('%f %f', $point[1], $point[0]);
                }
                $rings[] = '(' . implode(', ', $points) . ')';
            }
            $this->geometry = sprintf('POLYGON(%s)', implode(', ', $rings));
        } elseif ($type === 'MULTIPOLYGON') {
            $polygons = [];
            foreach ($coords as $polygon) {
                $rings = [];
                foreach ($polygon as $ring) {
                    $points = [];
                    foreach ($ring as $point) {
                        $points[] = sprintf('%f %f', $point[1], $point[0]);
                    }
                    $rings[] = '(' . implode(', ', $points) . ')';
                }
                $polygons[] = '(' . implode(', ', $rings) . ')';
            }
            $this->geometry = sprintf('MULTIPOLYGON(%s)', implode(', ', $polygons));
        }

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
}