<?php

namespace Debox\Auth\Graphql\Queries;

use Debox\Graphql\Support\Facades\GraphQL;
use Debox\Graphql\Support\GraphQLQuery;

class UserPageQuery extends GraphQLQuery {
    protected $attributes = [
        'names' => 'UserPageQuery'
    ];

    public function type() {
        return GraphQL::type('UserPage');
    }

    public function resolve($root, $args) {
        return $args;
    }

}