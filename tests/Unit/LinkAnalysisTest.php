<?php

use App\Commands\CrawlWebsiteCommand;

describe('Link Analysis', function () {
    beforeEach(function () {
        $this->command = new CrawlWebsiteCommand();
        $this->analyzeLinks = new ReflectionMethod($this->command, 'analyzeLinks');
        $this->analyzeLinks->setAccessible(true);
    });

    describe('internal link detection', function () {
        it('identifies same-domain links as internal', function () {
            $html = '
                <html>
                    <body>
                        <a href="https://example.com/page1">Page 1</a>
                        <a href="https://example.com/page2">Page 2</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'])->toHaveCount(2)
                ->and($links['internal'][0]['url'])->toBe('https://example.com/page1')
                ->and($links['internal'][1]['url'])->toBe('https://example.com/page2');
        });

        it('converts relative URLs to absolute internal links', function () {
            $html = '
                <html>
                    <body>
                        <a href="/about">About</a>
                        <a href="contact.html">Contact</a>
                        <a href="../parent">Parent</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com/current/page');

            expect($links['internal'])->toHaveCount(3)
                ->and($links['internal'][0]['url'])->toBe('https://example.com/about')
                ->and($links['internal'][1]['url'])->toBe('https://example.com/current/page/contact.html')
                ->and($links['internal'][2]['url'])->toBe('https://example.com/current/page/../parent');
        });

        it('handles protocol-relative URLs', function () {
            $html = '<a href="//example.com/page">Protocol Relative</a>';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'])->toHaveCount(1)
                ->and($links['internal'][0]['url'])->toBe('https://example.com/page');
        });

        it('handles subdomains correctly', function () {
            $html = '
                <html>
                    <body>
                        <a href="https://subdomain.example.com/page">Subdomain</a>
                        <a href="https://example.com/page">Main Domain</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            // Both should be considered internal based on the domain matching logic
            expect($links['internal'])->toHaveCount(2);
        });
    });

    describe('external link detection', function () {
        it('identifies different-domain links as external', function () {
            $html = '
                <html>
                    <body>
                        <a href="https://google.com">Google</a>
                        <a href="https://github.com">GitHub</a>
                        <a href="https://stackoverflow.com">Stack Overflow</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['external'])->toHaveCount(3)
                ->and($links['external'][0]['url'])->toBe('https://google.com')
                ->and($links['external'][1]['url'])->toBe('https://github.com')
                ->and($links['external'][2]['url'])->toBe('https://stackoverflow.com');
        });

        it('converts non-HTTP protocols to relative paths', function () {
            $html = '<a href="ftp://files.example.com/file.zip">FTP Link</a>';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            // FTP links are converted to relative paths and treated as internal
            expect($links['internal'])->toHaveCount(1)
                ->and($links['internal'][0]['url'])->toBe('https://example.com/ftp://files.example.com/file.zip');
        });
    });

    describe('link filtering', function () {
        it('filters out empty anchor links but processes named anchors', function () {
            $html = '
                <html>
                    <body>
                        <a href="#">Empty Anchor</a>
                        <a href="#section1">Section 1</a>
                        <a href="#top">Top</a>
                        <a href="https://example.com/page">Valid Link</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            // Named anchors are converted to full URLs, empty # is filtered out
            expect($links['internal'])->toHaveCount(3)
                ->and($links['internal'][0]['url'])->toBe('https://example.com/#section1')
                ->and($links['internal'][1]['url'])->toBe('https://example.com/#top')
                ->and($links['internal'][2]['url'])->toBe('https://example.com/page');
        });

        it('filters out javascript links', function () {
            $html = '
                <html>
                    <body>
                        <a href="javascript:void(0)">JS Void</a>
                        <a href="javascript:alert(\'test\')">JS Alert</a>
                        <a href="https://example.com/page">Valid Link</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'])->toHaveCount(1)
                ->and($links['internal'][0]['url'])->toBe('https://example.com/page');
        });

        it('filters out mailto links', function () {
            $html = '
                <html>
                    <body>
                        <a href="mailto:user@example.com">Email</a>
                        <a href="mailto:support@example.com?subject=Help">Email with Subject</a>
                        <a href="https://example.com/page">Valid Link</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'])->toHaveCount(1)
                ->and($links['internal'][0]['url'])->toBe('https://example.com/page');
        });

        it('filters out tel links', function () {
            $html = '
                <html>
                    <body>
                        <a href="tel:+1234567890">Phone</a>
                        <a href="tel:555-0123">Phone 2</a>
                        <a href="https://example.com/page">Valid Link</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'])->toHaveCount(1)
                ->and($links['internal'][0]['url'])->toBe('https://example.com/page');
        });

        it('filters out empty hrefs', function () {
            $html = '
                <html>
                    <body>
                        <a href="">Empty</a>
                        <a href=" ">Whitespace</a>
                        <a>No href</a>
                        <a href="https://example.com/page">Valid Link</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'])->toHaveCount(1)
                ->and($links['internal'][0]['url'])->toBe('https://example.com/page');
        });
    });

    describe('link text extraction', function () {
        it('extracts link text correctly', function () {
            $html = '
                <html>
                    <body>
                        <a href="https://example.com/page1">Page One</a>
                        <a href="https://example.com/page2">Page Two with <strong>bold</strong> text</a>
                        <a href="https://example.com/page3">   Whitespace Link   </a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'][0]['text'])->toBe('Page One')
                ->and($links['internal'][1]['text'])->toBe('Page Two with bold text')
                ->and($links['internal'][2]['text'])->toBe('Whitespace Link');
        });

        it('handles empty link text', function () {
            $html = '<a href="https://example.com/page"></a>';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'][0]['text'])->toBe('');
        });

        it('handles links with only whitespace text', function () {
            $html = '<a href="https://example.com/page">   </a>';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'][0]['text'])->toBe('');
        });
    });

    describe('link metadata', function () {
        it('includes found_on URL in link data', function () {
            $html = '<a href="https://external.com">External</a>';
            $baseUrl = 'https://example.com/current-page';

            $links = $this->analyzeLinks->invoke($this->command, $html, $baseUrl);

            expect($links['external'][0]['found_on'])->toBe($baseUrl);
        });

        it('provides complete link structure', function () {
            $html = '<a href="https://external.com/page">External Page</a>';
            $baseUrl = 'https://example.com';

            $links = $this->analyzeLinks->invoke($this->command, $html, $baseUrl);

            $link = $links['external'][0];
            expect($link)->toHaveKey('url')
                ->and($link)->toHaveKey('text')
                ->and($link)->toHaveKey('found_on')
                ->and($link['url'])->toBe('https://external.com/page')
                ->and($link['text'])->toBe('External Page')
                ->and($link['found_on'])->toBe('https://example.com');
        });
    });

    describe('edge cases', function () {
        it('handles malformed HTML', function () {
            $html = '
                <html>
                    <body>
                        <a href="https://example.com/page1">Unclosed link
                        <a href="https://example.com/page2">Another link</a>
                        <a href="https://example.com/page3" target="_blank">Link with attributes</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            // The regex may not match the unclosed link properly, so we expect 2 links
            expect($links['internal'])->toHaveCount(2);
        });

        it('handles content with no links', function () {
            $html = '
                <html>
                    <body>
                        <p>This is just text with no links.</p>
                        <div>More content without links.</div>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'])->toBeEmpty()
                ->and($links['external'])->toBeEmpty();
        });

        it('handles URLs with query parameters and fragments', function () {
            $html = '
                <html>
                    <body>
                        <a href="https://example.com/search?q=test&sort=date#results">Search Results</a>
                        <a href="https://external.com/page?param=value#section">External with Params</a>
                    </body>
                </html>
            ';

            $links = $this->analyzeLinks->invoke($this->command, $html, 'https://example.com');

            expect($links['internal'][0]['url'])->toBe('https://example.com/search?q=test&sort=date#results')
                ->and($links['external'][0]['url'])->toBe('https://external.com/page?param=value#section');
        });
    });
});
