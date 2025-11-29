<?php

return [
    'rules' => [
        'mobile' => [
            'required',
            'phone:PH,mobile', // String format instead of object for serialization
        ],
        'webhook' => ['required', 'url'],
    ],
];
