# Website Crawler 🕷️

[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue)](https://php.net)
[![Laravel Zero](https://img.shields.io/badge/laravel--zero-%5E12.0-red)](https://laravel-zero.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A powerful, feature-rich website crawler built with Laravel Zero and enhanced with beautiful Termwind output. Perfect for SEO analysis, website auditing, and comprehensive site mapping.

## ✨ Features

- 🎨 **Beautiful Terminal Output** - Enhanced with Termwind for stunning, colorful console displays
- 📊 **Comprehensive Reporting** - Generates detailed JSON reports with statistics and metadata
- 📧 **Email Extraction** - Finds and exports email addresses to separate files
- 🔗 **Link Analysis** - Analyzes internal and external links with domain statistics
- 🗺️ **Sitemap Generation** - Creates XML sitemaps with priority and lastmod data
- 📄 **Metadata Extraction** - Captures titles, descriptions, keywords, and Open Graph data
- 🖼️ **Image Counting** - Tracks image usage across pages
- 📏 **Page Size Analysis** - Monitors page sizes and performance metrics
- 🚀 **Concurrent Crawling** - Configurable concurrency for faster crawling
- 🎭 **JavaScript Support** - Optional JavaScript execution for SPAs
- 📁 **Organized Output** - Automatically creates timestamped, site-specific folders
- ⚙️ **Highly Configurable** - Extensive options for customization

## 🚀 Installation

### Requirements

- PHP 8.2 or higher
- Composer

### Install via Composer

```bash
composer global require deloughry/website-crawler
```

### Install from Source

```bash
git clone https://github.com/deloughry/website-crawler.git
cd website-crawler
composer install
chmod +x website-crawler
```

## 📖 Usage

### Basic Usage

```bash
# Simple crawl with default settings
php website-crawler crawl:website https://example.com

# Crawl with custom depth and concurrency
php website-crawler crawl:website https://example.com --depth=5 --concurrency=5

# Full analysis with all features
php website-crawler crawl:website https://example.com --extract-emails --analyze-links --generate-sitemap
```

### Advanced Examples

```bash
# E-commerce site analysis
php website-crawler crawl:website https://shop.example.com \
  --depth=4 \
  --extract-emails \
  --analyze-links \
  --concurrency=3 \
  --delay=500

# JavaScript-heavy SPA crawling
php website-crawler crawl:website https://spa-app.com \
  --javascript \
  --extract-emails \
  --analyze-links \
  --generate-sitemap \
  --delay=1000

# Custom output directory
php website-crawler crawl:website https://example.com \
  --output-dir=./reports \
  --extract-emails \
  --generate-sitemap
```

## ⚙️ Configuration Options

| Option | Description | Default |
|--------|-------------|---------|
| `--depth` | Maximum crawl depth | 3 |
| `--concurrency` | Number of concurrent requests | 10 |
| `--delay` | Delay between requests (ms) | 250 |
| `--user-agent` | Custom user agent string | Laravel-Zero-Crawler |
| `--output-dir` | Custom output directory | System temp directory |
| `--no-output` | Disable automatic report generation | false |
| `--generate-sitemap` | Generate XML sitemap | false |
| `--javascript` | Execute JavaScript while crawling | false |
| `--extract-emails` | Extract email addresses | false |
| `--analyze-links` | Analyze internal and external links | false |

## 📁 Output Files

The crawler automatically generates organized output in timestamped directories:

```
/tmp/website-crawler/example.com_2024-01-15_14-30-25/
├── crawl-report.json      # Comprehensive crawl data
├── extracted-emails.txt   # Found email addresses (if --extract-emails)
└── sitemap.xml           # XML sitemap (if --generate-sitemap)
```

### Report Structure

The JSON report includes:

- **Crawl Settings** - Configuration used for the crawl
- **Statistics** - Success rates, counts, and performance metrics
- **Crawled URLs** - Detailed data for each successfully crawled page
- **Failed URLs** - Information about failed requests
- **Page Metadata** - Titles, descriptions, keywords, and Open Graph data
- **Email Addresses** - Extracted emails (if enabled)
- **Link Analysis** - Internal/external link breakdown (if enabled)

## 🎨 Terminal Output

The crawler features beautiful, colorful terminal output powered by Termwind:

- 🔵 **Blue** - General information and crawled URLs
- 🟢 **Green** - Success messages and completion status
- 🔴 **Red** - Errors and failed URLs
- 🟡 **Yellow** - Crawling progress and warnings
- 🟣 **Purple** - Email extraction results
- 🟠 **Orange** - Link analysis data

## 🔧 Development

### Running Tests

```bash
composer test

# With coverage
composer test-coverage
```

### Code Formatting

```bash
composer format
```

### Building

```bash
# Create a PHAR executable
php website-crawler app:build
```

## 📝 Examples

### SEO Audit

```bash
php website-crawler crawl:website https://yoursite.com \
  --depth=5 \
  --extract-emails \
  --analyze-links \
  --generate-sitemap \
  --user-agent="SEO Audit Bot"
```

### Quick Site Check

```bash
php website-crawler crawl:website https://yoursite.com \
  --depth=2 \
  --concurrency=5
```

### Comprehensive Analysis

```bash
php website-crawler crawl:website https://yoursite.com \
  --depth=10 \
  --concurrency=3 \
  --delay=1000 \
  --javascript \
  --extract-emails \
  --analyze-links \
  --generate-sitemap \
  --output-dir=./analysis-results
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Matthew Deloughry**
- Website: [deloughry.co.uk](https://deloughry.co.uk)
- Email: matthew@deloughry.co.uk
- GitHub: [@DR-DinoMight](https://github.com/DR-DinoMight)

## 🙏 Acknowledgments

- Built with [Laravel Zero](https://laravel-zero.com/)
- Powered by [Spatie Crawler](https://github.com/spatie/crawler)
- Enhanced with [Termwind](https://github.com/nunomaduro/termwind)

---

⭐ **Star this repository if you find it helpful!**
