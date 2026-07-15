<?php

namespace Foodsharing\Mock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WeblateMock extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Emulation of https://hosted.weblate.org/api/projects/foodsharing/languages/?format=json.
     */
    #[Route(path: '/weblate/languages')]
    public function languages(): Response
    {
        return new BinaryFileResponse(
            $this->projectDir . '/src/Mock/weblate_response.json',
            Response::HTTP_OK,
            ['Content-Type' => 'application/json']
        );
    }
}
