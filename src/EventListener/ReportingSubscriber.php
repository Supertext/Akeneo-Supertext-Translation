<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\EventListener;

use Supertext\AkeneoTranslationBundle\Reporting\Reporting;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * - The public ping route GET /supertext/ping?challenge=… for the Supertext Commando Center. It is
 *   answered before routing and before Akeneo's firewall (priority 512; the firewall listens at 8),
 *   so it needs no login, opens no session and has no CSRF token. 404 while version reports are off.
 * - Runs the reporter actions controllers and commands deferred, after the response was sent
 *   (kernel.terminate) or the command finished (console.terminate).
 */
final class ReportingSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Reporting $reporting)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST     => ['onRequest', 512],
            KernelEvents::TERMINATE   => ['runDeferred', -1024],
            ConsoleEvents::TERMINATE  => ['runDeferred', -1024],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->getPathInfo() !== Reporting::PING_PATH || !$request->isMethod('GET')) {
            return;
        }

        $challenge = $request->query->all()['challenge'] ?? null;
        $ping      = $this->reporting->ping(\is_string($challenge) ? $challenge : null);
        // Status, headers and body as the SDK builds them (Symfony adds "private" to Cache-Control: no-store).
        $event->setResponse(new Response($ping->body, $ping->status, $ping->headers));
    }

    public function runDeferred(): void
    {
        $this->reporting->runDeferred();
    }
}
