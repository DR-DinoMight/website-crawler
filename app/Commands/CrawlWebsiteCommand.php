<?php

namespace App\Commands;

use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlProfiles\CrawlInternalUrls;
use LaravelZero\Framework\Commands\Command;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use function Termwind\{render};

class CrawlWebsiteCommand extends Command
{
    protected $signature = 'crawl:website
                          {url : The URL to start crawling from}
                          {--depth=3 : Maximum crawl depth}
                          {--delay=250 : Delay between requests in milliseconds}
                          {--concurrency=10 : Number of concurrent requests}
                          {--user-agent=Laravel-Zero-Crawler : User agent string}
                          {--output-dir= : Custom output directory path (defaults to temp directory)}
                          {--no-output : Disable automatic report generation}
                          {--generate-sitemap : Generate XML sitemap}
                          {--visual-sitemap : Generate Mermaid visual sitemap}
                          {--javascript : Execute JavaScript while crawling}
                          {--extract-emails : Extract email addresses from pages}
                          {--analyze-links : Analyze internal and external links}';

    protected $description = 'Crawl a website and extract comprehensive information';

    protected $crawledUrls = [];
    protected $failedUrls = [];
    protected $extractedEmails = [];
    protected $analyzedLinks = [];
    protected $pageMetadata = [];
    protected $outputDirectory = '';
    protected $generatedFiles = [];

    public function handle(): int
    {
        $url = $this->argument('url');

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            render('<div class="px-1 bg-red-500 text-white font-bold">❌ Invalid URL provided</div>');
            return self::FAILURE;
        }

        render('<div class="px-1 bg-blue-500 text-white font-bold">🕷️ Starting to crawl: ' . $url . '</div>');

        // Create output directory
        $this->outputDirectory = $this->createOutputDirectory($url);
        render('<div class="px-1 bg-gray-700 text-white">📁 Output directory: ' . $this->outputDirectory . '</div>');

        $this->displayCrawlSettings();
        render('<br>');

        // Create and configure crawler
        $crawler = Crawler::create()
            ->setCrawlObserver(new WebsiteCrawlObserver($this))
            ->setCrawlProfile(new CrawlInternalUrls($url))
            ->setConcurrency($this->option('concurrency'))
            ->setDelayBetweenRequests($this->option('delay'))
            ->setUserAgent($this->option('user-agent'));

        // Enable JavaScript execution if requested
        if ($this->option('javascript')) {
            $crawler->executeJavaScript();
            render('<div class="px-1 bg-yellow-500 text-black">📜 JavaScript execution enabled</div>');
        }

        // Set maximum crawl depth
        $crawler->setMaximumDepth($this->option('depth'));

        // Start crawling with progress indication
        $this->task('Crawling website', function () use ($crawler, $url) {
            $crawler->startCrawling($url);
        });

        $this->displayResults();

        // Generate report by default (unless disabled)
        if (!$this->option('no-output')) {
            $this->generateAndSaveReport();
        }

        // Generate sitemap if requested
        if ($this->option('generate-sitemap')) {
            $this->generateAndSaveSitemap();
        }

        // Generate Mermaid sitemap if requested
        if ($this->option('visual-sitemap')) {
            $this->generateAndSaveMermaid();
        }

        // Generate email file if emails were extracted
        if ($this->option('extract-emails') && !empty($this->extractedEmails)) {
            $this->generateAndSaveEmailFile();
        }

        // Display generated files
        $this->displayGeneratedFiles();

        return self::SUCCESS;
    }

    protected function displayCrawlSettings(): void
    {
        render('<div class="px-1 bg-gray-800 text-white font-bold mb-1">⚙️ Crawl Settings</div>');

        $outputBase = $this->option('output-dir') ?? sys_get_temp_dir() . '/website-crawler';

        $settings = [
            'Max Depth' => $this->option('depth'),
            'Concurrency' => $this->option('concurrency'),
            'Delay (ms)' => $this->option('delay'),
            'User Agent' => $this->option('user-agent'),
            'Output Base' => $outputBase,
            'JavaScript' => $this->option('javascript') ? 'Yes' : 'No',
            'Extract Emails' => $this->option('extract-emails') ? 'Yes' : 'No',
            'Analyze Links' => $this->option('analyze-links') ? 'Yes' : 'No',
            'Generate Report' => !$this->option('no-output') ? 'Yes' : 'No',
            'Generate Sitemap' => $this->option('generate-sitemap') ? 'Yes' : 'No',
            'Visual Sitemap' => $this->option('visual-sitemap') ? 'Yes' : 'No',
        ];

        foreach ($settings as $setting => $value) {
            render('<div class="px-2"><span class="text-cyan-400 font-bold">' . $setting . ':</span> <span class="text-white">' . $value . '</span></div>');
        }
    }

    public function addCrawledUrl(
        string $url,
        int $statusCode,
        string $contentType = '',
        ResponseInterface $response = null
    ): void {
        $urlData = [
            'url' => $url,
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'crawled_at' => now()->toDateTimeString(),
        ];

        // Extract additional data if response is provided
        if ($response) {
            // Get body content - ensure we can read it
            $response->getBody()->rewind(); // Reset stream position
            $body = $response->getBody()->getContents();

            // Extract emails if requested
            if ($this->option('extract-emails')) {
                $emails = $this->extractEmails($body);
                if (!empty($emails)) {
                    $this->extractedEmails = array_merge($this->extractedEmails, $emails);
                    $urlData['emails'] = $emails;
                }
            }

            // Analyze links if requested or needed for Mermaid generation
            if ($this->option('analyze-links') || $this->option('visual-sitemap')) {
                $links = $this->analyzeLinks($body, $url);
                if (!empty($links)) {
                    $this->analyzedLinks[$url] = $links;
                    $urlData['links_count'] = count($links['internal']) + count($links['external']);
                }
            }

            // Extract page metadata
            $metadata = $this->extractMetadata($body);
            $urlData = array_merge($urlData, $metadata);
            $this->pageMetadata[$url] = $metadata;
        }

        $this->crawledUrls[] = $urlData;
    }

    public function addFailedUrl(string $url, string $reason): void
    {
        $this->failedUrls[] = [
            'url' => $url,
            'reason' => $reason,
            'failed_at' => now()->toDateTimeString(),
        ];
    }

    protected function extractEmails(string $body): array
    {
        // More comprehensive email regex
        $emailPattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/';
        preg_match_all($emailPattern, $body, $matches);

        $emails = array_unique($matches[0]);

        // Filter out common false positives
        $filteredEmails = array_filter($emails, function ($email) {
            $blacklist = ['example@example.com', 'test@test.com', 'email@example.com'];
            return !in_array(strtolower($email), $blacklist) &&
                !preg_match('/\.(png|jpg|jpeg|gif|css|js)@/i', $email);
        });

        return array_values($filteredEmails);
    }

    protected function analyzeLinks(string $body, string $baseUrl): array
    {
        $linkPattern = '/<a[^>]+href=([\'"])([^\'"]+)\1[^>]*>(.*?)<\/a>/i';
        preg_match_all($linkPattern, $body, $matches, PREG_SET_ORDER);

        $parsedBaseUrl = parse_url($baseUrl);
        $baseDomain = $parsedBaseUrl['host'] ?? '';

        $internal = [];
        $external = [];

        foreach ($matches as $match) {
            $href = trim($match[2]);
            $linkText = trim(strip_tags($match[3]));

            // Skip empty hrefs, anchors, javascript links, and mailto links
            if (
                empty($href) ||
                $href === '#' ||
                strpos($href, 'javascript:') === 0 ||
                strpos($href, 'mailto:') === 0 ||
                strpos($href, 'tel:') === 0
            ) {
                continue;
            }

            // Convert relative URLs to absolute
            if (strpos($href, '//') === 0) {
                $href = ($parsedBaseUrl['scheme'] ?? 'http') . ':' . $href;
            } elseif (strpos($href, '/') === 0) {
                $href = ($parsedBaseUrl['scheme'] ?? 'http') . '://' . $baseDomain . $href;
            } elseif (!preg_match('/^https?:\/\//', $href)) {
                $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
            }

            $linkData = [
                'url' => $href,
                'text' => $linkText,
                'found_on' => $baseUrl,
            ];

            // Determine if link is internal or external
            $linkDomain = parse_url($href, PHP_URL_HOST);
            if ($linkDomain && ($linkDomain === $baseDomain ||
                strpos($linkDomain, '.' . $baseDomain) !== false ||
                strpos($baseDomain, '.' . $linkDomain) !== false)) {
                $internal[] = $linkData;
            } else {
                $external[] = $linkData;
            }
        }

        return [
            'internal' => $internal,
            'external' => $external,
        ];
    }

    protected function extractMetadata(string $body): array
    {
        $metadata = [];

        // Extract title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            $metadata['title'] = trim(strip_tags($matches[1]));
        }

        // Extract meta description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $body, $matches)) {
            $metadata['description'] = trim($matches[1]);
        }

        // Extract meta keywords
        if (preg_match('/<meta[^>]+name=["\']keywords["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $body, $matches)) {
            $metadata['keywords'] = trim($matches[1]);
        }

        // Extract Open Graph title
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $body, $matches)) {
            $metadata['og_title'] = trim($matches[1]);
        }

        // Extract canonical URL
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)["\'][^>]*>/i', $body, $matches)) {
            $metadata['canonical'] = trim($matches[1]);
        }

        // Count headings
        for ($i = 1; $i <= 6; $i++) {
            $count = preg_match_all("/<h{$i}[^>]*>.*?<\/h{$i}>/is", $body);
            if ($count > 0) {
                $metadata["h{$i}_count"] = $count;
            }
        }

        // Count images
        $imageCount = preg_match_all('/<img[^>]*>/i', $body);
        $metadata['image_count'] = $imageCount;

        // Page size
        $metadata['page_size'] = strlen($body);

        return $metadata;
    }

    protected function displayResults(): void
    {
        render('<br>');
        render('<div class="px-1 bg-green-500 text-white font-bold">✅ Crawling completed!</div>');
        render('<br>');

        // Display statistics
        $totalCrawled = count($this->crawledUrls);
        $totalFailed = count($this->failedUrls);
        $totalEmails = count(array_unique($this->extractedEmails));

        render('<div class="px-1 bg-gray-800 text-white font-bold mb-1">📊 Statistics</div>');

        $stats = [
            'URLs Crawled' => $totalCrawled,
            'URLs Failed' => $totalFailed,
            'Success Rate' => $totalCrawled > 0 ? round(($totalCrawled / ($totalCrawled + $totalFailed)) * 100, 2) . '%' : '0%',
        ];

        if ($this->option('extract-emails')) {
            $stats['Unique Emails Found'] = $totalEmails;
        }

        if ($this->option('analyze-links')) {
            $totalInternalLinks = 0;
            $totalExternalLinks = 0;
            foreach ($this->analyzedLinks as $links) {
                $totalInternalLinks += count($links['internal']);
                $totalExternalLinks += count($links['external']);
            }
            $stats['Internal Links Found'] = $totalInternalLinks;
            $stats['External Links Found'] = $totalExternalLinks;
        }

        foreach ($stats as $metric => $count) {
            render('<div class="px-2"><span class="text-cyan-400 font-bold">' . $metric . ':</span> <span class="text-white">' . $count . '</span></div>');
        }

        // Show crawled URLs with enhanced data
        if ($totalCrawled > 0) {
            $this->displayCrawledUrls();
        }

        // Show extracted emails
        if ($this->option('extract-emails') && $totalEmails > 0) {
            $this->displayExtractedEmails();
        }

        // Show link analysis
        if ($this->option('analyze-links')) {
            $this->displayLinkAnalysis();
        }

        // Show failed URLs if any
        if ($totalFailed > 0) {
            $this->displayFailedUrls();
        }
    }

    protected function displayCrawledUrls(): void
    {
        render('<br>');
        render('<div class="px-1 bg-blue-500 text-white font-bold">📄 Crawled URLs</div>');

        $urlsToShow = array_slice($this->crawledUrls, 0, 15);

        foreach ($urlsToShow as $item) {
            $statusColor = $item['status_code'] >= 200 && $item['status_code'] < 300 ? 'text-green-400' : 'text-red-400';
            $url = $this->truncateUrl($item['url']);
            $title = $this->truncateString($item['title'] ?? 'N/A', 30);
            $size = $this->formatBytes($item['page_size'] ?? 0);

            render('<div class="px-2 text-white">' . $url . ' <span class="' . $statusColor . ' font-bold">[' . $item['status_code'] . ']</span> <span class="text-gray-300">' . $title . '</span> <span class="text-yellow-300">' . $size . '</span></div>');
        }

        if (count($this->crawledUrls) > 15) {
            render('<div class="px-2 text-gray-400">... and ' . (count($this->crawledUrls) - 15) . ' more URLs</div>');
        }
    }

    protected function displayExtractedEmails(): void
    {
        render('<br>');
        render('<div class="px-1 bg-purple-500 text-white font-bold">📧 Extracted Emails</div>');

        $uniqueEmails = array_unique($this->extractedEmails);
        $emailChunks = array_chunk($uniqueEmails, 3);

        foreach (array_slice($emailChunks, 0, 10) as $chunk) {
            render('<div class="px-2 text-cyan-300">• ' . implode(' | ', $chunk) . '</div>');
        }

        if (count($emailChunks) > 10) {
            render('<div class="px-2 text-gray-400">... and ' . ((count($emailChunks) - 10) * 3) . ' more emails</div>');
        }
    }

    protected function displayLinkAnalysis(): void
    {
        render('<br>');
        render('<div class="px-1 bg-orange-500 text-white font-bold">🔗 Link Analysis Summary</div>');

        $totalInternal = 0;
        $totalExternal = 0;
        $externalDomains = [];

        foreach ($this->analyzedLinks as $pageUrl => $links) {
            $totalInternal += count($links['internal']);
            $totalExternal += count($links['external']);

            foreach ($links['external'] as $link) {
                $domain = parse_url($link['url'], PHP_URL_HOST);
                if ($domain) {
                    $externalDomains[$domain] = ($externalDomains[$domain] ?? 0) + 1;
                }
            }
        }

        // Show top external domains
        if (!empty($externalDomains)) {
            arsort($externalDomains);
            render('<br>');
            render('<div class="px-1 bg-teal-500 text-white font-bold">🌐 Top External Domains</div>');

            foreach (array_slice($externalDomains, 0, 10, true) as $domain => $count) {
                render('<div class="px-2"><span class="text-cyan-400 font-bold">' . $domain . ':</span> <span class="text-white">' . $count . '</span></div>');
            }
        }
    }

    protected function displayFailedUrls(): void
    {
        render('<br>');
        render('<div class="px-1 bg-red-500 text-white font-bold">❌ Failed URLs</div>');

        $failedUrls = array_slice($this->failedUrls, 0, 10);

        foreach ($failedUrls as $item) {
            $url = $this->truncateUrl($item['url']);
            $reason = $this->truncateString($item['reason'], 50);

            render('<div class="px-2"><span class="text-red-300 font-bold">' . $url . ':</span> <span class="text-gray-300">' . $reason . '</span></div>');
        }
    }

    protected function generateAndSaveSitemap(): void
    {
        $sitemapPath = $this->outputDirectory . '/sitemap.xml';
        $sitemap = $this->generateSitemap();

        file_put_contents($sitemapPath, $sitemap);
        $this->generatedFiles[] = $sitemapPath;
        render('<div class="px-1 bg-green-500 text-white">🗺️ Sitemap generated</div>');
    }

    protected function generateSitemap(): string
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($this->crawledUrls as $urlData) {
            if ($urlData['status_code'] === 200) {
                $sitemap .= "  <url>\n";
                $sitemap .= "    <loc>" . htmlspecialchars($urlData['url']) . "</loc>\n";
                $sitemap .= "    <lastmod>" . date('c', strtotime($urlData['crawled_at'])) . "</lastmod>\n";

                // Add priority based on URL depth
                $depth = substr_count(parse_url($urlData['url'], PHP_URL_PATH), '/');
                $priority = max(0.1, 1.0 - ($depth * 0.1));
                $sitemap .= "    <priority>" . number_format($priority, 1) . "</priority>\n";

                $sitemap .= "  </url>\n";
            }
        }

        $sitemap .= '</urlset>';
        return $sitemap;
    }

    protected function generateAndSaveMermaid(): void
    {
        $mermaidPath = $this->outputDirectory . '/visual-sitemap.mmd';
        $diagram = $this->generateMermaidSitemap();

        file_put_contents($mermaidPath, $diagram);
        $this->generatedFiles[] = $mermaidPath;
        render('<div class="px-1 bg-green-500 text-white">🗺️ Mermaid sitemap generated</div>');
    }

    protected function generateMermaidSitemap(): string
    {
        $lines = [];
        $lines[] = 'graph TD';

        // Map each URL to a stable node id
        $urlToNodeId = [];
        $nextId = 1;
        $getNodeId = function (string $url) use (&$urlToNodeId, &$nextId): string {
            if (!isset($urlToNodeId[$url])) {
                $urlToNodeId[$url] = 'N' . $nextId++;
            }
            return $urlToNodeId[$url];
        };

        // Helper to create a readable label for a URL
        $labelForUrl = function (string $url): string {
            $parsed = parse_url($url);
            $path = $parsed['path'] ?? '/';
            $host = $parsed['host'] ?? '';
            $label = ($host ? $host : '') . $path;
            if (isset($parsed['query'])) {
                $label .= '?' . $parsed['query'];
            }
            return $label;
        };

        // Prefer page titles when available
        $getTitleForUrl = function (string $url) use ($labelForUrl): string {
            $title = $this->pageMetadata[$url]['title'] ?? null;
            $label = $title && $title !== '' ? $title : $labelForUrl($url);
            // Escape quotes for Mermaid label
            $label = str_replace(["\\", '"'], ["\\\\", '\\"'], $label);
            // Limit length to keep diagram readable
            if (strlen($label) > 80) {
                $label = substr($label, 0, 77) . '...';
            }
            return $label;
        };

        // Build set of nodes and edges from analyzed internal links
        $edges = [];
        foreach ($this->analyzedLinks as $sourceUrl => $links) {
            foreach ($links['internal'] as $link) {
                $targetUrl = $link['url'];
                $srcId = $getNodeId($sourceUrl);
                $tgtId = $getNodeId($targetUrl);
                $edges[$srcId . ' --> ' . $tgtId] = true;
            }
        }

        // Ensure all crawled URLs appear as nodes, even without edges
        foreach ($this->crawledUrls as $urlData) {
            $getNodeId($urlData['url']);
        }

        // Emit node declarations with readable labels
        foreach ($urlToNodeId as $url => $nodeId) {
            $label = $getTitleForUrl($url);
            $lines[] = $nodeId . '["' . $label . '"]';
        }

        // Emit edges
        foreach (array_keys($edges) as $edge) {
            $lines[] = $edge;
        }

        return implode("\n", $lines) . "\n";
    }

    protected function truncateUrl(string $url, int $length = 60): string
    {
        return strlen($url) > $length ? substr($url, 0, $length) . '...' : $url;
    }

    protected function truncateString(string $string, int $length = 50): string
    {
        return strlen($string) > $length ? substr($string, 0, $length) . '...' : $string;
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

    protected function generateAndSaveReport(): void
    {
        $reportPath = $this->outputDirectory . '/crawl-report.json';

        $data = [
            'crawled_at' => now()->toDateTimeString(),
            'site_url' => $this->argument('url'),
            'crawl_settings' => [
                'depth' => $this->option('depth'),
                'concurrency' => $this->option('concurrency'),
                'delay' => $this->option('delay'),
                'javascript' => $this->option('javascript'),
                'extract_emails' => $this->option('extract-emails'),
                'analyze_links' => $this->option('analyze-links'),
            ],
            'statistics' => [
                'total_crawled' => count($this->crawledUrls),
                'total_failed' => count($this->failedUrls),
                'success_rate' => count($this->crawledUrls) > 0 ? round((count($this->crawledUrls) / (count($this->crawledUrls) + count($this->failedUrls))) * 100, 2) : 0,
            ],
            'crawled_urls' => $this->crawledUrls,
            'failed_urls' => $this->failedUrls,
            'page_metadata' => $this->pageMetadata,
        ];

        // Add extracted emails if option was enabled
        if ($this->option('extract-emails')) {
            $data['extracted_emails'] = array_unique($this->extractedEmails);
            $data['statistics']['unique_emails'] = count(array_unique($this->extractedEmails));
        }

        // Add link analysis if option was enabled
        if ($this->option('analyze-links')) {
            $data['analyzed_links'] = $this->analyzedLinks;
            $totalInternal = 0;
            $totalExternal = 0;
            foreach ($this->analyzedLinks as $links) {
                $totalInternal += count($links['internal']);
                $totalExternal += count($links['external']);
            }
            $data['statistics']['internal_links'] = $totalInternal;
            $data['statistics']['external_links'] = $totalExternal;
        }

        file_put_contents($reportPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->generatedFiles[] = $reportPath;
        render('<div class="px-1 bg-green-500 text-white">💾 Crawl report generated</div>');
    }

    protected function generateAndSaveEmailFile(): void
    {
        $emailPath = $this->outputDirectory . '/extracted-emails.txt';

        $uniqueEmails = array_unique($this->extractedEmails);
        sort($uniqueEmails); // Sort alphabetically

        $emailContent = "# Extracted Email Addresses\n";
        $emailContent .= "# Crawled from: " . $this->argument('url') . "\n";
        $emailContent .= "# Generated on: " . now()->toDateTimeString() . "\n";
        $emailContent .= "# Total unique emails: " . count($uniqueEmails) . "\n\n";

        foreach ($uniqueEmails as $email) {
            $emailContent .= $email . "\n";
        }

        file_put_contents($emailPath, $emailContent);
        $this->generatedFiles[] = $emailPath;
        render('<div class="px-1 bg-green-500 text-white">📧 Email file generated</div>');
    }

    protected function createOutputDirectory(string $url): string
    {
        // Parse the URL to get a clean site name
        $parsedUrl = parse_url($url);
        $siteName = $parsedUrl['host'] ?? 'unknown-site';

        // Remove www. prefix if present
        $siteName = preg_replace('/^www\./', '', $siteName);

        // Create a safe directory name
        $siteName = preg_replace('/[^a-zA-Z0-9.-]/', '_', $siteName);

        // Create timestamp for uniqueness
        $timestamp = now()->format('Y-m-d_H-i-s');

        // Determine base directory (custom or default temp)
        $baseDir = $this->option('output-dir') ?? sys_get_temp_dir() . '/website-crawler';

        // Ensure base directory is absolute path
        if (!str_starts_with($baseDir, '/')) {
            $baseDir = getcwd() . '/' . $baseDir;
        }

        // Create the full directory path
        $outputDir = $baseDir . '/' . $siteName . '_' . $timestamp;

        // Create the directory if it doesn't exist
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        return $outputDir;
    }

    protected function displayGeneratedFiles(): void
    {
        if (empty($this->generatedFiles)) {
            return;
        }

        render('<br>');
        render('<div class="px-1 bg-purple-500 text-white font-bold">📁 Generated Files</div>');

        foreach ($this->generatedFiles as $filePath) {
            $fileName = basename($filePath);
            $fileSize = $this->formatBytes(filesize($filePath));
            render('<div class="px-2 text-cyan-300">📄 ' . $fileName . ' <span class="text-gray-400">(' . $fileSize . ')</span></div>');
        }

        render('<div class="px-2 text-yellow-300 font-bold">📂 All files saved to: ' . $this->outputDirectory . '</div>');
    }
}
