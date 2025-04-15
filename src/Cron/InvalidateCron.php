<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Symfony\Component\Clock\ClockAwareTrait;
use Terminal42\ContaoLiteSpeedCache\ProxyHandler;

#[AsCronJob('minutely')]
class InvalidateCron
{
    use ClockAwareTrait;

    public function __construct(private ProxyHandler $proxyHandler)
    {
    }

    public function __invoke(): void
    {
        $this->proxyHandler->executeJobs($this->now()->getTimestamp());
    }
}
