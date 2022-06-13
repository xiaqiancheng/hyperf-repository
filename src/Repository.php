<?php

declare(strict_types=1);

namespace Belief\Hyperf;

use Hyperf\Database\Model\Builder;
use Belief\Hyperf\Interfaces\RepositoryInterface;

class Repository extends Builder implements RepositoryInterface
{
    use Traits\RepositoryFactory;
    use Traits\RepositoryTools;
}
