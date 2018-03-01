<?php

namespace Debox\Auth\Graphql\Types;

use Debox\Graphql\Support\Facades\GraphQL;
use Debox\Graphql\Support\Facades\Relay;
use Debox\Graphql\Support\GraphQLType;
use RainLab\User\Models\User;

class UserPageType extends GraphQLType {

    protected $attributes = [
        'name' => 'UserPage'
    ];

    public function fields() {
        return array_merge(parent::fields(), [
            'UserConnection' => Relay::connectionFieldFromEdgeTypeAndQueryBuilder(
                GraphQL::type('User'),
                function ($root, $args) {
                    return User::query();
                },
                []
            )
        ]);
    }
}