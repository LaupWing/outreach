<?php

use App\Support\Enrichment\Enricher;
use App\Support\Enrichment\SiteReader;

function enricher(): Enricher
{
    return new Enricher(Mockery::mock(SiteReader::class));
}

test('email picks mailto first and skips builder noise and images', function () {
    $html = '<a href="mailto:Praktijk@Voorbeeld.nl?subject=x">mail</a> support@wixpress.com logo@2x.png info@voorbeeld.nl';

    expect(enricher()->email($html))->toBe('praktijk@voorbeeld.nl');
});

test('email falls back to plain text addresses', function () {
    expect(enricher()->email('Bel ons of mail naar info@voorbeeld.nl.'))->toBe('info@voorbeeld.nl')
        ->and(enricher()->email('<p>Geen adres hier</p>'))->toBeNull();
});

test('signals read the copyright year, viewport, software and newest date', function () {
    $html = <<<'HTML'
    <html><head><meta name="viewport" content="width=device-width"></head>
    <body class="wp-content">
    <time datetime="2020-11-02">oud</time><time datetime="2021-03-12">nieuw</time>
    <footer>© 2015 - 2019 Voorbeeld</footer>
    </body></html>
    HTML;

    expect(enricher()->signals($html))->toMatchArray([
        'copyright_year' => 2019,
        'viewport' => true,
        'software' => ['WordPress'],
        'last_news_at' => '2021-03-12',
        'blocked' => false,
        'javascript_only' => false,
    ]);
});

test('a page that is only scripts is flagged as javascript only', function () {
    $html = '<html><head><script src="app.js"></script></head><body><div id="root"></div><script>boot()</script></body></html>';

    expect(enricher()->signals($html)['javascript_only'])->toBeTrue();
});
