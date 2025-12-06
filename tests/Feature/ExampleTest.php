<?php

uses(Tests\TestCase::class);

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(200);
});
