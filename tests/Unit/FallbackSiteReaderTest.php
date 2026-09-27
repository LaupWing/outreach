<?php

use App\Support\Enrichment\FallbackSiteReader;
use App\Support\Enrichment\SiteReader;

test('a normal page comes from the plain fetch and the browser is never started', function () {
    $plain = Mockery::mock(SiteReader::class);
    $plain->shouldReceive('fetch')->once()->andReturn('<html><body>'.str_repeat('Echte tekst. ', 30).'</body></html>');
    $browser = Mockery::mock(SiteReader::class);
    $browser->shouldNotReceive('fetch');

    expect((new FallbackSiteReader($plain, $browser))->fetch('https://a.nl'))->toContain('Echte tekst');
});

test('an app shell is rendered in the browser', function () {
    $plain = Mockery::mock(SiteReader::class);
    $plain->shouldReceive('fetch')->once()->andReturn('<html><body><div id="app"></div><script src="app.js"></script></body></html>');
    $browser = Mockery::mock(SiteReader::class);
    $browser->shouldReceive('fetch')->once()->andReturn('<html><body>'.str_repeat('Gerenderde tekst. ', 30).'</body></html>');

    expect((new FallbackSiteReader($plain, $browser))->fetch('https://a.nl'))->toContain('Gerenderde tekst');
});

test('when the browser fails too, the shell is what you get', function () {
    $shell = '<html><body><div id="app"></div><script></script></body></html>';
    $plain = Mockery::mock(SiteReader::class);
    $plain->shouldReceive('fetch')->andReturn($shell);
    $browser = Mockery::mock(SiteReader::class);
    $browser->shouldReceive('fetch')->andReturn(null);

    expect((new FallbackSiteReader($plain, $browser))->fetch('https://a.nl'))->toBe($shell);
});
