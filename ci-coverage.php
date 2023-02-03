<?php
require_once(__DIR__ . '/vendor/autoload.php');

use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\PHP as PhpReport;
use SebastianBergmann\CodeCoverage\Util\Filesystem;


class CoverageHelper {
    /*
     * ensure coverage is saved if die() is called somewhere
     */

    const TARGET_DIR = '/tmp/coverage';
    private CodeCoverage $coverage;

    function __construct() {
        $filter = new Filter;
        $filter->includeDirectory(__DIR__ . '/app');
        $filter->includeDirectory(__DIR__ . '/classes');
        $filter->includeDirectory(__DIR__ . '/lib');
        $filter->includeDirectory(__DIR__ . '/public');
        $filter->includeDirectory(__DIR__ . '/sections');
        $this->coverage = new CodeCoverage(
            (new Selector)->forLineCoverage($filter),
            $filter
        );
        $this->coverage->cacheStaticAnalysis('/tmp/coverage-cache');
        $this->coverage->start('fpm-coverage');
    }

    function __destruct() {
        $this->coverage->stop();
        Filesystem::createDirectory($this::TARGET_DIR);
        $outfile = tempnam($this::TARGET_DIR, 'phpcov');
        (new PhpReport)->process($this->coverage, $outfile);
        rename($outfile, $outfile . ".cov");
    }
}

$coverage_helper = new CoverageHelper();
require_once(__DIR__ . '/gazelle.php');
