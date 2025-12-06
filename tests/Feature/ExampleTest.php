<?php

test('example', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(200);
});
