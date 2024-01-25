<?php

namespace Foodsharing\Modules\Region;

use Foodsharing\Lib\Xhr\XhrDialog;
use Foodsharing\Modules\Core\Control;
use Twig\Environment;

final class RegionXhr extends Control
{
    private RegionGateway $regionGateway;
    private Environment $twig;

    public function __construct(
        RegionGateway $regionGateway,
        Environment $twig
    ) {
        $this->regionGateway = $regionGateway;
        $this->twig = $twig;

        parent::__construct();
    }

    public function bubble(): array
    {
        $region_id = $_GET['id'];
        $region = $this->regionGateway->getRegionDetails($region_id);

        $dia = new XhrDialog();

        $dia->setTitle($this->translator->trans('terminology.community', ['{name}' => $region['name']]));
        $dia->addContent($this->twig->render('partials/vue-wrapper.twig', [
            'id' => 'community-bubble',
            'component' => 'CommunityBubble',
            'props' => [
                'regionId' => $region['id'],
            ],
            'initialData' => [],
        ]));

        $dia->addOpt('modal', 'false', false);
        $dia->addOpt('resizeable', 'false', false);
        $dia->noOverflow();

        return $dia->xhrout();
    }
}
