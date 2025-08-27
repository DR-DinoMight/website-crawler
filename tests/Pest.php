<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeValidUrl', function () {
    return $this->toMatch('/^https?:\/\/[^\s\/$.?#].[^\s]*$/i');
});

expect()->extend('toBeValidEmail', function () {
    return $this->toMatch('/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
});

expect()->extend('toContainValidHtml', function () {
    return $this->toContain('<html>')
        ->and($this->toContain('</html>'));
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createMockResponse(int $statusCode = 200, string $contentType = 'text/html', string $body = '', int $size = null): Mockery\MockInterface
{
    $stream = Mockery::mock(\Psr\Http\Message\StreamInterface::class);
    $stream->shouldReceive('getContents')->andReturn($body);
    $stream->shouldReceive('rewind')->andReturn(null);
    $stream->shouldReceive('getSize')->andReturn($size ?? strlen($body));

    $response = Mockery::mock(\Psr\Http\Message\ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn($statusCode);
    $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn($contentType);
    $response->shouldReceive('getHeaderLine')->with('Content-Length')->andReturn((string)($size ?? strlen($body)));
    $response->shouldReceive('getBody')->andReturn($stream);

    return $response;
}

function createMockUri(string $url): Mockery\MockInterface
{
    $uri = Mockery::mock(\Psr\Http\Message\UriInterface::class);
    $uri->shouldReceive('__toString')->andReturn($url);

    return $uri;
}

function createTestHtml(string $title = 'Test Page', array $emails = [], array $links = [], int $imageCount = 0): string
{
    $html = "<html><head><title>{$title}</title></head><body>";

    foreach ($emails as $email) {
        $html .= "<p>Contact: {$email}</p>";
    }

    foreach ($links as $url => $text) {
        $html .= "<a href=\"{$url}\">{$text}</a>";
    }

    for ($i = 0; $i < $imageCount; $i++) {
        $html .= "<img src=\"image{$i}.jpg\" alt=\"Image {$i}\">";
    }

    $html .= "</body></html>";

    return $html;
}
