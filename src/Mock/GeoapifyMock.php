<?php

namespace Foodsharing\Mock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GeoapifyMock extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Emulates the map tile provider for acceptance tests. It returns ./img/mock_tile.png for all coordinates.
     */
    #[Route(path: '/geoapify/{z}/{x}/{y}.png')]
    public function api(): Response
    {
        return new BinaryFileResponse(
            $this->projectDir . '/src/Mock/img/mock_tile.png',
            Response::HTTP_OK,
            ['Content-Type' => 'text/png']);
    }
}
