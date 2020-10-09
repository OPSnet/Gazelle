<?php

namespace Gazelle\Manager;

class TorrentLabel {
    protected $info;
    protected $showMedia;
    protected $showEdition;
    protected $showFlags;
    protected $groupName;
    protected $separator;

    public function __construct() {
        $this->info = null;
        $this->showMedia = false;
        $this->showEdition = false;
        $this->showFlags = true;
        $this->groupName = null;
        $this->separator = ' / ';
    }

    /**
     * Load the information of a torrent
     *
     * @param array $info The torrent array from a Gazelle\Manager\Torrent object
     */
    public function load(array $info) {
        $this->info = $info;
        return $this;
    }

    /**
     * By default, the label does not contain the media (CD, WEB, ...).
     *
     * @param bool $flag If true, the label will contain the media
     */
    public function showMedia(bool $flag) {
        $this->showMedia = $flag;
        return $this;
    }

    /**
     * By default, the label does not contain the remastering information
     *
     * @param bool $flag If true, the label will contain remastering year and title.
     */
    public function showEdition(bool $flag) {
        $this->showEdition = $flag;
        return $this;
    }

    /**
     * By default, the label contains flags (Reported, Lossy Master Approved, ....)
     *
     * @param bool $flag If false, the flags are excluded
     */
    public function showFlags(bool $flag) {
        $this->showFlags = $flag;
        return $this;
    }

    protected function element($class, $text): string {
        return sprintf('<strong class="torrent_label tooltip %s" title="%s" style="white-space: nowrap;">%s</strong>',
            $class, $text, $text
        );
    }

    /**
     * Generate the release [Format/Encoding/Media] of the torrent
     * @return string
     */
    public function release(): string {
        $release = [$this->info['Format'], $this->info['Encoding']];
        if ($this->info['Media']) {
            $release[] = $this->info['Media'];
        }
        return implode('/', $release);
    }

    /**
     * Generate the edition of the torrent
     * @return string
     */
    public function edition(): string {
        if (!$this->showEdition) {
            return '';
        }
        $edition = [];
        $year = $this->info['RemasterYear'] ?? $this->info['Year'] ?? null;
        if ($year) {
            $edition[] = $year;
        }
        $title = $this->info['RemasterTitle'] ?? null;
        if ($title) {
            $edition[] = $title;
        }
        $recordLabel = $this->info['RemasterRecordLabel'] ?? $this->info['RecordLabel'] ?? null;
        if ($recordLabel) {
            $edition[] = $recordLabel;
        }
        $recordCatalogue = $this->info['RemasterRecordCatalogue'] ?? $this->info['RecordCatalogue'] ?? null;
        if ($recordCatalogue) {
            $edition[] = $recordCatalogue;
        }
        return implode('/', $edition);
    }

    /**
     * Generate the HTML label of the torrent
     * @return string
     */
    public function label(): string {
        $label = [];
        if (isset($this->info['Format'])) {
            $label[] = $this->info['Format'];
        }
        if (isset($this->info['Encoding'])) {
            $label[] = $this->info['Encoding'];
        }
        if (isset($this->info['Media']) && $this->info['Media'] === 'CD') {
            if (isset($this->info['HasLog'])) {
                $label[] = 'Log' . (($this->info['HasLogDB'] ?? false) ? " ({$this->info['LogScore']}%)" : '');
            }
            if (isset($this->info['HasCue']) && $this->info['HasCue']) {
                $label[] = 'Cue';
            }
        }
        if ($this->showMedia && isset($this->info['Media'])) {
            $label[] = $this->info['Media'];
        }
        if (isset($this->info['Scene']) && $this->info['Scene']) {
            $label[] = 'Scene';
        }
        if (!count($label) && $this->groupName) {
            $label[] = $this->groupName;
        }

        if (isset($this->info['IsSnatched']) && $this->info['IsSnatched']) {
            $label[] = $this->element('tl_snatched', 'Snatched!');
        }
        if (isset($this->info['FreeTorrent'])) {
            if ($this->info['FreeTorrent'] == '1') {
                $label[] = $this->element('tl_free', 'Freeleech!');
            } elseif ($this->info['FreeTorrent'] == '2') {
                $label[] = $this->element('tl_free tl_neutral', 'Neutral Leech!');
            }
        }
        if (isset($this->info['PersonalFL']) && $this->info['PersonalFL']) {
            $label[] = $this->element('tl_free tl_personal', 'Personal Freeleech!');
        }
        if (isset($this->info['Reported']) && $this->info['Reported']) {
            $label[] = $this->element('tl_reported', 'Reported');
        }

        if ($this->showFlags) {
            if ($this->info['HasLog'] && $this->info['HasLogDB'] && $this->info['LogChecksum'] !== '1') {
                $label[] = $this->element('tl_notice', 'Bad/Missing Checksum');
            }
            if ($this->info['BadTags']) {
                $label[] = $this->element('tl_reported tl_bad_tags', 'Bad Tags');
            }
            if (!empty($this->info['BadFolders'])) {
                $label[] = $this->element('tl_reported tl_bad_folders', 'Bad Folders');
            }
            if (!empty($this->info['BadFiles'])) {
                $label[] = $this->element('tl_reported tl_bad_filenames', 'Bad File Names');
            }
        }

        if (!empty($this->info['MissingLineage'])) {
            $label[] = $this->element('tl_reported tl_missing_lineage', 'Missing Lineage');
        }
        if (!empty($this->info['CassetteApproved'])) {
            $label[] = $this->element('tl_approved tl_cassette', 'Cassette Approved');
        }
        if (!empty($this->info['LossymasterApproved'])) {
            $label[] = $this->element('tl_approved tl_lossy_master', 'Lossy Master Approved');
        }
        if (!empty($this->info['LossywebApproved'])) {
            $label[] = $this->element('tl_approved tl_lossy_web', 'Lossy WEB Approved');
        }
        return implode($this->separator, $label);
    }
}
