<?php

namespace Foodsharing\Mock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

class GeoapifyMock extends AbstractController
{
    public function __construct(
        private readonly KernelInterface $kernelInterface,
    ) {
    }

    /**
     * Emulates the map tile provider for acceptance tests. It returns ./img/mock_tile.png for all coordinates.
     */
    #[Route(path: '/geoapify/{z}/{x}/{y}.png')]
    public function api(): Response
    {
        return new BinaryFileResponse(
            $this->kernelInterface->getProjectDir() . '/src/Mock/img/mock_tile.png',
            Response::HTTP_OK,
            ['Content-Type' => 'text/png']);
    }
}
