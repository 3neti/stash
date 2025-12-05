<?php

return [
    'rules' => [
        'mobile' => [
            'required',
            'phone:PH,mobile', // String format instead of object for serialization
        ],
        'email' => [
            'required',
            'email',
        ],
        'webhook' => ['required', 'url'],
        'slack' => [
            'required',
            'url',
            'starts_with:https://hooks.slack.com/',
        ],
    ],
];
