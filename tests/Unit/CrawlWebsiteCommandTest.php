<?php

use App\Commands\CrawlWebsiteCommand;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

describe('CrawlWebsiteCommand', function () {
    beforeEach(function () {
        $this->command = new CrawlWebsiteCommand();
    });

    describe('URL validation', function () {
        it('validates valid URLs correctly', function () {
            $validUrls = [
                'https://example.com',
                'http://example.com',
                'https://subdomain.example.com',
                'https://example.com/path',
                'https://example.com:8080',
            ];

            foreach ($validUrls as $url) {
                expect(filter_var($url, FILTER_VALIDATE_URL))->not->toBeFalse();
            }
        });

        it('rejects invalid URLs', function () {
            $invalidUrls = [
                'not-a-url',
                'example.com',
                '',
                'javascript:alert(1)',
            ];

            foreach ($invalidUrls as $url) {
                expect(filter_var($url, FILTER_VALIDATE_URL))->toBeFalse();
            }
        });
    });

    describe('email extraction', function () {
        it('extracts valid email addresses from HTML content', function () {
            $html = '
                <html>
                    <body>
                        <p>Contact us at info@example.com</p>
                        <p>Support: support@test.org</p>
                        <p>Invalid: notanemail</p>
                    </body>
                </html>
            ';

            $method = new ReflectionMethod($this->command, 'extractEmails');
            $method->setAccessible(true);
            $emails = $method->invoke($this->command, $html);

            expect($emails)->toContain('info@example.com')
                ->and($emails)->toContain('support@test.org')
                ->and($emails)->not->toContain('notanemail');
        });

        it('filters out common false positives', function () {
            $html = '
                <html>
                    <body>
                        <p>Real email: real@example.com</p>
                        <p>Fake: example@example.com</p>
                        <p>Test: test@test.com</p>
                        <p>Image: image.png@example.com</p>
                    </body>
                </html>
            ';

            $method = new ReflectionMethod($this->command, 'extractEmails');
            $method->setAccessible(true);
            $emails = $method->invoke($this->command, $html);

            expect($emails)->toContain('real@example.com')
                ->and($emails)->not->toContain('example@example.com')
                ->and($emails)->not->toContain('test@test.com')
                ->and($emails)->not->toContain('image.png@example.com');
        });

        it('returns unique email addresses', function () {
            $html = '
                <html>
                    <body>
                        <p>Email: duplicate@example.com</p>
                        <p>Same email: duplicate@example.com</p>
                    </body>
                </html>
            ';

            $method = new ReflectionMethod($this->command, 'extractEmails');
            $method->setAccessible(true);
            $emails = $method->invoke($this->command, $html);

            expect($emails)->toHaveCount(1)
                ->and($emails[0])->toBe('duplicate@example.com');
        });
    });

    describe('metadata extraction', function () {
        it('extracts page title correctly', function () {
            $html = '<html><head><title>Test Page Title</title></head><body></body></html>';

            $method = new ReflectionMethod($this->command, 'extractMetadata');
            $method->setAccessible(true);
            $metadata = $method->invoke($this->command, $html);

            expect($metadata)->toHaveKey('title')
                ->and($metadata['title'])->toBe('Test Page Title');
        });

        it('extracts meta description', function () {
            $html = '<html><head><meta name="description" content="This is a test description"></head><body></body></html>';

            $method = new ReflectionMethod($this->command, 'extractMetadata');
            $method->setAccessible(true);
            $metadata = $method->invoke($this->command, $html);

            expect($metadata)->toHaveKey('description')
                ->and($metadata['description'])->toBe('This is a test description');
        });

        it('extracts Open Graph title', function () {
            $html = '<html><head><meta property="og:title" content="OG Test Title"></head><body></body></html>';

            $method = new ReflectionMethod($this->command, 'extractMetadata');
            $method->setAccessible(true);
            $metadata = $method->invoke($this->command, $html);

            expect($metadata)->toHaveKey('og_title')
                ->and($metadata['og_title'])->toBe('OG Test Title');
        });

        it('counts images correctly', function () {
            $html = '
                <html>
                    <body>
                        <img src="image1.jpg" alt="Image 1">
                        <img src="image2.png" alt="Image 2">
                        <img src="image3.gif">
                    </body>
                </html>
            ';

            $method = new ReflectionMethod($this->command, 'extractMetadata');
            $method->setAccessible(true);
            $metadata = $method->invoke($this->command, $html);

            expect($metadata)->toHaveKey('image_count')
                ->and($metadata['image_count'])->toBe(3);
        });

        it('counts headings correctly', function () {
            $html = '
                <html>
                    <body>
                        <h1>Heading 1</h1>
                        <h1>Another H1</h1>
                        <h2>Heading 2</h2>
                        <h3>Heading 3</h3>
                    </body>
                </html>
            ';

            $method = new ReflectionMethod($this->command, 'extractMetadata');
            $method->setAccessible(true);
            $metadata = $method->invoke($this->command, $html);

            expect($metadata)->toHaveKey('h1_count')
                ->and($metadata['h1_count'])->toBe(2)
                ->and($metadata)->toHaveKey('h2_count')
                ->and($metadata['h2_count'])->toBe(1)
                ->and($metadata)->toHaveKey('h3_count')
                ->and($metadata['h3_count'])->toBe(1);
        });

        it('calculates page size correctly', function () {
            $html = '<html><body>Test content</body></html>';

            $method = new ReflectionMethod($this->command, 'extractMetadata');
            $method->setAccessible(true);
            $metadata = $method->invoke($this->command, $html);

            expect($metadata)->toHaveKey('page_size')
                ->and($metadata['page_size'])->toBe(strlen($html));
        });
    });

    describe('link analysis', function () {
        it('analyzes internal and external links correctly', function () {
            $html = '
                <html>
                    <body>
                        <a href="https://example.com/page1">Internal Link 1</a>
                        <a href="/page2">Internal Link 2</a>
                        <a href="https://external.com">External Link</a>
                        <a href="mailto:test@example.com">Email Link</a>
                        <a href="javascript:void(0)">JS Link</a>
                        <a href="#">Anchor Link</a>
                    </body>
                </html>
            ';

            $method = new ReflectionMethod($this->command, 'analyzeLinks');
            $method->setAccessible(true);
            $links = $method->invoke($this->command, $html, 'https://example.com');

            expect($links)->toHaveKey('internal')
                ->and($links)->toHaveKey('external')
                ->and($links['internal'])->toHaveCount(2)
                ->and($links['external'])->toHaveCount(1);
        });

        it('converts relative URLs to absolute', function () {
            $html = '<a href="/relative-path">Relative Link</a>';

            $method = new ReflectionMethod($this->command, 'analyzeLinks');
            $method->setAccessible(true);
            $links = $method->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'][0]['url'])->toBe('https://example.com/relative-path');
        });

        it('extracts link text correctly', function () {
            $html = '<a href="/test">Test Link Text</a>';

            $method = new ReflectionMethod($this->command, 'analyzeLinks');
            $method->setAccessible(true);
            $links = $method->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'][0]['text'])->toBe('Test Link Text');
        });
    });

    describe('utility methods', function () {
        it('formats bytes correctly', function () {
            $method = new ReflectionMethod($this->command, 'formatBytes');
            $method->setAccessible(true);

            expect($method->invoke($this->command, 0))->toBe('0 B')
                ->and($method->invoke($this->command, 1024))->toBe('1 KB')
                ->and($method->invoke($this->command, 1048576))->toBe('1 MB')
                ->and($method->invoke($this->command, 1073741824))->toBe('1 GB')
                ->and($method->invoke($this->command, 512))->toBe('512 B')
                ->and($method->invoke($this->command, 1536))->toBe('1.5 KB');
        });

        it('truncates URLs correctly', function () {
            $method = new ReflectionMethod($this->command, 'truncateUrl');
            $method->setAccessible(true);

            $longUrl = 'https://example.com/very/long/path/that/should/be/truncated';
            $result = $method->invoke($this->command, $longUrl, 30);

            expect($result)->toHaveLength(33) // 30 + '...'
                ->and($result)->toEndWith('...');
        });

        it('truncates strings correctly', function () {
            $method = new ReflectionMethod($this->command, 'truncateString');
            $method->setAccessible(true);

            $longString = 'This is a very long string that should be truncated';
            $result = $method->invoke($this->command, $longString, 20);

            expect($result)->toHaveLength(23) // 20 + '...'
                ->and($result)->toEndWith('...');
        });
    });

    describe('output directory creation', function () {
        it('creates valid directory names from URLs', function () {
            $method = new ReflectionMethod($this->command, 'createOutputDirectory');
            $method->setAccessible(true);

            // Mock the command to avoid actual directory creation
            $command = Mockery::mock(CrawlWebsiteCommand::class)->makePartial();
            $command->shouldReceive('option')->with('output-dir')->andReturn(null);

            $result = $method->invoke($command, 'https://example.com');

            expect($result)->toContain('example.com')
                ->and($result)->toMatch('/\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}/');
        });

        it('handles www prefixes correctly', function () {
            $method = new ReflectionMethod($this->command, 'createOutputDirectory');
            $method->setAccessible(true);

            $command = Mockery::mock(CrawlWebsiteCommand::class)->makePartial();
            $command->shouldReceive('option')->with('output-dir')->andReturn(null);

            $result = $method->invoke($command, 'https://www.example.com');

            expect($result)->toContain('example.com')
                ->and($result)->not->toContain('www.example.com');
        });
    });

    describe('sitemap generation', function () {
        it('generates valid XML sitemap', function () {
            $command = new CrawlWebsiteCommand();

            // Set up test data
            $crawledUrls = [
                ['url' => 'https://example.com', 'status_code' => 200, 'crawled_at' => '2024-01-01 12:00:00'],
                ['url' => 'https://example.com/page1', 'status_code' => 200, 'crawled_at' => '2024-01-01 12:01:00'],
                ['url' => 'https://example.com/page2', 'status_code' => 404, 'crawled_at' => '2024-01-01 12:02:00'],
            ];

            $reflection = new ReflectionClass($command);
            $property = $reflection->getProperty('crawledUrls');
            $property->setAccessible(true);
            $property->setValue($command, $crawledUrls);

            $method = new ReflectionMethod($command, 'generateSitemap');
            $method->setAccessible(true);
            $sitemap = $method->invoke($command);

            expect($sitemap)->toContain('<?xml version="1.0" encoding="UTF-8"?>')
                ->and($sitemap)->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
                ->and($sitemap)->toContain('https://example.com')
                ->and($sitemap)->toContain('https://example.com/page1')
                ->and($sitemap)->not->toContain('https://example.com/page2'); // 404 should be excluded
        });
    });

    describe('mermaid visual sitemap', function () {
        it('generates Mermaid diagram with nodes and edges', function () {
            $command = new CrawlWebsiteCommand();

            // Prepare crawled URLs and metadata
            $crawledUrls = [
                ['url' => 'https://example.com', 'status_code' => 200, 'crawled_at' => '2024-01-01 12:00:00'],
                ['url' => 'https://example.com/about', 'status_code' => 200, 'crawled_at' => '2024-01-01 12:01:00'],
                ['url' => 'https://example.com/contact', 'status_code' => 200, 'crawled_at' => '2024-01-01 12:02:00'],
            ];

            $pageMetadata = [
                'https://example.com' => ['title' => 'Home'],
                'https://example.com/about' => ['title' => 'About Us'],
                'https://example.com/contact' => ['title' => 'Contact'],
            ];

            $analyzedLinks = [
                'https://example.com' => [
                    'internal' => [
                        ['url' => 'https://example.com/about', 'text' => 'About', 'found_on' => 'https://example.com'],
                        ['url' => 'https://example.com/contact', 'text' => 'Contact', 'found_on' => 'https://example.com'],
                    ],
                    'external' => [],
                ],
                'https://example.com/about' => [
                    'internal' => [
                        ['url' => 'https://example.com', 'text' => 'Home', 'found_on' => 'https://example.com/about'],
                    ],
                    'external' => [],
                ],
            ];

            // Set properties via reflection
            $reflection = new ReflectionClass($command);
            foreach (
                [
                    'crawledUrls' => $crawledUrls,
                    'pageMetadata' => $pageMetadata,
                    'analyzedLinks' => $analyzedLinks,
                ] as $prop => $value
            ) {
                $property = $reflection->getProperty($prop);
                $property->setAccessible(true);
                $property->setValue($command, $value);
            }

            $method = new ReflectionMethod($command, 'generateMermaidSitemap');
            $method->setAccessible(true);
            $diagram = $method->invoke($command);

            expect($diagram)->toContain('graph TD')
                ->and($diagram)->toContain('About Us')
                ->and($diagram)->toContain('Contact')
                ->and($diagram)->toContain('Home')
                ->and($diagram)->toContain('-->');
        });
    });
});
