<?php

use App\Commands\WebsiteCrawlObserver;
use App\Commands\CrawlWebsiteCommand;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

describe('WebsiteCrawlObserver', function () {
    beforeEach(function () {
        $this->command = Mockery::mock(CrawlWebsiteCommand::class);
        $this->observer = new WebsiteCrawlObserver($this->command);
    });

    afterEach(function () {
        Mockery::close();
    });

    describe('willCrawl method', function () {
        it('logs crawling intention', function () {
            $uri = Mockery::mock(UriInterface::class);
            $uri->shouldReceive('__toString')->andReturn('https://example.com');

            // Since we're using Termwind render, we can't easily test the output
            // but we can ensure the method executes without errors
            expect(fn() => $this->observer->willCrawl($uri, 'Test Link'))
                ->not->toThrow(Exception::class);
        });
    });

    describe('crawled method', function () {
        it('processes successful crawl responses', function () {
            $uri = Mockery::mock(UriInterface::class);
            $uri->shouldReceive('__toString')->andReturn('https://example.com');

            $stream = Mockery::mock(StreamInterface::class);
            $stream->shouldReceive('getSize')->andReturn(1024);

            $response = Mockery::mock(ResponseInterface::class);
            $response->shouldReceive('getStatusCode')->andReturn(200);
            $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('text/html');
            $response->shouldReceive('getHeaderLine')->with('Content-Length')->andReturn('1024');
            $response->shouldReceive('getBody')->andReturn($stream);

            $this->command->shouldReceive('addCrawledUrl')
                ->once()
                ->with('https://example.com', 200, 'text/html', $response);

            expect(fn() => $this->observer->crawled($uri, $response))
                ->not->toThrow(Exception::class);
        });

        it('handles responses without content-length header', function () {
            $uri = Mockery::mock(UriInterface::class);
            $uri->shouldReceive('__toString')->andReturn('https://example.com');

            $stream = Mockery::mock(StreamInterface::class);
            $stream->shouldReceive('getSize')->andReturn(2048);

            $response = Mockery::mock(ResponseInterface::class);
            $response->shouldReceive('getStatusCode')->andReturn(200);
            $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('text/html');
            $response->shouldReceive('getHeaderLine')->with('Content-Length')->andReturn('');
            $response->shouldReceive('getBody')->andReturn($stream);

            $this->command->shouldReceive('addCrawledUrl')
                ->once()
                ->with('https://example.com', 200, 'text/html', $response);

            expect(fn() => $this->observer->crawled($uri, $response))
                ->not->toThrow(Exception::class);
        });

        it('processes error status codes correctly', function () {
            $uri = Mockery::mock(UriInterface::class);
            $uri->shouldReceive('__toString')->andReturn('https://example.com/404');

            $stream = Mockery::mock(StreamInterface::class);
            $stream->shouldReceive('getSize')->andReturn(512);

            $response = Mockery::mock(ResponseInterface::class);
            $response->shouldReceive('getStatusCode')->andReturn(404);
            $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('text/html');
            $response->shouldReceive('getHeaderLine')->with('Content-Length')->andReturn('512');
            $response->shouldReceive('getBody')->andReturn($stream);

            $this->command->shouldReceive('addCrawledUrl')
                ->once()
                ->with('https://example.com/404', 404, 'text/html', $response);

            expect(fn() => $this->observer->crawled($uri, $response))
                ->not->toThrow(Exception::class);
        });
    });

    describe('crawlFailed method', function () {
        it('processes failed crawl attempts', function () {
            $uri = Mockery::mock(UriInterface::class);
            $uri->shouldReceive('__toString')->andReturn('https://example.com/failed');

            $request = new Request('GET', 'https://example.com/failed');
            $exception = new RequestException('Connection timeout', $request);

            $this->command->shouldReceive('addFailedUrl')
                ->once()
                ->with('https://example.com/failed', 'Connection timeout');

            expect(fn() => $this->observer->crawlFailed($uri, $exception))
                ->not->toThrow(Exception::class);
        });

        it('handles complex error messages', function () {
            $uri = Mockery::mock(UriInterface::class);
            $uri->shouldReceive('__toString')->andReturn('https://example.com/error');

            $request = new Request('GET', 'https://example.com/error');
            $exception = new RequestException('cURL error 28: Operation timed out after 30000 milliseconds', $request);

            $this->command->shouldReceive('addFailedUrl')
                ->once()
                ->with('https://example.com/error', 'cURL error 28: Operation timed out after 30000 milliseconds');

            expect(fn() => $this->observer->crawlFailed($uri, $exception))
                ->not->toThrow(Exception::class);
        });
    });

    describe('finishedCrawling method', function () {
        it('executes without errors', function () {
            expect(fn() => $this->observer->finishedCrawling())
                ->not->toThrow(Exception::class);
        });
    });

    describe('utility methods', function () {
        it('formats bytes correctly', function () {
            $method = new ReflectionMethod($this->observer, 'formatBytes');
            $method->setAccessible(true);

            expect($method->invoke($this->observer, 0))->toBe('0 B')
                ->and($method->invoke($this->observer, 1024))->toBe('1 KB')
                ->and($method->invoke($this->observer, 1048576))->toBe('1 MB')
                ->and($method->invoke($this->observer, 2048))->toBe('2 KB');
        });

        it('truncates strings correctly', function () {
            $method = new ReflectionMethod($this->observer, 'truncateString');
            $method->setAccessible(true);

            $longString = 'This is a very long error message that should be truncated';
            $result = $method->invoke($this->observer, $longString, 20);

            expect($result)->toHaveLength(23) // 20 + '...'
                ->and($result)->toEndWith('...');
        });

        it('does not truncate short strings', function () {
            $method = new ReflectionMethod($this->observer, 'truncateString');
            $method->setAccessible(true);

            $shortString = 'Short message';
            $result = $method->invoke($this->observer, $shortString, 50);

            expect($result)->toBe('Short message');
        });
    });
});
