<?php

namespace Foodsharing\Mock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Emulates the response from the Bundestag petition website which is parsed for the petition banned.
 */
class BundestagPetitionMock extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    #[Route(path: '/petition')]
    public function petition(): Response
    {
        return new BinaryFileResponse(
            $this->projectDir . '/src/Mock/example_petition.html',
            Response::HTTP_OK,
            ['Content-Type' => 'text/html']
        );
    }
}
