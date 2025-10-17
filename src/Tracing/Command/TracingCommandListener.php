<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Command;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\SpanPool;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Tracing\TransactionTracingTrait;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class TracingCommandListener implements EventSubscriberInterface
{
    use TransactionTracingTrait;

    private KernelInterface $kernel;

    public function __construct(
        Dispatcher $dispatcher,
        SpanPool $spanPool,
        TransactionPool $tracePool,
        KernelInterface $kernel
    ) {
        $this->dispatcher = $dispatcher;
        $this->spanPool = $spanPool;
        $this->tracePool = $tracePool;
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

        $span = $this->transactionStart(
            $this->getSpanId($command),
            $command->getName(),
            SpanBundle::TRANSACTION_TYPE_CONSOLE,
            ''
        );

        if (extension_loaded('posix')) {
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
            'framework' => 'symfony',
            'version' => Kernel::VERSION,
        ];

        $span->context->command = [
            'name' => $command->getName() ?? SpanBundle::UNKNOWN,
        ];
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();

        if ($command === null) {
            return;
        }

        $id = $this->getSpanId($command);
        $span = $this->getTransaction($id);

        if ($span === null) {
            return;
        }

        if ($span->successful === null) {
            $span->successful = $event->getExitCode() === Command::SUCCESS;
        }

        $this->transactionEnd($id);
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $command = $event->getCommand();

        if ($command === null) {
            return;
        }

        $this->transactionError($this->getSpanId($command), $event->getError());
    }

    private function getSpanId(Command $command): int
    {
        return spl_object_id($command);
    }
}
