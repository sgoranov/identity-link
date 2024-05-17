<?php
/**
 * Copyright for held by Robin Chalas as part of project thephpleague/oauth2-server-bundle
 * https://github.com/thephpleague/oauth2-server-bundle
 *
 * Copyright (c) 2020 Robin Chalas
 * Portions Copyright (c) 2018-2020 Trikoder
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace App\Service;

use League\Event\EventInterface;
use League\Event\ListenerAcceptorInterface;
use League\Event\ListenerProviderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SymfonyLeagueEventListenerProvider implements ListenerProviderInterface
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function provideListeners(ListenerAcceptorInterface $listenerAcceptor): SymfonyLeagueEventListenerProvider
    {
        $listener = $this->dispatchLeagueEventWithSymfonyEventDispatcher(...);

        $listenerAcceptor->addListener('*', $listener);

        return $this;
    }

    private function dispatchLeagueEventWithSymfonyEventDispatcher(EventInterface $event): void
    {
        $this->eventDispatcher->dispatch($event, $event->getName());
    }
}
