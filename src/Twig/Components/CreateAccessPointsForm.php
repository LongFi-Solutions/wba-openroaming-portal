<?php

namespace App\Twig\Components;

use App\DTO\AccessPointDTO;
use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Form\CreateAccessPointType;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\CreateNetworkType;
use App\Security\Voter\UserAuthenticationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

#[AsLiveComponent]
final class CreateAccessPointsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    public function __construct(private EntityManagerInterface $entityManager, private RequestStack $requestStack)
    {
    }

    #[LiveProp]
    public AccessPointDTO|null $accessPointDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    #[LiveProp]
    public Network|null $network = null;

    #[LiveProp]
    public AccessPoint|null $accessPoint = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::MAP_WRITE);


        $form = $this->createForm(CreateAccessPointType::class, $this->accessPointDTO, ['disabled' => !$canWrite]);

        $currentRequest = $this->requestStack->getCurrentRequest();
        $isLiveRequest = $currentRequest && $currentRequest->headers->has('X-Live-Component-Action');

        if ($form->isSubmitted() === false && $isLiveRequest) {
            $form->submit([], false);
        }

        foreach ($form->getErrors() as $error) {
            if (
                $error->getCause() &&
                $error->getCause()->getPropertyPath() === 'data.geometryJson' &&
                $form->has('geometryJson')
            ) {
                $form->get('geometryJson')->addError(new FormError($error->getMessage()));
            }
        }

        return $form;
    }

    public function getFormErrors(): FormErrorIterator
    {
        return $this->getForm()->getErrors(true);
    }

    public function getMap(): Map
    {
        $map = new Map()
            ->center(new Point(37.7412, -25.6756))
            ->zoom(13);

        $accessPoints = $this->entityManager->getRepository(AccessPoint::class)->findBy(['network' => $this->network]);

        foreach ($accessPoints as $ap) {
            if ($this->accessPointDTO && $this->accessPointDTO->ssid === $ap->getSsid()) {
                continue;
            }

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
