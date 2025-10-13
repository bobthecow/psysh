<?php

/*
 * Minimal Composer autoload fixture for testing ComposerAutoloadWarmer.
 *
 * This fixture provides a predictable, minimal set of classes for testing
 * the autoload warmer's filtering logic without the overhead of scanning
 * a real vendor directory with hundreds of packages.
 */

require_once __DIR__.'/composer/autoload_real.php';

return PsyTestComposerFixtureAutoloader::getLoader();
