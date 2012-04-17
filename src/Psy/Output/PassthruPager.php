<?php

namespace Psy\Output;

use Psy\Output\OutputPager;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PassthruPager extends StreamOutput implements OutputPager
{
    public function __construct(StreamOutput $output)
    {
        parent::__construct($output->getStream());
    }

    public function close()
    {
        // nothing to do here
    }
}
