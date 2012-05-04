<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Output;

use Psy\Output\OutputPager;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

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
