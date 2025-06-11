<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Exceptions;

use Exception;

final class MultisiteOnly extends Exception
{
    public function __construct(string $message = 'Multisite only.')
    {
        parent::__construct($message);
    }
}
