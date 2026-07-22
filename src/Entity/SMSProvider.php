<?php

namespace App\Entity;

use App\Enum\SMSProviderType;
use App\Repository\SMSProviderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SMSProviderRepository::class)]
class SMSProvider
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    /**
     * Identifies which SMSProviderInterface implementation handles this
     * provider — Doctrine maps this natively to/from the PHP enum.
     */
    #[ORM\Column(length: 50, enumType: SMSProviderType::class)]
    private ?SMSProviderType $smsProviderType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, SMSProviderParam>
     */
    #[ORM\OneToMany(targetEntity: SMSProviderParam::class, mappedBy: 'smsProvider', cascade: ['persist', 'remove'])]
    private Collection $smsProviderParams;

    public function __construct()
    {
        $this->smsProviderParams = new ArrayCollection();
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getSMSProviderType(): ?SMSProviderType
    {
        return $this->smsProviderType;
    }

    public function setSMSProviderType(SMSProviderType $smsProviderType): static
    {
        $this->smsProviderType = $smsProviderType;

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

    /**
     * @return Collection<int, SMSProviderParam>
     */
    public function getSmsProviderParams(): Collection
    {
        return $this->smsProviderParams;
    }

    public function addSmsProviderParam(SMSProviderParam $smsProviderParam): static
    {
        if (!$this->smsProviderParams->contains($smsProviderParam)) {
            $this->smsProviderParams->add($smsProviderParam);
            $smsProviderParam->setSmsProvider($this);
        }

        return $this;
    }

    public function removeSmsProviderParam(SMSProviderParam $smsProviderParam): static
    {
        // set the owning side to null (unless already changed)
        if (
            $this->smsProviderParams->removeElement($smsProviderParam) &&
            $smsProviderParam->getSmsProvider() === $this
        ) {
            $smsProviderParam->setSmsProvider(null);
        }

        return $this;
    }
}
