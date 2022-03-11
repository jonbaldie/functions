<?php

/**
 * This file contains all of your HTTP routes,
 * with all GET routes under the 'GET' key, and
 * the same applying to other HTTP request types.
 */

return [
    'GET' => [
        '/' => function (array $all) {
            return $all['view']('index.html');
        },
    ]
];
