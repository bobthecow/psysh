<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Minimal Composer autoload fixture for testing ComposerAutoloadWarmer.
 *
 * This fixture provides a predictable, minimal set of classes for testing
 * the autoload warmer's filtering logic without the overhead of scanning
 * a real vendor directory with hundreds of packages.
 */

require_once __DIR__.'/composer/autoload_real.php';

return PsyTestComposerFixtureAutoloader::getLoader();
