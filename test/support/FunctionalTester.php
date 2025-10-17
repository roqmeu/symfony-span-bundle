<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Support;

use Codeception\Actor;
use Roqmeu\SpanBundle\Test\Support\_generated\FunctionalTesterActions;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\KernelInterface;

class FunctionalTester extends Actor
{
    use FunctionalTesterActions;

    public function getApplication(): Application
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->grabService('kernel');

        $application = new Application($kernel);
        $application->setAutoExit(false);

        return $application;
    }
}
