<?php

namespace Psy;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Output extends ConsoleOutput
{
    const NUMBER_LINES = 128;

    public function __construct() {
        parent::__construct();

        $this->initFormatters();
    }

    public function write($messages, $newline = false, $type = 0)
    {
        $messages = (array) $messages;

        if ($type & self::NUMBER_LINES) {
            $pad = strlen((string) count($messages));
            $template = $this->isDecorated() ? "<aside>%-{$pad}s</aside>: %s" : "%-{$pad}s: %s";

            foreach ($messages as $i => $line) {
                $messages[$i] = sprintf($template, $i, $line);
            }
        }

        return parent::write($messages, $newline, $type & ~self::NUMBER_LINES);
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
