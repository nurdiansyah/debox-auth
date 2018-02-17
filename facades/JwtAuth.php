<?php namespace Debox\Auth\Facades;

use October\Rain\Support\Facade;

class JwtAuth extends Facade {
    /**
     * Get the registered name of the component.
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'jwt.auth';
    }
}
