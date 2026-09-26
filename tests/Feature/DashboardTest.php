<?php

test('the dashboard is open while it runs on mock data', function () {
    $response = $this->get(route('dashboard'));

    $response->assertOk();
});
