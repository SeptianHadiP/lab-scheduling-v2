<?php

test('pengunjung yang belum login diarahkan dari dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

// test('returns a successful response', function () {
//     $response = $this->get(route('home'));

//     $response->assertOk();
// });