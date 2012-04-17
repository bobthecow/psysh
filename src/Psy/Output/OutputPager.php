<?php

namespace Psy\Output;

use Symfony\Component\Console\Output\OutputInterface;

interface OutputPager extends OutputInterface
{
    function close();
}
