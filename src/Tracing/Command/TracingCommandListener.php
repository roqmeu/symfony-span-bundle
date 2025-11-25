<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Command;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;
use Roqmeu\SpanBundle\State\Span;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;

class TracingCommandListener implements EventSubscriberInterface, ResetInterface
{
    use SpanTracerAwareTrait;

    private KernelInterface $kernel;

    /**
     * @var array<int, Span>
     */
    public array $spanPool = [];

    public function __construct(SpanTracer $spanTracer, KernelInterface $kernel)
    {
        $this->spanTracer = $spanTracer;
        $this->kernel = $kernel;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 1024],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', -1024],
            ConsoleEvents::ERROR => ['onConsoleError', 1024],
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if ($command === null) {
            return;
        }

        $span = new Span($command->getName() ?? SpanBundle::UNKNOWN, SpanBundle::SPAN_TYPE_CONSOLE);

        if (\extension_loaded('posix')) {
            $span->context->process = [
                'executable' => \PHP_BINARY,
                'interactive' => \PHP_SAPI === 'cli' && (\posix_isatty(\STDIN) || \posix_isatty(\STDOUT)),
                'pid' => \posix_getpid(),
                'parent_pid' => \posix_getppid(),
                'runtime_name' => \PHP_SAPI,
                'runtime_version' => \PHP_VERSION,
            ];
        } else {
            $span->context->process = [
                'executable' => \PHP_BINARY,
                'runtime_name' => \PHP_SAPI,
                'runtime_version' => \PHP_VERSION,
            ];
        }

        $span->context->framework = [
            'debug' => $this->kernel->isDebug(),
            'environment' => $this->kernel->getEnvironment(),
            'name' => 'symfony',
            'version' => Kernel::VERSION,
        ];

        $span->context->command = [
            'name' => $command->getName() ?? SpanBundle::UNKNOWN,
        ];

        $this->spanPool[$this->getCommandId($command)] = $span;

        if ($this->spanTracer->hasActiveTrace()) {
            $this->spanTracer->startSpan($span);
        } else {
            $this->spanTracer->startSpanWithTrace($span);
        }
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();

        if ($command === null) {
            return;
        }

        $span = $this->spanPool[$this->getCommandId($command)] ?? null;

        if ($span === null) {
            return;
        }

        $span->setSuccessfulIf($event->getExitCode() === Command::SUCCESS);

        $this->spanTracer->endSpan($span);
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $command = $event->getCommand();

        if ($command === null) {
            return;
        }

        $span = $this->spanPool[$this->getCommandId($command)] ?? null;

        if ($span === null) {
            return;
        }

        $span->setError($event->getError());
    }

    private function getCommandId(Command $command): int
    {
        return spl_object_id($command);
    }

    public function reset(): void
    {
        $this->spanPool = [];
    }
}
