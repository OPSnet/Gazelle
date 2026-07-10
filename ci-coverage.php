<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Serialization\Serializer;

class CoverageHelper {
    /*
     * ensure coverage is saved if die() is called somewhere
     */

    protected const TARGET_DIR = '/tmp/coverage';
    private CodeCoverage $coverage;

    public function __construct() {
        if (!is_dir($this::TARGET_DIR) && mkdir($this::TARGET_DIR, 0o755, true) === false) {
            throw new \Exception(
                "Directory " . $this::TARGET_DIR . " could not be created"
            );
        }
        $filter = new Filter();
        $filter->includeFiles($this->filenameList(__DIR__ . '/app'));
        $filter->includeFiles($this->filenameList(__DIR__ . '/classes'));
        $filter->includeFiles($this->filenameList(__DIR__ . '/lib'));
        $filter->includeFiles($this->filenameList(__DIR__ . '/public'));
        $filter->includeFiles($this->filenameList(__DIR__ . '/sections'));
        $this->coverage = new CodeCoverage(
            new Selector()->forLineCoverage($filter),
            $filter
        );
        $this->coverage->cacheStaticAnalysis('/tmp/coverage-cache');
        $this->coverage->start('fpm-coverage');
    }

    /**
     * @return list<string>
     */
    protected function filenameList(string $path): array {
        $list = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $entry) {
            $filename = $entry->getPathname();
            if (str_ends_with($filename, '.php')) {
                $list[] = $filename;
            }
        }
        return $list;
    }

    public function __destruct() {
        $this->coverage->stop();
        $outfile = tempnam($this::TARGET_DIR, 'phpcov');
        if ($outfile !== false) {
            (new Serializer())->serialize($outfile, $this->coverage);
            rename($outfile, "{$outfile}.cov");
        }
    }
}

$coverage_helper = new CoverageHelper();
require __DIR__ . '/gazelle.php';
