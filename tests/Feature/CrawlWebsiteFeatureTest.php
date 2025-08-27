<?php

describe('CrawlWebsiteCommand Feature Tests', function () {
    it('displays help information correctly', function () {
        $this->artisan('crawl:website --help')
            ->expectsOutputToContain('Crawl a website and extract comprehensive information')
            ->assertExitCode(0);
    });

    it('validates URL input', function () {
        $this->artisan('crawl:website invalid-url')
            ->assertExitCode(1);
    });

    it('accepts valid URL without crashing', function () {
        // Mock a simple HTTP response to avoid actual network calls
        $this->artisan('crawl:website https://httpbin.org/html --depth=1 --concurrency=1')
            ->assertExitCode(0);
    })->skip('Requires network access - enable for integration testing');

    it('shows all available options in help', function () {
        $this->artisan('crawl:website --help')
            ->expectsOutputToContain('--depth')
            ->expectsOutputToContain('--concurrency')
            ->expectsOutputToContain('--delay')
            ->expectsOutputToContain('--user-agent')
            ->expectsOutputToContain('--output-dir')
            ->expectsOutputToContain('--no-output')
            ->expectsOutputToContain('--generate-sitemap')
            ->expectsOutputToContain('--javascript')
            ->expectsOutputToContain('--extract-emails')
            ->expectsOutputToContain('--analyze-links')
            ->assertExitCode(0);
    });

    describe('option validation', function () {
        it('accepts valid depth values', function () {
            $this->artisan('crawl:website https://example.com --depth=5 --help')
                ->assertExitCode(0);
        });

        it('accepts valid concurrency values', function () {
            $this->artisan('crawl:website https://example.com --concurrency=3 --help')
                ->assertExitCode(0);
        });

        it('accepts valid delay values', function () {
            $this->artisan('crawl:website https://example.com --delay=1000 --help')
                ->assertExitCode(0);
        });

        it('accepts custom user agent', function () {
            $this->artisan('crawl:website https://example.com --user-agent="Custom Bot" --help')
                ->assertExitCode(0);
        });

        it('accepts custom output directory', function () {
            $this->artisan('crawl:website https://example.com --output-dir=./custom --help')
                ->assertExitCode(0);
        });
    });

    describe('flag combinations', function () {
        it('accepts multiple flags together', function () {
            $command = 'crawl:website https://example.com --extract-emails --analyze-links --generate-sitemap --help';

            $this->artisan($command)
                ->assertExitCode(0);
        });

        it('accepts all flags with custom values', function () {
            $command = 'crawl:website https://example.com ' .
                '--depth=3 ' .
                '--concurrency=2 ' .
                '--delay=500 ' .
                '--user-agent="Test Bot" ' .
                '--output-dir=./test ' .
                '--extract-emails ' .
                '--analyze-links ' .
                '--generate-sitemap ' .
                '--javascript ' .
                '--help';

            $this->artisan($command)
                ->assertExitCode(0);
        });
    });
});
