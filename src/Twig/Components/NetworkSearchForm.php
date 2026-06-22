<?php

namespace App\Twig\Components;

use App\Entity\Network;
use App\Entity\User;
use App\Repository\NetworkRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
class NetworkSearchForm
{

    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true, url: true)]
    public string $filter = 'all';

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public int $count = 7;

    #[LiveProp(writable: true)]
    public string $sort = 'createdAt';

    #[LiveProp(writable: true)]
    public string $order = 'desc';

    /** @var Paginator<User>|null */
    private ?Paginator $cachedNetworks = null;

    /** @var array<string, int>|null */
    private ?array $cachedCounts = null;

    public function __construct(
        private readonly NetworkRepository $networkRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly Security $security,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @return Paginator<Network>
     */
    #[ExposeInTemplate]
    public function getNetworks(): Paginator
    {
        if (!$this->cachedNetworks instanceof Paginator) {
            $this->cachedNetworks = new Paginator($this->getQueryBuilder());
        }

        return $this->cachedNetworks;
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return $this->networkRepository->searchWithFilter(
            $this->filter,
            $this->sort,
            $this->order,
            $this->query ?: null,
            $this->page,
            $this->count,
        );
    }

    /**
     * @return array<string, int>
     */
    #[ExposeInTemplate]
    public function getNetworkCounts(): array
    {
        if ($this->cachedCounts === null) {
            $this->cachedCounts = [
                'all' => count($this->networkRepository->findAll()),
            ];
        }

        return $this->cachedCounts;
    }

}