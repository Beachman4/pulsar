<?php

return [
    'phrases' => [
        'pulsar' => [
            'properties' => [
                'TestModel2' => [
                    'validate' => 'Email address',
                ],
            ],
            'validation' => [
                'invalid' => '{{property}} is invalid',
                'unique' => '{{property}} must be unique',
            ],
        ],
    ],
];
