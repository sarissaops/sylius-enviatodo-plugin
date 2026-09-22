<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepository;
use Sylius\Component\Shipping\Model\ShipmentInterface;

/**
 * One Enviatodo label lifecycle per Sylius shipment. Rows start as drafts
 * (quote uuid held at checkout) and complete when the order is paid.
 */
#[ORM\Entity(repositoryClass: EnviatodoShipmentRepository::class)]
#[ORM\Table(name: 'sarissaops_enviatodo_shipment')]
class EnviatodoShipment
{
    public const HANDOVER_LABEL_CREATED = 'label_created';

    public const HANDOVER_DROPPED_AT_BRANCH = 'dropped_at_branch';

    public const HANDOVER_IN_PICKUP = 'in_pickup';

    public const HANDOVER_IN_TRANSIT = 'in_transit';

    public const HANDOVER_DELIVERED = 'delivered';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ShipmentInterface::class)]
    #[ORM\JoinColumn(name: 'shipment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ShipmentInterface $shipment;

    #[ORM\Column(type: 'string')]
    private string $quoteUuid;

    #[ORM\Column(type: 'string')]
    private string $providerId;

    #[ORM\Column(type: 'string')]
    private string $providerServiceId;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $trackingId = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $guideId = null;

    #[ORM\Column(type: 'integer')]
    private int $amountMinor = 0;

    #[ORM\Column(type: 'string')]
    private string $handover = self::HANDOVER_LABEL_CREATED;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $handoverDetail = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        ShipmentInterface $shipment,
        string $quoteUuid,
        string $providerId,
        string $providerServiceId,
    ) {
        $this->shipment = $shipment;
        $this->quoteUuid = $quoteUuid;
        $this->providerId = $providerId;
        $this->providerServiceId = $providerServiceId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function complete(string $trackingId, int $amountMinor): void
    {
        $this->trackingId = $trackingId;
        $this->amountMinor = $amountMinor;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isCompleted(): bool
    {
        return null !== $this->trackingId;
    }

    /**
     * Merchant left the parcel at a carrier branch: no pickup is booked and no
     * API call fires. The branch reference keeps the milestone traceable.
     */
    public function markDroppedAtBranch(string $branchReference): void
    {
        $this->handover = self::HANDOVER_DROPPED_AT_BRANCH;
        $this->handoverDetail = $branchReference;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markInPickup(): void
    {
        $this->handover = self::HANDOVER_IN_PICKUP;
        $this->handoverDetail = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markInTransit(): void
    {
        $this->handover = self::HANDOVER_IN_TRANSIT;
        $this->handoverDetail = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markDelivered(): void
    {
        $this->handover = self::HANDOVER_DELIVERED;
        $this->handoverDetail = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShipment(): ShipmentInterface
    {
        return $this->shipment;
    }

    public function getQuoteUuid(): string
    {
        return $this->quoteUuid;
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function getProviderServiceId(): string
    {
        return $this->providerServiceId;
    }

    public function getTrackingId(): ?string
    {
        return $this->trackingId;
    }

    public function getGuideId(): ?string
    {
        return $this->guideId;
    }

    public function setGuideId(string $guideId): void
    {
        $this->guideId = $guideId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getAmountMinor(): int
    {
        return $this->amountMinor;
    }

    public function getHandover(): string
    {
        return $this->handover;
    }

    public function getHandoverDetail(): ?string
    {
        return $this->handoverDetail;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
