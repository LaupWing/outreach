<?php

test('the dashboard is open while it runs on mock data', function () {
    $response = $this->get(route('dashboard'));

    $response->assertOk();
});

test('the leads page is open while it runs on mock data', function () {
    $response = $this->get(route('leads.index'));

    $response->assertOk();
});

test('the scrape page is open while it runs on mock data', function () {
    $response = $this->get(route('scrape.index'));

    $response->assertOk();
});

test('the niches page is open while it runs on mock data', function () {
    $response = $this->get(route('niches.index'));

    $response->assertOk();
});

test('the offers page is open while it runs on mock data', function () {
    $response = $this->get(route('offers.index'));

    $response->assertOk();
});

test('the mailboxes page is open while it runs on mock data', function () {
    $response = $this->get(route('mailboxes.index'));

    $response->assertOk();
});
