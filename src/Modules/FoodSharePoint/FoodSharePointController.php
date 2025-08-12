<?php

namespace Foodsharing\Modules\FoodSharePoint;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\ActivationStatus;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class FoodSharePointController extends FoodsharingController
{
    public function __construct(
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly FoodSharePointPermissions $foodSharePointPermissions,
    ) {
        parent::__construct();
    }

    #[Route('/fairteiler/{id}', name: 'fairteiler_id', requirements: ['id' => Requirement::DIGITS])]
    public function byId(int $id): Response
    {
        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($id);

        if (empty($foodSharePoint) || (
            $foodSharePoint->status === ActivationStatus::NOT_ACTIVE &&
            !$this->foodSharePointPermissions->mayApproveFoodSharePointCreation($foodSharePoint->regionId)
        )) {
            return $this->redirect('/');
        }
        $this->pageHelper->addContent($this->prepareVueComponent('food-share-point', 'FoodSharePoint', ['id' => $id]));

        return $this->renderGlobal();
    }

    #[Route('/fairteiler/{id}/edit', name: 'fairteiler_edit', requirements: ['id' => Requirement::DIGITS])]
    public function edit(int $id): Response
    {
        $this->pageHelper->addContent($this->prepareVueComponent(
            'food-share-point-add-or-edit',
            'FoodSharePointAddOrEdit',
            ['foodSharePointId' => $id],
        ));

        return $this->renderGlobal();
    }

    #[Route('/fairteiler/add', name: 'fairteiler_add')]
    public function add(#[MapQueryParameter] int $regionId): Response
    {
        $this->pageHelper->addContent($this->prepareVueComponent(
            'food-share-point-add-or-edit',
            'FoodSharePointAddOrEdit',
            ['regionId' => $regionId],
        ));

        return $this->renderGlobal();
    }

    #[Route('/fairteiler', name: 'redirect')]
    public function legacyRedirects(Request $request): Response
    {
        $foodSharePointId = intval($request->query->get('id'));
        $regionId = intval($request->query->get('bid'));
        switch ($request->query->get('sub')) {
            case 'ft':
                if (!$foodSharePointId) {
                    break;
                }

                return $this->redirectToRoute('fairteiler_id', ['id' => $foodSharePointId]);
            case 'add':
                return $this->redirect("/fairteiler/add?regionId={$regionId}");
            case 'edit':
                if (!$foodSharePointId) {
                    break;
                }

                return $this->redirectToRoute('fairteiler_edit', ['id' => $foodSharePointId]);
        }
        if ($regionId) {
            return $this->redirect('/region/' . $regionId);
        }

        return $this->redirectToRoute('dashboard');
    }
}
