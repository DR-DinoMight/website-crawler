<?php

namespace App\Commands;

use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use function Termwind\{render};

class WebsiteCrawlObserver extends CrawlObserver
{
    protected $command;

    public function __construct(CrawlWebsiteCommand $command)
    {
        $this->command = $command;
    }

    public function willCrawl(UriInterface $url, ?string $linkText): void
    {
        render('<div class="px-1 text-yellow-400">🔍 Crawling: ' . $url . '</div>');
    }

    public function crawled(
        UriInterface $url,
        ResponseInterface $response,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null,
    ): void {
        $statusCode = $response->getStatusCode();
        $contentType = $response->getHeaderLine('Content-Type');

        // Pass the response object for enhanced data extraction
        $this->command->addCrawledUrl(
            (string) $url,
            $statusCode,
            $contentType,
            $response
        );

        $statusColor = $statusCode >= 200 && $statusCode < 300 ? 'green' : 'red';

        // Enhanced logging with more details - get size from content length header if available
        $size = $response->getHeaderLine('Content-Length') ?: ($response->getBody()->getSize() ?? 0);
        $sizeFormatted = $this->formatBytes((int)$size);

        $statusColorClass = $statusCode >= 200 && $statusCode < 300 ? 'text-green-400' : 'text-red-400';
        render('<div class="px-1 ' . $statusColorClass . '">✅ [' . $statusCode . '] ' . $url . ' (' . $sizeFormatted . ')</div>');
    }

    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null,
    ): void {
        $reason = $requestException->getMessage();

        $this->command->addFailedUrl((string) $url, $reason);
        render('<div class="px-1 text-red-500">❌ Failed: ' . $url . ' - ' . $this->truncateString($reason, 60) . '</div>');
    }

    public function finishedCrawling(): void
    {
        render('<div class="px-1 bg-green-500 text-white font-bold">🏁 Finished crawling all URLs</div>');
    }

    protected function formatBytes(int $size): string
    {
        if ($size === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 1) . ' ' . $units[$unitIndex];
    }

    protected function truncateString(string $string, int $length = 50): string
    {
        return strlen($string) > $length ? substr($string, 0, $length) . '...' : $string;
    }
}
