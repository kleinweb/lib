<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Hooks;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Action extends Filter {}
