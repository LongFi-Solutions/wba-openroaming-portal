<?php

namespace App\Twig\Components;

use App\DTO\NetworkDTO;
use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Enum\SettingName;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\CreateNetworkType;
use App\Security\Voter\UserAuthenticationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

#[AsLiveComponent]
final class EditNetworksForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[LiveProp]
    public NetworkDTO|null $networkDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    #[LiveProp]
    public Network|null $network = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::MAP_WRITE);

        return $this->createForm(CreateNetworkType::class, $this->networkDTO, ['disabled' => !$canWrite]);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(CreateNetworkType::class, $this->networkDTO);

        // Submit the current DTO values
        $form->submit([
            SettingName::CAPPORT_ENABLED->value => $this->networkDTO->name,
            SettingName::CAPPORT_PORTAL_URL->value => $this->networkDTO->description,
            SettingName::CAPPORT_VENUE_INFO_URL->value => $this->networkDTO->geometryJson,
        ], false);

        $this->form = $form;
    }

    public function getMap(): Map
    {
        $map = new Map()
            ->center(new Point(37.7412, -25.6756))
            ->zoom(13);

        $accessPoints = $this->entityManager->getRepository(AccessPoint::class)->findBy(['network' => $this->network]);

        foreach ($accessPoints as $ap) {
            $location = $ap->getLocation();
            if ($location && isset($location['coordinates'])) {
                $lng = $location['coordinates'][0] ?? null;
                $lat = $location['coordinates'][1] ?? null;

                if ($lat !== null && $lng !== null) {
                    $map->addMarker(new Marker(
                        position: new Point((float)$lat, (float)$lng),
                        title: $ap->getName() ?? 'Access Point'
                    ));
                }
            }
        }

        return $map;
    }
}
