<?php

namespace Foodsharing\Modules\Achievement;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Permissions\AchievementPermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AchievementController extends FoodsharingController
{
    public function __construct(
        private readonly AchievementPermissions $achievementPermissions,
    ) {
        parent::__construct();
    }

    #[Route(path: '/achievements', name: 'achievements')]
    public function index(Request $request): Response
    {
        if (!$this->achievementPermissions->mayEditAchievements()) {
            return $this->redirectToRoute('dashboard');
        }
        $this->pageHelper->addTitle($this->translator->trans('achievements.editTitle'));
        $this->pageHelper->addContent($this->prepareVueComponent('achievementEditor', 'AchievementEditor'));

        return $this->renderGlobal();
    }
}
