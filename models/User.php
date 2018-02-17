<?php namespace Debox\Auth\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use RainLab\User\Models\User as UserBase;

class User extends UserBase implements Authenticatable {
    use \Illuminate\Auth\Authenticatable;
}
