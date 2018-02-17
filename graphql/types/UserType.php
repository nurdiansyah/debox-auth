<?php

namespace debox\auth\graphql\types;

use Debox\Graphql\Relay\Type\NodeType;
use GraphQL\Type\Definition\Type;

class UserType extends NodeType {
    protected $attributes = [
        'name' => 'User',
        'description' => 'type User.'
    ];

    public function fields() {
        $currentFields = parent::fields();
        return array_merge($currentFields, [
            'username' => [
                'type' => Type::string(),
                'description' => 'username user.'
            ],
            'name' => [
                'type' => Type::string(),
                'description' => 'name user.'
            ],
            'password' => [
                'type' => Type::string(),
                'description' => 'password user.'
            ],
            'address' => [
                'type' => Type::string(),
                'description' => 'Address user.'
            ],
            'email' => [
                'type' => Type::string(),
                'description' => 'Email user.'
            ]
        ]);
    }

    public function resolveById($id) {
        // TODO: Implement resolveById() method.
    }
}