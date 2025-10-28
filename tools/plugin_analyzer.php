#!/usr/bin/env php
<?php
/**
 * Plugin Analyzer for OctoberCMS v4
 * ----------------------------------
 * Usage:
 *   php tools/plugin-analyzer.php plugins/omsb/feeder --out plugins/omsb/feeder/docs
 */

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Finder\Finder;

function title($text) {
    return str_repeat('=', strlen($text)) . "\n$text\n" . str_repeat('=', strlen($text)) . "\n";
}

function write($path, $content) {
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $content);
}

function parseYamlFiles($pluginPath) {
    $finder = new Finder();
    $finder->files()->in($pluginPath)->name('*.yaml');

    $files = [];
    foreach ($finder as $file) {
        $files[] = [
            'path' => $file->getRelativePathname(),
            'size' => $file->getSize(),
            'lines' => substr_count($file->getContents(), "\n")
        ];
    }
    return $files;
}

function analyzePlugin($pluginPath, $outputPath) {
    $pluginName = basename($pluginPath);
    echo "🔍 Analyzing plugin: {$pluginName}\n";

    $docs = [];

    // Plugin info.php
    $infoPath = $pluginPath . '/Plugin.php';
    $infoSummary = '';
    if (file_exists($infoPath)) {
        $content = file_get_contents($infoPath);
        preg_match("/class\s+(\w+)/", $content, $matches);
        $className = $matches[1] ?? 'Unknown';
        $infoSummary = "Main plugin class: `$className`\n\nLocated at `$infoPath`.\n";
    }

    // Folder scan
    $folders = ['models', 'controllers', 'components', 'console', 'updates', 'classes'];
    $structure = [];
    foreach ($folders as $folder) {
        $dir = "$pluginPath/$folder";
        if (is_dir($dir)) {
            $files = glob("$dir/*.*");
            $structure[$folder] = array_map('basename', $files);
        }
    }

    // Generate docs
    $index = title("📘 {$pluginName} Plugin Documentation") .
        "Generated on " . date('Y-m-d H:i:s') . "\n\n" .
        "## Overview\n" .
        "$infoSummary\n" .
        "### Folder Structure\n";

    foreach ($structure as $type => $files) {
        $index .= "- **" . ucfirst($type) . "** (" . count($files) . ")\n";
        foreach ($files as $file) {
            $index .= "  - `$file`\n";
        }
    }

    // YAML summary
    $yamlFiles = parseYamlFiles($pluginPath);
    $yamlSummary = "\n\n## YAML Configuration Files\nFound " . count($yamlFiles) . " YAML files.\n";
    foreach ($yamlFiles as $f) {
        $yamlSummary .= "- `{$f['path']}` ({$f['lines']} lines)\n";
    }

    // API endpoints (routes)
    $routesFile = $pluginPath . '/routes.php';
    $routes = '';
    if (file_exists($routesFile)) {
        $lines = file($routesFile);
        foreach ($lines as $line) {
            if (preg_match('/Route::(get|post|put|delete)\([\'"](.*?)[\'"]/', $line, $m)) {
                $routes .= "- **{$m[1]}** → `{$m[2]}`\n";
            }
        }
    }

    if ($routes) {
        $index .= "\n\n## API / Routes\n" . $routes;
    }

    // Improvement recommendations
    $recommendations = <<<MD

## 🔧 Recommendations

Here are possible improvements and enhancements identified for this plugin:

- Add **docblocks** for models, controllers, and components for better API readability.
- Consider introducing **Custom Backend Behaviors** for repetitive logic.
- If not present, implement **event listeners** using `Event::listen()` for cross-plugin communication.
- Add **unit tests** in `tests/` for model validation and plugin integration.
- Provide **custom backend widgets** to display plugin data on the OctoberCMS dashboard.
- Explore **Service Providers** or **Facades** if the plugin exposes shared logic across other OMSB plugins.

MD;

    $index .= $yamlSummary . $recommendations;

    // Write output
    write("$outputPath/00_index.md", $index);
    echo "✅ Documentation written to $outputPath/00_index.md\n";
}

$args = $_SERVER['argv'];
if (count($args) < 3) {
    echo "Usage: php tools/plugin-analyzer.php <plugin-path> --out <output-dir>\n";
    exit(1);
}

$pluginPath = $args[1];
$outputFlagIndex = array_search('--out', $args);
$outputPath = $args[$outputFlagIndex + 1] ?? "$pluginPath/docs";

analyzePlugin($pluginPath, $outputPath);