<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Fixtures\ImplicitUse\App\Model;

class User
{
}

class Post
{
}

namespace Psy\Test\Fixtures\ImplicitUse\App\View;

class User
{
}

namespace Psy\Test\Fixtures\ImplicitUse\App\Legacy;

class OldUser
{
}

class User
{
}

namespace Psy\Test\Fixtures\ImplicitUse\Domain;

class DomainEntity
{
}

namespace Psy\Test\Fixtures\ImplicitUse\App\Model\Deep\Nested;

class DeepClass
{
}

namespace Psy\Test\Fixtures\ImplicitUse\App\Contract;

interface UserInterface
{
}

interface PostInterface
{
}

namespace Psy\Test\Fixtures\ImplicitUse\App\Traits;

trait Timestampable
{
}

trait Sluggable
{
}

namespace Psy\Test\Fixtures\ImplicitUse\App\Exception;

class UserException extends \Exception
{
}

class PostException extends \Exception
{
}

namespace Psy\Test\Fixtures\ImplicitUse\App\Service;

class UserService
{
}
