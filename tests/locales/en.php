<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

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
