<?php

declare(strict_types=1);

namespace Belief\Hyperf\Traits;

trait RepositoryFactory
{
    protected static $entity;

    public static function instance()
    {
        $className = static::$entity;
        $entity = new $className();
        $entity->setRepositoryClassName(static::class);
        return $entity->getRepository();
    }
}
