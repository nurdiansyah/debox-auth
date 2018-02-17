<?php

namespace Debox\Auth\Facades;

use Illuminate\Support\Facades\Facade;

class JWTFactory extends Facade {
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'jwt.payload.factory';
    }
}
