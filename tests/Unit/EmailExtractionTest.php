<?php

use App\Commands\CrawlWebsiteCommand;

describe('Email Extraction', function () {
    beforeEach(function () {
        $this->command = new CrawlWebsiteCommand();
        $this->extractEmails = new ReflectionMethod($this->command, 'extractEmails');
        $this->extractEmails->setAccessible(true);
    });

    describe('valid email extraction', function () {
        it('extracts standard email formats', function () {
            $html = '
                <html>
                    <body>
                        <p>Contact: user@example.com</p>
                        <p>Support: support@company.org</p>
                        <p>Info: info@site.co.uk</p>
                    </body>
                </html>
            ';

            $emails = $this->extractEmails->invoke($this->command, $html);

            expect($emails)->toContain('user@example.com')
                ->and($emails)->toContain('support@company.org')
                ->and($emails)->toContain('info@site.co.uk');
        });

        it('extracts emails with numbers and special characters', function () {
            $html = '
                <html>
                    <body>
                        <p>User: user123@example.com</p>
                        <p>Test: test.email+tag@domain.co.uk</p>
                        <p>Dash: user-name@example-site.org</p>
                        <p>Underscore: user_name@example.com</p>
                    </body>
                </html>
            ';

            $emails = $this->extractEmails->invoke($this->command, $html);

            expect($emails)->toContain('user123@example.com')
                ->and($emails)->toContain('test.email+tag@domain.co.uk')
                ->and($emails)->toContain('user-name@example-site.org')
                ->and($emails)->toContain('user_name@example.com');
        });

        it('extracts emails from various HTML contexts', function () {
            $html = '
                <html>
                    <body>
                        <a href="mailto:link@example.com">Email Link</a>
                        <span>Inline email: inline@example.com</span>
                        <div>Block email: block@example.com</div>
                        <p>Paragraph email: paragraph@example.com</p>
                        <!-- Comment email: comment@example.com -->
                    </body>
                </html>
            ';

            $emails = $this->extractEmails->invoke($this->command, $html);

            expect($emails)->toContain('link@example.com')
                ->and($emails)->toContain('inline@example.com')
                ->and($emails)->toContain('block@example.com')
                ->and($emails)->toContain('paragraph@example.com')
                ->and($emails)->toContain('comment@example.com');
        });
    });

    describe('email filtering', function () {
        it('filters out blacklisted emails', function () {
            $html = '
                <html>
                    <body>
                        <p>Real: real@example.com</p>
                        <p>Fake: example@example.com</p>
                        <p>Test: test@test.com</p>
                        <p>Email: email@example.com</p>
                    </body>
                </html>
            ';

            $emails = $this->extractEmails->invoke($this->command, $html);

            expect($emails)->toContain('real@example.com')
                ->and($emails)->not->toContain('example@example.com')
                ->and($emails)->not->toContain('test@test.com')
                ->and($emails)->not->toContain('email@example.com');
        });

        it('filters out file extension false positives', function () {
            $html = '
                <html>
                    <body>
                        <p>Real email: real@example.com</p>
                        <p>Image: image.png@example.com</p>
                        <p>CSS: style.css@example.com</p>
                        <p>JS: script.js@example.com</p>
                        <p>JPEG: photo.jpeg@example.com</p>
                        <p>GIF: animation.gif@example.com</p>
                    </body>
                </html>
            ';

            $emails = $this->extractEmails->invoke($this->command, $html);

            expect($emails)->toContain('real@example.com')
                ->and($emails)->not->toContain('image.png@example.com')
                ->and($emails)->not->toContain('style.css@example.com')
                ->and($emails)->not->toContain('script.js@example.com')
                ->and($emails)->not->toContain('photo.jpeg@example.com')
                ->and($emails)->not->toContain('animation.gif@example.com');
        });
    });

    describe('email deduplication', function () {
        it('returns unique emails only', function () {
            $html = '
                <html>
                    <body>
                        <p>First: duplicate@example.com</p>
                        <p>Second: duplicate@example.com</p>
                        <p>Third: duplicate@example.com</p>
                        <p>Unique: unique@example.com</p>
                    </body>
                </html>
            ';

            $emails = $this->extractEmails->invoke($this->command, $html);

            expect($emails)->toHaveCount(2)
                ->and($emails)->toContain('duplicate@example.com')
                ->and($emails)->toContain('unique@example.com');
        });

        it('handles case sensitivity correctly', function () {
            $html = '
                <html>
                    <body>
                        <p>Lower: user@example.com</p>
                        <p>Upper: USER@EXAMPLE.COM</p>
                        <p>Mixed: User@Example.Com</p>
                    </body>
                </html>
            ';

            $emails = $this->extractEmails->invoke($this->command, $html);

            // Should extract all variations as they are technically different
            expect($emails)->toContain('user@example.com')
                ->and($emails)->toContain('USER@EXAMPLE.COM')
                ->and($emails)->toContain('User@Example.Com');
        });
    });

    describe('edge cases', function () {
        it('handles empty content', function () {
            $emails = $this->extractEmails->invoke($this->command, '');

            expect($emails)->toBeEmpty();
        });

        it('handles content with no emails', function () {
            $html = '
                <html>
                    <body>
                        <p>This is just regular text with no emails.</p>
                        <p>Some @ symbols but not @ valid @ emails @.</p>
                    </body>
                </html>
            ';

            $emails = $this->extractEmails->invoke($this->command, $html);

            expect($emails)->toBeEmpty();
        });

        it('handles malformed emails', function () {
            $html = '
                <html>
                    <body>
                        <p>Missing domain: user@</p>
                        <p>Missing user: @example.com</p>
                        <p>No TLD: user@domain</p>
                        <p>Double @: user@@example.com</p>
                        <p>Valid: valid@example.com</p>
                    </body>
                </html>
            ';

            $emails = $this->extractEmails->invoke($this->command, $html);

            expect($emails)->toHaveCount(1)
                ->and($emails)->toContain('valid@example.com');
        });

        it('handles very long content efficiently', function () {
            $longContent = str_repeat('Some text without emails. ', 1000);
            $longContent .= 'Contact us at test@example.com for more info.';
            $longContent .= str_repeat(' More text without emails.', 1000);

            $emails = $this->extractEmails->invoke($this->command, $longContent);

            expect($emails)->toHaveCount(1)
                ->and($emails)->toContain('test@example.com');
        });
    });
});
