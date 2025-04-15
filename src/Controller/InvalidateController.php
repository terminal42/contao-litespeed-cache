<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Terminal42\ContaoLiteSpeedCache\ProxyHandler;

#[Route('_invalidate_LiteSpeed_cache', name: self::ROUTE_NAME, methods: ['GET'])]
class InvalidateController
{
    public const ROUTE_NAME = 'invalidate_LiteSpeed_cache';

    public function __construct(private ProxyHandler $proxyHandler)
    {
    }

    public function __invoke(Request $request): Response
    {
        return $this->proxyHandler->getControllerResponse($request);
    }
}
