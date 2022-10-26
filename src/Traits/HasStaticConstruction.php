<?php

namespace BRISP\LivewireComponentRequests\Traits;

trait HasStaticConstruction
{
    /**
     * @param  array  $args
     * @return mixed
     */
    public static function new($args = null)
    {
        $class = get_called_class();

        if ($args != null) {
            return is_array($args)
                ? new $class(...$args)
                : new $class($args);
        }

        return new $class();
    }
}
