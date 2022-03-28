<?php

namespace Gazelle\Util;

class FileChecker {

    protected $comicsExtensions = [
        'cbr', 'cbz', 'gif', 'jpeg', 'jpg', 'pdf', 'png'
    ];

    protected $musicExtensions = [
        'ac3', 'accurip', 'azw3', 'chm', 'cue', 'djv', 'djvu', 'doc', 'docx', 'dts',
        'epub', 'ffp', 'flac', 'gif', 'htm', 'html', 'jpeg', 'jpg', 'json', 'lit', 'lrc',
        'log', 'm3u', 'm3u8', 'm4a', 'm4b', 'md5', 'mobi', 'mp3', 'mp4', 'nfo', 'pdf',
        'pls', 'png', 'rtf', 'sfv', 'txt', 'toc', 'yaml', 'yml'
    ];

    protected $badExtensions = [
       'torrent'
    ];

    protected $keywords = [
        'ahashare.com', 'demonoid.com', 'demonoid.me', 'djtunes.com', 'h33t', 'housexclusive.net',
        'limetorrents.com', 'mixesdb.com', 'mixfiend.blogstop', 'mixtapetorrent.blogspot',
        'plixid.com', 'reggaeme.com' , 'scc.nfo', 'thepiratebay.org', 'torrentday'
    ];

    public function checkFile(string $type, string $name): ?string {
        $err = $this->checkName($name);
        if (!is_null($err)) {
            return $err;
        }
        $err = $this->checkExtensions($type, $name);
        if (!is_null($err)) {
            return $err;
        }
        return null;
    }

    public function checkName(string $name): ?string {
        if (preg_match('/INCOMPLETE~/i', $name)) {
            return $this->forbiddenError($name);
        }
        $nameLC = strtolower($name);
        foreach ($this->keywords as $value) {
            if (str_contains($nameLC, $value)) {
                return $this->forbiddenError($name);
            }
        }

        /*
         * These characters are invalid in NTFS on Windows systems:
         *        : ? / < > \ * | "
         *
         * TODO: Add "/" to the blacklist. Adding "/" to the blacklist causes problems with nested dirs, apparently.
         */
        if (preg_match('/[\\:?<>*|"]/', $name, $m)) {
            return $this->characterError($m[0], ' : ? < > \ * | " ');
        }
        return null;
    }

    public function checkExtensions(string $type, string $name): ?string {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($type, ['Music', 'Audiobooks', 'Comedy', 'E-Books']) && !in_array($ext, $this->musicExtensions)) {
            return $this->invalidError($name);
        } elseif ($type == 'Comics' && !in_array($ext, $this->comicsExtensions)) {
            return $this->invalidError($name);
        } elseif (in_array($ext, $this->badExtensions)) {
            return $this->forbiddenError($name);
        }
        return null;
    }

    protected function invalidError(string $name): string {
        return 'The torrent contained one or more invalid files (' . display_str($name) . ')';
    }

    protected function forbiddenError(string $name): string {
        return 'The torrent contained one or more forbidden files (' . display_str($name) . ')';
    }

    protected function characterError(string $character, string $allBlockedChars): string {
        return "One or more of the files or folders in the torrent has a name that contains the forbidden character '$character'. Please rename the files as necessary and recreate the torrent.<br /><br />\nNote: The complete list of characters that are disallowed are shown below:<br />\n\t\t$allBlockedChars";
    }
}
