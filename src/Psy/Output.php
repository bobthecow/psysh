<?php

namespace Psy;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Output extends ConsoleOutput
{
    public function __construct() {
        parent::__construct();

        $this->initFormatters();
    }

    public function writelnnos(array $messages, $type = 0, $formatter = null)
    {
        $pad = strlen((string) count($messages));
        $template = "<aside>%-{$pad}s</aside>: ";
        if ($formatter !== null) {
            $template .= sprintf('<%s>%%s</%s>', $formatter, $formatter);
        } else {
            $template .= '%s';
        }

        foreach ($messages as $i => $line) {
            $messages[$i] = sprintf($template, $i, $line);
        }
        $this->writeln($messages, $type);
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
