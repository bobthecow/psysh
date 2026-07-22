<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive;

use Psy\Formatter\LinkFormatter;
use Psy\Readline\Interactive\Input\KeyEvent;
use Psy\Readline\Interactive\Input\MouseEvent;
use Psy\Readline\Interactive\Input\PasteEvent;
use Psy\Readline\Interactive\Layout\DisplayString;
use Psy\Util\Str;

/**
 * Interactive pager specialized for PsySH documentation.
 *
 * Manual pages always open interactively, even when short. PHP manual links
 * respond to pointer hover and queue another `doc` command when clicked.
 */
class ManualPager extends Pager
{
    /** @var array{line: int, start: int}|null */
    private ?array $hoveredLink = null;

    /**
     * {@inheritdoc}
     */
    protected function shouldDisplayInline(array $lines): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function reportsPointerMotion(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleMouseEvent(MouseEvent $event): bool
    {
        if ($event->getAction() === MouseEvent::ACTION_MOVE) {
            return $this->updateHoveredLink($event);
        }
        if ($event->getAction() === MouseEvent::ACTION_RELEASE_LEFT) {
            return $this->followDocumentLink($event);
        }

        return parent::handleMouseEvent($event);
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareLinesForRender(array $lines): array
    {
        if ($this->hoveredLink !== null && isset($lines[$this->hoveredLink['line']])) {
            $line = $this->hoveredLink['line'];
            $lines[$line] = DisplayString::underlineHyperlink($lines[$line], $this->hoveredLink['start']);
        }

        return $lines;
    }

    /**
     * {@inheritdoc}
     */
    protected function resetTransientState(): void
    {
        $this->hoveredLink = null;
    }

    private function followDocumentLink(MouseEvent $event): bool
    {
        if (($link = $this->documentLinkAt($event)) === null) {
            return false;
        }

        $this->replayInput(
            new PasteEvent('doc '.$link['target']),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        );
        $this->quitGracefully();

        return true;
    }

    private function updateHoveredLink(MouseEvent $event): bool
    {
        $link = $this->documentLinkAt($event);
        $hoveredLink = $link === null ? null : ['line' => $link['line'], 'start' => $link['start']];
        if ($hoveredLink === $this->hoveredLink) {
            return false;
        }

        $this->hoveredLink = $hoveredLink;

        return true;
    }

    /**
     * @return array{uri: string, label: string, start: int, end: int, line: int, target: string}|null
     */
    private function documentLinkAt(MouseEvent $event): ?array
    {
        $position = $this->contentPositionAt($event->getColumn(), $event->getRow());
        if ($position === null) {
            return null;
        }

        $link = DisplayString::hyperlinkAt($position['text'], $position['offset']);
        if ($link === null || ($target = $this->documentTarget($link)) === null) {
            return null;
        }

        $link['line'] = $position['line'];
        $link['target'] = $target;

        return $link;
    }

    /**
     * @param array{uri: string, label: string, start: int, end: int} $link
     */
    private function documentTarget(array $link): ?string
    {
        if (\strpos($link['uri'], 'https://php.net/') !== 0) {
            return null;
        }

        $target = LinkFormatter::normalizePhpNetReference($link['label']);
        $parts = \explode('::', $target);

        if (\count($parts) > 2 || !Str::isValidClassName($parts[0])) {
            return null;
        }
        if (isset($parts[1]) && (\strpos($parts[1], '\\') !== false || !Str::isValidClassName($parts[1]))) {
            return null;
        }

        return $target;
    }
}
