<?php

namespace Foodsharing\Modules\FoodSharePoint;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Foodsharing\Utility\IdentificationHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class FoodSharePointController extends FoodsharingController
{
    private int $regionId;
    private array $foodSharePoint;
    private array $follower;
    private array $regions;

    /**
     * @var Profile[]
     */
    private array $followers;
    private array $managers;

    public function __construct(
        private readonly FoodSharePointView $view,
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly RegionGateway $regionGateway,
        private readonly MailboxGateway $mailboxGateway,
        private readonly IdentificationHelper $identificationHelper,
        private readonly FoodSharePointPermissions $foodSharePointPermissions,
    ) {
        parent::__construct();
    }

    #[Route('/fairteiler/{id}', name: 'fairteiler_id', requirements: ['id' => Requirement::DIGITS])]
    public function byId(int $id): Response
    {
        return $this->redirect('/fairteiler?sub=ft&id=' . $id);
    }

    #[Route('/fairteiler', name: 'fairteiler')]
    public function index(Request $request): Response
    {
        $this->setup($request);

        if (!$request->query->has('sub')) {
            $this->subIndex();
        } else {
            match ($request->query->get('sub')) {
                'ft' => $this->ft(),
                'check' => $this->check($request),
                'add', 'edit' => $this->addAndEdit($request),
                default => throw $this->createNotFoundException()
            };
        }

        return $this->renderGlobal();
    }

    private function subIndex(): void
    {
        $this->regions = $this->currentUserUnits->getRegions();

        if ($this->regionId === 0) {
            if ($this->session->id()) {
                $regionIds = $this->regionGateway->listIdsForFoodsaverWithDescendants($this->session->id());
            } else {
                $regionIds = [];
            }
        } else {
            $regionIds = $this->regionGateway->listIdsForDescendantsAndSelf($this->regionId);
        }

        if ($this->foodSharePoint = $this->foodSharePointGateway->listFoodSharePointsNested($regionIds)) {
            $this->pageHelper->addContent($this->view->listFoodSharePoints($this->foodSharePoint));
        } else {
            $this->pageHelper->addContent(
                $this->v_utils->v_info($this->translator->trans('fsp.none'))
            );
        }
        $this->pageHelper->addContent($this->view->foodSharePointOptions($this->regionId), CNT_RIGHT);
    }

    private function setup(Request $request): void
    {
        // allowed only for logged in users
        if (!$this->session->mayRole()
            && $request->query->has('sub')
            && $request->query->get('sub') !== 'ft') {
            $this->routeHelper->goLoginAndExit();
        }

        $this->foodSharePoint = [];
        $this->follower = [];
        $this->regions = $this->getRealRegions();
        if ($foodSharePointId = intval($request->query->get('id'))) {
            $this->foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);

            if (!$this->foodSharePoint) {
                $this->routeHelper->goAndExit('/fairteiler');
            }
            $regionId = $this->foodSharePoint['bezirk_id'];
        }

        if (!isset($regionId)) {
            $regionId = intval($request->query->get('bid'));
        }

        if (!empty($regionId) && is_int($regionId) && $region = $this->regionGateway->getRegion($regionId)) {
            $this->regionId = $regionId;
            $region1 = $region;
            if ((int)$region['mailbox_id'] > 0) {
                $region1['urlname'] = $this->mailboxGateway->getMailboxname($region['mailbox_id']);
            } else {
                $region1['urlname'] = $this->identificationHelper->id($region1['name']);
            }
        } else {
            $this->regionId = 0;
            $region1 = null;
        }

        if ($foodSharePointId) {
            $follow = $request->query->get('follow');
            $infoType = intval($request->query->get('infotype', InfoType::BELL));

            if ($this->handleFollowUnfollow($foodSharePointId, $this->session->id() ?? 0, $follow, $infoType)) {
                $url = explode('&follow=', (string)$this->routeHelper->getSelf());
                $this->routeHelper->goAndExit($url[0]);
            }

            if (!isset($this->regions[$this->foodSharePoint['bezirk_id']])) {
                $this->regions[] = $this->regionGateway->getRegion($this->foodSharePoint['bezirk_id']);
            }

            $this->follower = $this->foodSharePointGateway->getFollower($foodSharePointId);
            $mapper = fn ($user) => new Profile($user);
            $this->managers = array_map($mapper, $this->follower['fsp_manager']);
            $this->followers = array_map($mapper, $this->follower['follow']);

            $this->foodSharePoint['urlname'] = str_replace(' ', '_', (string)$this->foodSharePoint['name']);
            $this->foodSharePoint['urlname'] = $this->identificationHelper->id($this->foodSharePoint['urlname']);
            $this->foodSharePoint['urlname'] = str_replace('_', '-', (string)$this->foodSharePoint['urlname']);

            if ($request->query->has('delete') && $this->foodSharePointPermissions->mayDeleteFoodSharePointOfRegion($this->regionId)) {
                $this->delete();
            }
        }
        $this->view->setRegions($this->regions);
        $this->view->setRegion($region1);

        $this->pageHelper->addBread($this->translator->trans('fsp.yours'), '/fairteiler');
        if ($this->regionId > 0) {
            $this->pageHelper->addBread($region1['name'], '/fairteiler?bid=' . $this->regionId);
        }
    }

    private function handleFollowUnfollow(int $foodSharePointId, int $foodSharerId, ?string $follow, int $infoType): bool
    {
        if ($follow === null) {
            return false;
        }
        if ($follow == 1 && in_array($infoType, [InfoType::EMAIL, InfoType::BELL], true)) {
            $this->foodSharePointGateway->follow($foodSharerId, $foodSharePointId, $infoType);
        } else {
            $this->foodSharePointGateway->unfollow($foodSharerId, $foodSharePointId);
        }

        return true;
    }

    private function getRealRegions(): array
    {
        return array_filter($this->currentUserUnits->getRegions(), fn ($region) => UnitType::isAccessibleRegion($region['type']));
    }

    private function addAndEdit(Request $request): Response
    {
        if (!$this->foodSharePointPermissions->mayEdit($this->regionId, $this->follower)) {
            $this->routeHelper->goAndExit('/fairteiler/' . $this->foodSharePoint['id']);
        }

        if ($request->query->get('sub') !== 'add') {
            $componentParams = [
                'foodSharePointId' => $this->foodSharePoint['id'],
                'regionId' => $this->regionId,
            ];

            $this->pageHelper->addBread(
                $this->foodSharePoint['name'],
                '/fairteiler?sub=ft&bid=' . $this->regionId . '&id=' . $this->foodSharePoint['id']
            );
        }

        $foodSharePoint = $this->prepareVueComponent('food-share-point-add-or-edit', 'FoodSharePointAddOrEdit', $componentParams ?? []);

        $this->pageHelper->addBread($this->translator->trans('fsp.edit'));

        $this->pageHelper->addContent($foodSharePoint);

        return $this->renderGlobal();
    }

    private function check(Request $request): void
    {
        $foodSharePoint = $this->foodSharePoint;
        if (!$foodSharePoint || !$this->foodSharePointPermissions->mayApproveFoodSharePointCreation($foodSharePoint['bezirk_id'])) {
            $this->routeHelper->goPageAndExit('fairteiler');
        }

        if ($request->query->has('agree')) {
            if ($request->query->get('agree')) {
                $this->accept();
            } else {
                $this->delete();
            }
        }
        $this->pageHelper->addContent($this->view->checkFoodSharePoint($foodSharePoint));

        $menuAccept = [
            'href' => '/fairteiler?sub=check&id=' . (int)$foodSharePoint['id'] . '&agree=1',
            'name' => $this->translator->trans('fsp.accept'),
        ];
        $menuReject = [
            'href' => '/fairteiler?sub=check&id=' . (int)$foodSharePoint['id'] . '&agree=0',
            'name' => $this->translator->trans('fsp.reject'),
            'click' => 'if (confirm(\''
                . $this->translator->trans('fsp.rejectConfirm')
                . '\')) { goTo(this.href); } else { return false; }',
        ];
        $this->pageHelper->addContent($this->view->menu(
            [$menuAccept, $menuReject],
            ['title' => $this->translator->trans('options')]
        ), CNT_RIGHT);
    }

    private function accept(): void
    {
        $this->foodSharePointGateway->acceptFoodSharePoint($this->foodSharePoint['id']);
        $this->flashMessageHelper->success($this->translator->trans('fsp.acceptSuccess'));
        $this->routeHelper->goAndExit('/fairteiler?sub=ft&id=' . $this->foodSharePoint['id']);
    }

    private function ft(): void
    {
        $this->pageHelper->addBread($this->foodSharePoint['name']);
        $this->pageHelper->addTitle($this->foodSharePoint['name']);
        $this->pageHelper->addContent(
            $this->view->foodSharePointHead($this->foodSharePoint) . '
			<div>'
                . $this->v_utils->v_info(
                    $this->translator->trans('fsp.publicwall'),
                    $this->translator->trans('notice')
                ) . '
			</div>'
        );

        $this->pageHelper->addContent($this->view->vueComponent('vue-wall', 'wall', [
            'target' => 'fairteiler',
            'targetId' => $this->foodSharePoint['id'],
            'galleryHeightInPx' => 250,
        ]));

        if ($this->foodSharePointPermissions->mayFollow()) {
            $items = [];

            if ($this->foodSharePointPermissions->mayEdit($this->regionId, $this->follower)) {
                $items[] = [
                    'name' => $this->translator->trans('fsp.edit'),
                    'href' => '/fairteiler?bid=' . $this->regionId . '&sub=edit&id=' . $this->foodSharePoint['id'],
                ];
            }

            if ($this->isFollower()) {
                if ($this->foodSharePointPermissions->mayUnfollow($this->foodSharePoint['id'])) {
                    $items[] = [
                        'name' => $this->translator->trans('fsp.unfollow'),
                        'href' => $this->routeHelper->getSelf() . '&follow=0',
                    ];
                }
            } else {
                $items[] = [
                    'name' => $this->translator->trans('fsp.follow'),
                    'click' => 'u_follow(); return false;'
                ];
                $this->pageHelper->addHidden($this->view->followHidden());
            }

            $this->pageHelper->addContent($this->view->options($items), CNT_LEFT);
            $this->pageHelper->addContent($this->view->follower($this->followers, $this->managers), CNT_LEFT);
        }

        $this->pageHelper->addContent($this->view->desc($this->foodSharePoint), CNT_RIGHT);
        $this->pageHelper->addContent($this->view->address($this->foodSharePoint), CNT_RIGHT);
    }

    private function isFollower(): bool
    {
        return isset($this->follower['all'][$this->session->id()]);
    }

    public function getFollowers(): array
    {
        return $this->followers;
    }
}
