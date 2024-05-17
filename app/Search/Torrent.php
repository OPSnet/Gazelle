<?php

namespace Gazelle\Search;

use Gazelle\Enum\LeechType;

class Torrent {
    final protected const TAGS_ANY = 0;
    final protected const TAGS_ALL = 1;
    final protected const SPH_BOOL_AND = ' ';
    final protected const SPH_BOOL_OR = ' | ';

    /**
     * Map of sort mode => attribute name for ungrouped torrent page
     */
    public static array $SortOrders = [
        'year' => 'year',
        'time' => 'id',
        'size' => 'size',
        'seeders' => 'seeders',
        'leechers' => 'leechers',
        'snatched' => 'snatched',
        'random' => 1];

    /**
     * Map of sort mode => attribute name for grouped torrent page
     */
    private static array $SortOrdersGrouped = [
        'year' => 'year',
        'time' => 'id',
        'size' => 'maxsize',
        'seeders' => 'sumseeders',
        'leechers' => 'sumleechers',
        'snatched' => 'sumsnatched',
        'random' => 1];

    /**
     * Map of sort mode => aggregate expression required for some grouped sort orders
     */
    private static array $AggregateExp = [
        'size' => 'MAX(size) AS maxsize',
        'seeders' => 'SUM(seeders) AS sumseeders',
        'leechers' => 'SUM(leechers) AS sumleechers',
        'snatched' => 'SUM(snatched) AS sumsnatched'];

    /**
     * Map of attribute name => global variable name with list of values that can be used for filtering
     */
    private static array $Attributes = [
        'filter_cat' => false,
        'releasetype' => 'ReleaseTypes',
        'freetorrent' => false,
        'hascue' => false,
        'haslog' => false,
        'scene' => false,
        'vanityhouse' => false,
        'year' => false];

    /**
     * List of fields that can be used for fulltext searches
     */
    private static array $Fields = [
        'artistname' => 1,
        'cataloguenumber' => 1,
        'description' => 1,
        'encoding' => 1,
        'filelist' => 1,
        'format' => 1,
        'groupname' => 1,
        'media' => 1,
        'recordlabel' => 1,
        'remastercataloguenumber' => 1,
        'remasterrecordlabel' => 1,
        'remastertitle' => 1,
        'remasteryear' => 1,
        'searchstr' => 1,
        'taglist' => 1];

    /**
     * List of torrent-specific fields that can be used for filtering
     */
    private static array $TorrentFields = [
        'description' => 1,
        'encoding' => 1,
        'filelist' => 1,
        'format' => 1,
        'media' => 1,
        'remastercataloguenumber' => 1,
        'remasterrecordlabel' => 1,
        'remastertitle' => 1,
        'remasteryear' => 1];

    /**
     * Some form field names don't match the ones in the index
     */
    private static array $FormsToFields = [
        'searchstr' => '(groupname,artistname,yearfulltext)'];

    /**
     * Specify the operator type to use for fields. Empty key sets the default
     */
    private static array $FieldOperators = [
        '' => self::SPH_BOOL_AND,
        'encoding' => self::SPH_BOOL_OR,
        'format' => self::SPH_BOOL_OR,
        'media' => self::SPH_BOOL_OR];

    /**
     * Specify the separator character to use for fields. Empty key sets the default
     */
    private static array $FieldSeparators = [
        '' => ' ',
        'encoding' => '|',
        'format' => '|',
        'media' => '|',
        'taglist' => ','];

    /**
     * Primary SphinxqlQuery object used to get group IDs or torrent IDs for ungrouped searches
     */
    private readonly \SphinxqlQuery $SphQL;

    /**
     * Second SphinxqlQuery object used to get torrent IDs if torrent-specific fulltext filters are used
     */
    private ?\SphinxqlQuery $SphQLTor = null;

    /**
     * Ordered result array or false if query resulted in an error
     */
    private array|false $SphResults = false;

    private int $NumResults = 0;
    private array $Groups = [];

    /**
     * True if the NOT operator can be used. Sphinx needs at least one positive search condition
     */
    private bool $EnableNegation = false;

    /**
     * Whether any filters were used
     */
    private bool $Filtered = false;

    /**
     * Whether the random sort order is selected
     */
    private bool $Random = false;

    /**
     * Storage for fulltext search terms
     * ['Field name' => [
     *     'include' => [],
     *     'exclude' => [],
     *     'operator' => self::SPH_BOOL_AND | self::SPH_BOOL_OR
     * ]], ...
     */
    private array $Terms = [];

    /**
     * Unprocessed search terms for retrieval
     */
    private array $RawTerms = [];

    /**
     * Storage for used torrent-specific attribute filters
     * ['Field name' => 'Search expression', ...]
     */
    private array $UsedTorrentAttrs = [];

    /**
     * Storage for used torrent-specific fulltext fields
     * ['Field name' => 'Search expression', ...]
     */
    private array $UsedTorrentFields = [];

    public function __construct(
        protected \Gazelle\Manager\TGroup $tgMan,
        protected \Gazelle\Manager\Torrent $torMan,
        protected readonly bool $GroupResults,
        protected readonly string $OrderBy,
        protected readonly string $OrderWay,
        protected int $Page,
        protected int $PageSize,
        protected readonly bool $searchMany,
    ) {
        if (
            $this->GroupResults && !isset(self::$SortOrdersGrouped[$OrderBy])
                || !$this->GroupResults && !isset(self::$SortOrders[$OrderBy])
                || !in_array($OrderWay, ['asc', 'desc'])
        ) {
            $ErrMsg = "Search\Torrent constructor arguments:\n" . print_r(func_get_args(), true);
            global $Debug;
            $Debug->analysis('Bad arguments in Search\Torrent constructor', $ErrMsg, 3600 * 24);
            error('-1');
        }
        $this->Page = $searchMany ? $Page : min($Page, SPHINX_MAX_MATCHES / $PageSize);

        $ResultLimit = $PageSize;
        $this->SphQL = new \SphinxqlQuery();
        if ($OrderBy === 'random') {
            $this->SphQL->select('id, groupid')
                ->order_by('RAND()', '');
            $this->Random = true;
            $this->Page = 1;
            if ($GroupResults) {
                // Get more results because ORDER BY RAND() can't be used in GROUP BY queries
                $ResultLimit *= 5;
            }
        } elseif ($this->GroupResults) {
            $Select = 'groupid';
            if (isset(self::$AggregateExp[$OrderBy])) {
                $Select .= ', ' . self::$AggregateExp[$OrderBy];
            }
            $this->SphQL->select($Select)
                ->group_by('groupid')
                ->order_group_by(self::$SortOrdersGrouped[$OrderBy], $OrderWay)
                ->order_by(self::$SortOrdersGrouped[$OrderBy], $OrderWay);
        } else {
            $this->SphQL->select('id, groupid')
                ->order_by(self::$SortOrders[$OrderBy], $OrderWay);
        }
        $Offset = ($this->Page - 1) * $ResultLimit;
        $MaxMatches = $Offset + $ResultLimit;
        $this->SphQL->from('torrents, delta')
            ->limit($Offset, $ResultLimit, $MaxMatches);
    }

    /**
     * Process search terms and run the main query
     *
     */
    public function query(array $Terms = []): array|false {
        $this->process_search_terms($Terms);
        $this->build_query();
        $this->run_query();
        $this->process_results();
        return $this->SphResults;
    }

    /**
     * Internal function that runs the queries needed to get the desired results
     */
    private function run_query(): void {
        $SphQLResult = $this->SphQL->sphinxquery();
        if ($SphQLResult === false) {
            return;
        }
        $result = $SphQLResult; /* to keep phpstan happy */
        if ($result->Errno > 0) {
            return;
        }
        if ($this->Random && $this->GroupResults) {
            $TotalCount = $result->get_meta('total_found');
            $this->SphResults = $result->collect('groupid');
            $GroupIDs = array_keys($this->SphResults);
            $GroupCount = count($GroupIDs);
            while ($result->get_meta('total') < $TotalCount && $GroupCount < $this->PageSize) {
                // Make sure we get $PageSize results, or all of them if there are less than $PageSize hits
                $this->SphQL->where('groupid', $GroupIDs, true);
                $SphQLResult = $this->SphQL->sphinxquery();
                if (!$result->has_results()) {
                    break;
                }
                $this->SphResults += $result->collect('groupid');
                $GroupIDs = array_keys($this->SphResults);
                $GroupCount = count($GroupIDs);
            }
            if ($GroupCount > $this->PageSize) {
                $this->SphResults = array_slice($this->SphResults, 0, $this->PageSize, true);
            }
            $this->NumResults = count($this->SphResults);
        } else {
            $this->NumResults = (int)$result->get_meta('total_found');
            if ($this->GroupResults) {
                $this->SphResults = $result->collect('groupid');
            } else {
                $this->SphResults = $result->to_pair('id', 'groupid');
            }
        }
    }

    /**
     * Process search terms and store the parts in appropriate arrays until we know if
     * the NOT operator can be used
     */
    private function build_query(): void {
        foreach ($this->Terms as $Field => $Words) {
            $SearchString = '';
            if (isset(self::$FormsToFields[$Field])) {
                $Field = self::$FormsToFields[$Field];
            }
            $QueryParts = ['include' => [], 'exclude' => []];
            if (!$this->EnableNegation && !empty($Words['exclude'])) {
                $Words['include'] = $Words['exclude'];
                unset($Words['exclude']);
            }
            $totalWords = 0;
            if (!empty($Words['include'])) {
                foreach ($Words['include'] as $Word) {
                    $QueryParts['include'][] = \Sphinxql::sph_escape_string($Word);
                    ++$totalWords;
                }
            }
            if (!empty($Words['exclude'])) {
                foreach ($Words['exclude'] as $Word) {
                    $QueryParts['exclude'][] = '!' . \Sphinxql::sph_escape_string(substr($Word, 1));
                    ++$totalWords;
                }
            }
            if ($totalWords) {
                if (isset($Words['operator'])) {
                    // Is the operator already specified?
                    $Operator = $Words['operator'];
                } elseif (isset(self::$FieldOperators[$Field])) {
                    // Does this field have a non-standard operator?
                    $Operator = self::$FieldOperators[$Field];
                } else {
                    // Go for the default operator
                    $Operator = self::$FieldOperators[''];
                }
                if (!empty($QueryParts['include'])) {
                    $SearchString .= '( ' . implode($Operator, $QueryParts['include']) . ' ) ';
                }
                if (!empty($QueryParts['exclude'])) {
                     $SearchString .= implode(' ', $QueryParts['exclude']);
                }
                $this->SphQL->where_match($SearchString, $Field, false);
                if (isset(self::$TorrentFields[$Field])) {
                    $this->UsedTorrentFields[$Field] = $SearchString;
                }
                $this->Filtered = true;
            }
        }
    }

    /**
     * Look at each search term and figure out what to do with it
     *
     * $Terms Array with search terms from query()
     */
    private function process_search_terms(array $Terms): void {
        foreach ($Terms as $Key => $Term) {
            if (isset(self::$Fields[$Key])) {
                $this->process_field($Key, $Term);
            } elseif (isset(self::$Attributes[$Key])) {
                $this->process_attribute($Key, $Term);
            }
            $this->RawTerms[$Key] = $Term;
        }
        $this->post_process_fields();
    }

    /**
     * Process attribute filters and store them in case we need to post-process grouped results
     *
     * $Attribute Name of the attribute to filter against
     * $Value The filter's condition for a match
     */
    private function process_attribute(string $Attribute, array|int|string $Value): void {
        if ($Value === '') {
            return;
        }
        switch ($Attribute) {
            case 'year':
                if (!is_string($Value)) {
                    return;
                }
                $this->search_year($Value);
                break;

            case 'haslog':
                if (is_array($Value)) {
                    return;
                }
                if ($Value == 0) {
                    $this->SphQL->where('haslog', 0);
                } elseif ($Value == 99) {
                    $this->SphQL->where('logscore', 99);
                } elseif ($Value == 100) {
                    $this->SphQL->where('logscore', 100);
                } elseif ($Value < 0) {
                    $this->SphQL->where_lt('logscore', 100);
                    $this->SphQL->where('haslog', 1);
                } else {
                    $this->SphQL->where('haslog', 1);
                }
                $this->UsedTorrentAttrs['haslog'] = $Value;
                break;

            case 'freetorrent':
                if (!is_numeric($Value)) {
                    return;
                }
                $Value = (int)$Value;
                if ($Value == 3) {
                    $this->SphQL->where('freetorrent', 0, true);
                    $this->UsedTorrentAttrs['freetorrent'] = 3;
                } elseif ($Value >= 0 && $Value < 3) {
                    $this->SphQL->where('freetorrent', $Value);
                    $this->UsedTorrentAttrs[$Attribute] = $Value;
                } else {
                    return;
                }
                break;

            case 'filter_cat':
                if (is_string($Value)) {
                    $Value = array_fill_keys(explode('|', $Value), 1);
                }
                $CategoryFilter = [];
                if (is_array($Value)) {
                    foreach (array_keys($Value) as $Category) {
                        if (is_number($Category)) {
                            $CategoryFilter[] = $Category;
                        } else {
                            $ValidValues = array_map('strtolower', CATEGORY);
                            if (($CategoryID = array_search(strtolower($Category), $ValidValues)) !== false) {
                                $CategoryFilter[] = $CategoryID + 1;
                            }
                        }
                    }
                }
                if (empty($CategoryFilter)) {
                    $CategoryFilter = 0;
                }
                $this->SphQL->where('categoryid', $CategoryFilter);
                break;

            case 'releasetype':
                $id = (int)$Value;
                if (!is_null((new \Gazelle\ReleaseType())->findNameById($id))) {
                    $this->SphQL->where('ReleaseType', $id);
                }
                break;

            default:
                if (is_string($Value) && self::$Attributes[$Attribute] !== false) {
                    // Check if the submitted value can be converted to a valid one
                    $ValidValuesVarname = self::$Attributes[$Attribute];
                    // This code is incomprehensible, I would like to kill the original dev
                    global ${$ValidValuesVarname};
                    $ValidValues = array_map('strtolower', ${$ValidValuesVarname});
                    if (($Value = array_search(strtolower($Value), $ValidValues)) === false) {
                        // Force the query to return 0 results if value is still invalid
                        $Value = max(array_keys($ValidValues)) + 1;
                    }
                }
                $this->SphQL->where($Attribute, $Value);
                $this->UsedTorrentAttrs[$Attribute] = $Value;
                break;
        }
        $this->Filtered = true;
    }

    /**
     * Look at a fulltext search term and figure out if it needs special treatment
     *
     * $Field Name of the search field
     * $Term Search expression for the field
     */
    private function process_field(string $Field, string $Term): void {
        $Term = trim($Term);
        if ($Term === '') {
            return;
        }
        if ($Field === 'searchstr') {
            $this->search_basic($Term);
        } elseif ($Field === 'filelist') {
            $this->search_filelist($Term);
        } elseif ($Field === 'taglist') {
            $this->search_taglist($Term);
        } else {
            $this->add_field($Field, $Term);
        }
    }

    /**
     * Some fields may require post-processing
     */
    private function post_process_fields(): void {
        if (isset($this->Terms['taglist'])) {
            // Replace bad tags with tag aliases
            $this->Terms['taglist'] = (new \Gazelle\Manager\Tag())->replaceAliasList($this->Terms['taglist']);
            if (isset($this->RawTerms['tags_type']) && (int)$this->RawTerms['tags_type'] === self::TAGS_ANY) {
                $this->Terms['taglist']['operator'] = self::SPH_BOOL_OR;
            }
            // Update the RawTerms array so get_terms() can return the corrected search terms
            if (isset($this->Terms['taglist']['include'])) {
                $AllTags = $this->Terms['taglist']['include'];
            } else {
                $AllTags = [];
            }
            if (isset($this->Terms['taglist']['exclude'])) {
                $AllTags = array_merge($AllTags, $this->Terms['taglist']['exclude']);
            }
            $this->RawTerms['taglist'] = str_replace('_', '.', implode(', ', $AllTags));
        }
    }

    /**
     * Handle magic keywords in the basic torrent search
     *
     * @param string $Term Given search expression
     */
    private function search_basic($Term): void {
        $SearchBitrates = array_map('strtolower', array_merge(ENCODING, ['v0', 'v1', 'v2', '24bit']));
        $SearchFormats = array_map('strtolower', FORMAT);
        $SearchMedia = array_map('strtolower', MEDIA);

        foreach (explode(' ', $Term) as $Word) {
            if (in_array($Word, $SearchBitrates)) {
                $this->add_word('encoding', $Word);
            } elseif (in_array($Word, $SearchFormats)) {
                $this->add_word('format', $Word);
            } elseif (in_array($Word, $SearchMedia)) {
                $this->add_word('media', $Word);
            } elseif ($Word === '100%') {
                $this->process_attribute('haslog', 100);
            } elseif ($Word === '!100%') {
                $this->process_attribute('haslog', -1);
            } else {
                $this->add_word('searchstr', $Word);
            }
        }
    }

    /**
     * Use phrase boundary for file searches to make sure we don't count
     * partial hits from multiple files
     *
     * $Term Given search expression
     */
    private function search_filelist(string $Term): void {
        $SearchString = '"' . \Sphinxql::sph_escape_string($Term) . '"~20';
        $this->SphQL->where_match($SearchString, 'filelist', false);
        $this->UsedTorrentFields['filelist'] = $SearchString;
        $this->EnableNegation = true;
        $this->Filtered = true;
    }

    /**
     * Prepare tag searches before sending them to the normal treatment
     *
     * $Term Given search expression
     */
    private function search_taglist(string $Term): void {
        $this->add_field('taglist', strtr($Term, '.', '_'));
    }

    /**
     * The year filter accepts a range. Figure out how to handle the filter value
     *
     * $Term Filter condition. Can be an integer or a range with the format X-Y
     * return True if parameters are valid
     */
    private function search_year(string $Term): bool {
        $Years = explode('-', $Term);
        if (count($Years) === 1 && is_number($Years[0])) {
            // Exact year
            $this->SphQL->where('year', $Years[0]);
        } elseif (count($Years) === 2) {
            if (empty($Years[0]) && is_number($Years[1])) {
                // Range: 0 - 2005
                $this->SphQL->where_lt('year', $Years[1], true);
            } elseif (empty($Years[1]) && is_number($Years[0])) {
                // Range: 2005 - 2^32-1
                $this->SphQL->where_gt('year', $Years[0], true);
            } elseif (is_number($Years[0]) && is_number($Years[1])) {
                // Range: 2005 - 2009
                $this->SphQL->where_between('year', [min($Years), max($Years)]);
            } else {
                // Invalid input
                return false;
            }
        } else {
            // Invalid input
            return false;
        }
        return true;
    }

    /**
     * Add a field filter that doesn't need special treatment
     *
     * $Field Name of the search field
     * $Term Search expression for the field
     */
    private function add_field(string $Field, string $Term): void {
        if (isset(self::$FieldSeparators[$Field])) {
            $Separator = self::$FieldSeparators[$Field];
        } else {
            $Separator = self::$FieldSeparators[''];
        }
        $Words = explode($Separator, $Term);
        foreach ($Words as $Word) {
            $this->add_word($Field, $Word);
        }
    }

    /**
     * Add a keyword to the array of search terms
     *
     * $Field Name of the search field
     * $Word Keyword
     */
    private function add_word(string $Field, string $Word): void {
        $Word = trim($Word);
        // Skip isolated hyphens to enable "Artist - Title" searches
        if ($Word === '' || $Word === '-') {
            return;
        }
        if ($Word[0] === '!' && strlen($Word) >= 2 && !str_contains(substr($Word, 1), '!')) {
            $this->Terms[$Field]['exclude'][] = $Word;
        } else {
            $this->Terms[$Field]['include'][] = $Word;
            $this->EnableNegation = true;
        }
    }

    /**
     * Torrent group information for the matches
     */
    public function get_groups(): array {
        return $this->Groups;
    }

    /**
     * param string $Type Field or attribute name
     * return string Unprocessed search terms
     */
    public function get_terms(string $Type): string {
        return $this->RawTerms[$Type] ?? '';
    }

    public function record_count(): int {
        return $this->NumResults;
    }

    public function has_filters(): bool {
        return $this->Filtered;
    }

    /**
     * Were any torrent-specific fulltext filters were used
     */
    public function need_torrent_ft(): bool {
        return $this->GroupResults && $this->NumResults > 0 && !empty($this->UsedTorrentFields);
    }

    /**
     * Get torrent group info and remove any torrents that don't match
     */
    private function process_results(): void {
        if (!is_array($this->SphResults) || !count($this->SphResults)) {
            return;
        }
        $this->Groups = array_map(fn($id) => $this->tgMan->findById($id), $this->SphResults);
        if ($this->need_torrent_ft()) {
            // Query Sphinx for torrent IDs if torrent-specific fulltext filters were used
            $this->filter_torrents_sph();
        } elseif ($this->GroupResults) {
            // Otherwise, let PHP discard unmatching torrents
            $this->filter_torrents_internal();
        }
        // Ungrouped searches don't need any additional filtering
    }

    /**
     * Build and run a query that gets torrent IDs from Sphinx when fulltext filters
     * were used to get primary results and they are grouped
     */
    private function filter_torrents_sph(): void {
        $AllTorrents = [];
        foreach ($this->Groups as $tgroup) {
            if (is_null($tgroup)) {
                continue;
            }
            $AllTorrents[] = $tgroup;
        }
        $TorrentCount = count($AllTorrents);
        $this->SphQLTor = new \SphinxqlQuery();
        $this->SphQLTor->select('id')->from('torrents, delta');
        foreach ($this->UsedTorrentFields as $Field => $Term) {
            $this->SphQLTor->where_match($Term, $Field, false);
        }
        $this->SphQLTor->copy_attributes_from($this->SphQL);
        $this->SphQLTor->where('id', array_keys($AllTorrents))->limit(0, $TorrentCount, $TorrentCount);
        $SphQLResultTor = $this->SphQLTor->sphinxquery();
        if ($SphQLResultTor !== false) {
            $MatchingTorrentIDs = $SphQLResultTor->to_pair('id', 'id');
            foreach ($AllTorrents as $tgroup) {
                if (!isset($MatchingTorrentIDs[$tgroup->id()])) {
                    unset($this->Groups[$tgroup->id()]);
                }
            }
        }
    }

    /**
     * Non-Sphinx method of collecting IDs of torrents that match any
     * torrent-specific attribute filters that were used in the search query
     */
    private function filter_torrents_internal(): void {
        foreach ($this->Groups as $tgroup) {
            if (is_null($tgroup)) {
                continue;
            }
            $torrentList = array_map(fn($id) => $this->torMan->findById($id), $tgroup->torrentIdList());
            foreach ($torrentList as $torrent) {
                if (is_null($torrent) || !$this->filter_torrent_internal($torrent)) {
                    unset($this->Groups[$tgroup->id()]);
                    break;
                }
            }
        }
    }

    /**
     * Post-processing to determine if a torrent is a real hit or if it was
     * returned because another torrent in the group matched. Only used if
     * there are no torrent-specific fulltext conditions
     *
     * param array $Torrent list of TGroup objects
     * return bool True if it's a real hit
     */
    private function filter_torrent_internal(\Gazelle\Torrent $torrent): bool {
        if (isset($this->UsedTorrentAttrs['freetorrent'])) {
            $FilterValue = $this->UsedTorrentAttrs['freetorrent'];
            if ($FilterValue == '3' && $torrent->leechType() != LeechType::Normal) {
                // Either FL or NL is ok
                return false;
            } elseif (!in_array($FilterValue, ['3', $torrent->leechType()->value])) {
                return false;
            }
        }
        if (isset($this->UsedTorrentAttrs['hascue'])) {
            if ($this->UsedTorrentAttrs['hascue'] != $torrent->hasCue()) {
                return false;
            }
        }
        if (isset($this->UsedTorrentAttrs['haslog'])) {
            $FilterValue = $this->UsedTorrentAttrs['haslog'];
            if ($FilterValue == '0') {
                // No logs
                $Pass = !$torrent->hasLog();
            } elseif ($FilterValue == '100') {
                // 100% logs
                $Pass = $torrent->logScore() == '100';
            } elseif ($FilterValue == '99') {
                // 99% logs
                $Pass = $torrent->logScore() == '99';
            } elseif ($FilterValue < 0) {
                // Unscored or <100% logs
                $Pass = $torrent->hasLog() && $torrent->logScore() != '100';
            } else {
                // Any log score
                $Pass = $torrent->hasLog();
            }
            if (!$Pass) {
                return false;
            }
        }
        if (isset($this->UsedTorrentAttrs['scene'])) {
            if ($this->UsedTorrentAttrs['scene'] != $torrent->isScene()) {
                return false;
            }
        }
        return true;
    }
}
