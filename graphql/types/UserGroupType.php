<?php

namespace debox\auth\graphql\types;

use Debox\Graphql\Relay\Type\NodeType;
use GraphQL\Type\Definition\Type;
use RainLab\User\Models\UserGroup;

class UserGroupType extends NodeType {
    protected $attributes = [
        'name' => 'GroupUser',
        'description' => 'Group user.'
    ];

    public function fields() {
        $currentField = parent::fields();
        return array_merge($currentField, [
            'name' => [
                'type' => Type::string(),
                'description' => 'name group user.'
            ]
        ]);
    }

    public function resolveById($id) {
        UserGroup::query()->where('id', $id)->first();
    }
}