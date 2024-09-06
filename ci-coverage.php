<?php
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

require_once(__DIR__ . '/vendor/autoload.php');

use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\PHP as PhpReport;
use SebastianBergmann\CodeCoverage\Util\Filesystem;

function filenameList(string $path): array { /** @phpstan-ignore-line */
    $list = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $entry) {
        $filename = $entry->getPathname();
        if (str_ends_with($filename, '.php')) {
            $list[] = $filename;
        }
    }
    return $list;
}

class CoverageHelper {
    /*
     * ensure coverage is saved if die() is called somewhere
     */

    protected const TARGET_DIR = '/tmp/coverage';
    private CodeCoverage $coverage;

    public function __construct() {
        $filter = new Filter();
        $filter->includeFiles(filenameList(__DIR__ . '/app'));
        $filter->includeFiles(filenameList(__DIR__ . '/classes'));
        $filter->includeFiles(filenameList(__DIR__ . '/lib'));
        $filter->includeFiles(filenameList(__DIR__ . '/public'));
        $filter->includeFiles(filenameList(__DIR__ . '/sections'));
        $this->coverage = new CodeCoverage(
            (new Selector())->forLineCoverage($filter),
            $filter
        );
        $this->coverage->cacheStaticAnalysis('/tmp/coverage-cache');
        $this->coverage->start('fpm-coverage');
    }

    public function __destruct() {
        $this->coverage->stop();
        Filesystem::createDirectory($this::TARGET_DIR);
        $outfile = tempnam($this::TARGET_DIR, 'phpcov');
        if ($outfile !== false) {
            (new PhpReport())->process($this->coverage, $outfile);
            rename($outfile, $outfile . ".cov");
        }
    }
}

$coverage_helper = new CoverageHelper();
require_once(__DIR__ . '/gazelle.php');
