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
 * Fixture ClassLoader wrapper for testing.
 *
 * This file intentionally does nothing if the real Composer ClassLoader
 * is already loaded. The fixture autoloader will use the real ClassLoader
 * and just populate it with fixture data.
 */

namespace Composer\Autoload;

// The real ClassLoader should already be loaded by Composer's autoloader.
// This file exists only to maintain the expected directory structure.
// If for some reason it's not loaded, the autoload_real.php will handle it.
