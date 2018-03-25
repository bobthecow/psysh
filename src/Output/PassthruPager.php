<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Output;

use Symfony\Component\Console\Output\StreamOutput;

/**
 * A passthrough pager is a no-op. It simply wraps a StreamOutput's stream and
 * does nothing when the pager is closed.
 */
class PassthruPager extends StreamOutput implements OutputPager
{
    /**
     * Constructor.
     *
     * @param StreamOutput $output
     */
    public function __construct(StreamOutput $output)
    {
        parent::__construct($output->getStream());
    }

    /**
     * Close the current pager process.
     */
    public function close()
    {
        // nothing to do here
    }
}
