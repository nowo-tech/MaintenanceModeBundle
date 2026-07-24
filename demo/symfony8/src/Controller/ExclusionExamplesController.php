<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Demo endpoints excluded from HTTP 503 via paths / prefixes / route names / patterns.
 *
 * Configure exclusions in config/packages/nowo_maintenance_mode.yaml — do not put
 * panel-login links on the public maintenance page; operators use these bypasses
 * (or the auto-excluded panel prefix) to reach /_maintenance.
 */
final class ExclusionExamplesController extends AbstractController
{
    #[Route(path: '/status', name: 'app_status', methods: ['GET'])]
    public function statusByRouteName(): JsonResponse
    {
        return $this->json([
            'ok'      => true,
            'via'     => 'exclusions.routes: app_status',
            'message' => 'Excluded by Symfony route name while maintenance is on.',
        ]);
    }

    #[Route(path: '/api/ops/ping', name: 'app_ops_ping', methods: ['GET'])]
    public function opsPingByPrefix(): JsonResponse
    {
        return $this->json([
            'ok'      => true,
            'via'     => 'exclusions.path_prefixes: /api/ops',
            'message' => 'Excluded by path prefix.',
        ]);
    }

    #[Route(path: '/internal-check', name: 'app_internal_check', methods: ['GET'])]
    public function internalCheckByGlob(): Response
    {
        return new Response(
            "ok — excluded by exclusions.patterns glob '/internal-*'\n",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    #[Route(path: '/ops/ready', name: 'app_ops_ready', methods: ['GET'])]
    public function opsReadyByRegex(): JsonResponse
    {
        return $this->json([
            'ok'      => true,
            'via'     => 'exclusions.patterns: #^/ops/#',
            'message' => 'Excluded by regex pattern against the request path.',
        ]);
    }

    #[Route(path: '/examples/bypass', name: 'app_bypass_guide', methods: ['GET'])]
    public function bypassGuide(): Response
    {
        return $this->render('examples/bypass.html.twig');
    }
}
