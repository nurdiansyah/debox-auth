<?php

namespace Debox\Auth\Graphql\Mutations;

use Debox\Graphql\Support\GraphQLMutation;
use GraphQL\Type\Definition\Type;

class ChangePasswordMutation extends GraphQLMutation {
    public function args() {
        return [
            'password' => [
                'name' => 'password',
                'type' => Type::string()
            ],
            'password_confirmation' => [
                'name' => 'password_confirmation',
                'type' => Type::string()
            ],
            'password_new' => [
                'name' => 'password_new',
                'type' => Type::string()
            ]
        ];
    }

    public function rules() {
        return [
            'password' => 'required|min:6|',
            'password_confirmation' => 'same:password',
            'password_new' => 'required|min:6'
        ];
    }

}