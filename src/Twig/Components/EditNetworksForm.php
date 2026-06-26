<?php

namespace App\Twig\Components;

use App\DTO\NetworkDTO;
use App\Entity\AccessPoint;
use App\Entity\Network;
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
final class EditNetworksForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    #[LiveProp(writable: ['name', 'description', 'geometryJson'])]
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

        if ($this->networkDTO && $this->network) {

            $this->networkDTO->accessPointsFromDatabase = $this->entityManager
                ->getRepository(AccessPoint::class)
                ->findBy(['network' => $this->network]);
        }

        $form = $this->createForm(CreateNetworkType::class, $this->networkDTO, ['disabled' => !$canWrite]);

        $currentRequest = $this->requestStack->getCurrentRequest();
        $isLiveRequest = $currentRequest && $currentRequest->headers->has('X-Live-Component-Action');

        if ($form->isSubmitted() === false && $isLiveRequest) {
            $form->submit([], false);
        }

        foreach ($form->getErrors() as $error) {
            if ($error->getCause() && $error->getCause()->getPropertyPath() === 'data.geometryJson') {
                if ($form->has('geometryJson')) {
                    $form->get('geometryJson')->addError(new FormError($error->getMessage()));
                }
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