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
final class ManageNetworksForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    public function __construct(private EntityManagerInterface $entityManager, private RequestStack $requestStack)
    {
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

        $form = $this->createForm(CreateNetworkType::class, $this->networkDTO, ['disabled' => !$canWrite]);

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

        if (!$this->network || !$this->network->getId()) {
            return $map;
        }

        $accessPoints = $this->entityManager
            ->getRepository(AccessPoint::class)
            ->findByNetworkWithCoordinates($this->network);

        foreach ($accessPoints as $ap) {
            $map->addMarker(new Marker(
                position: new Point((float)$ap['lat'], (float)$ap['lng']),
                title: $ap['name'] ?? 'Access Point'
            ));
        }

        return $map;
    }
}
