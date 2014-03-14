<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Presenter;

/**
 * PresenterManager injects itself as a dependency to all Presenters which
 * implement PresenterManagerAware.
 */
interface PresenterManagerAware
{
    /**
     * Set a reference to the PresenterManager.
     *
     * @param PresenterManager $manager
     */
    public function setPresenterManager(PresenterManager $manager);
}
