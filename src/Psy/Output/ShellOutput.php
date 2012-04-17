<?php

namespace Psy\Output;

use Psy\Output\OutputPager;
use Psy\Output\PassthruPager;
use Psy\Output\ProcOutputPager;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

class ShellOutput extends ConsoleOutput
{
    const NUMBER_LINES = 128;

    private $paging = 0;
    private $pager;

    public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null, $pager = null)
    {
        parent::__construct($verbosity, $decorated, $formatter);

        $this->initFormatters();

        if ($pager === null) {
            $this->pager = new PassthruPager($this);
        } elseif (is_string($pager)) {
            $this->pager = new ProcOutputPager($this, $pager);
        } elseif ($pager instanceof OutputPager) {
            $this->pager = $pager;
        } else {
            throw new \InvalidArgumentException('Unexpected pager parameter: '.$pager);
        }
    }

    public function page($messages, $type = 0)
    {
        if (is_string($messages)) {
            $messages = (array) $messages;
        }

        if (!is_array($messages) && !is_callable($messages)) {
            throw new \InvalidArgumentException('Paged output requires a string, array or callback.');
        }

        $this->paging++;

        if (is_callable($messages)) {
            $messages($this);
        } else {
            $this->write($messages, true, $type);
        }

        $this->paging--;
        $this->closePager();
    }

    public function write($messages, $newline = false, $type = 0)
    {
        if ($this->getVerbosity() == self::VERBOSITY_QUIET) {
            return;
        }

        $messages = (array) $messages;

        if ($type & self::NUMBER_LINES) {
            $pad = strlen((string) count($messages));
            $template = $this->isDecorated() ? "<aside>%-{$pad}s</aside>: %s" : "%-{$pad}s: %s";

            foreach ($messages as $i => $line) {
                $messages[$i] = sprintf($template, $i, $line);
            }
        }

        // clean this up for super.
        $type = $type & ~self::NUMBER_LINES;

        return parent::write($messages, $newline, $type);
    }

    public function doWrite($message, $newline)
    {
        if ($this->paging > 0) {
            $this->pager->doWrite($message, $newline);
        } else {
            parent::doWrite($message, $newline);
        }
    }

    private function closePager()
    {
        if ($this->paging <= 0) {
            $this->pager->close();
        }
    }

    private function initFormatters()
    {
        $formatter = $this->getFormatter();

        $formatter->setStyle('aside',  new OutputFormatterStyle('blue'));
        $formatter->setStyle('strong', new OutputFormatterStyle('white', null, array('bold')));
        $formatter->setStyle('return', new OutputFormatterStyle('cyan'));
        $formatter->setStyle('urgent', new OutputFormatterStyle('red'));
        $formatter->setStyle('hidden', new OutputFormatterStyle('black'));
    }
}
