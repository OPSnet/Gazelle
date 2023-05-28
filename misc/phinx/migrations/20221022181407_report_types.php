<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

require_once(__DIR__ . '/../../../lib/config.php'); // for SITE_NAME

final class ReportTypes extends AbstractMigration {
    public function up(): void {
        $this->table('category', ['id' => false, 'primary_key'=> ['category_id'], 'encoding' => 'utf8mb4'])
            ->addColumn('category_id', 'integer', ['identity' => true])
            ->addColumn('is_system',    'boolean', ['default' => false])
            ->addColumn('is_grouped',   'boolean', ['default' => false])
            ->addColumn('upload',       'enum', ['default' => 'simple', 'values' => ['audiobook', 'simple', 'music']])
            ->addColumn('name',         'string', ['length' => 30])
            ->addIndex(['name'], ['unique' => true, 'name' => 'c_name_uidx'])
            ->create();

        $this->table('category')->insert([[
            'name'       => 'Music',
            'upload'     => 'music',
            'is_grouped' => true,
        ],[
            'name'       => 'Applications',
            'upload'     => 'simple',
        ],[
            'name'       => 'E-Books',
            'upload'     => 'simple',
        ],[
            'name'       => 'Audiobooks',
            'upload'     => 'audiobook',
        ],[
            'name'       => 'E-Learning Videos',
            'upload'     => 'simple',
        ],[
            'name'       => 'Comedy',
            'upload'     => 'audiobook',
        ],[
            'name'       => 'Comics',
            'upload'     => 'simple',
        ],[
            'name'       => 'Global',
            'upload'     => 'simple',
            'is_system'  => true,
        ]])->save();

        // hack: work around Mysql ignoring an explicit PK value
        $this->execute("update category set category_id = 0 where name = 'Global'");

        $this->table('torrent_report_configuration', ['id' => false, 'primary_key'=> ['torrent_report_configuration_id'], 'encoding' => 'utf8mb4'])
            ->addColumn('torrent_report_configuration_id', 'integer', ['identity' => true])
            ->addColumn('type',           'string', ['length' => 20])
            ->addColumn('name' ,          'string', ['length' => 30])
            ->addColumn('category_id',    'integer')
            ->addColumn('sequence',       'integer')
            ->addColumn('tracker_reason', 'integer', ['default' => -1])
            ->addColumn('is_active',      'boolean', ['default' => true])
            ->addColumn('is_admin',       'boolean', ['default' => false])
            ->addColumn('need_image',     'enum', ['default' => 'none', 'values' => ['none', 'optional', 'required', 'proof']])
            ->addColumn('need_link',      'enum', ['default' => 'none', 'values' => ['none', 'optional', 'required']])
            ->addColumn('need_sitelink',  'enum', ['default' => 'none', 'values' => ['none', 'optional', 'required']])
            ->addColumn('need_track',     'enum', ['default' => 'none', 'values' => ['none', 'optional', 'required', 'all']])
            ->addColumn('resolve_delete', 'boolean', ['default' => false])
            ->addColumn('resolve_upload', 'boolean', ['default' => false])
            ->addColumn('resolve_warn',   'integer', ['default' => 0])
            ->addColumn('resolve_log',    'string', ['length' => 80, 'null' => true])
            ->addColumn('explanation',    'text', ['limit' => MysqlAdapter::TEXT_MEDIUM])
            ->addColumn('pm_body',        'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => true])
            ->addForeignKey('category_id', 'category', 'category_id')
            ->addIndex(['type'], ['unique' => true, 'name' => 'trc_type_uidx'])
            ->create();

        $this->table('torrent_report_configuration')->insert([[
            'category_id'    => 0,
            'type'           => 'dupe',
            'name'           => 'Dupe',
            'sequence'       => 10,
            'tracker_reason' => 0,
            'need_sitelink'  => 'required',
            'resolve_delete' => true,
            'explanation'    => 'Please specify a link to the original torrent.',
            'pm_body'        => '[rule]h2.2[/rule]. Your torrent was reported because it was a duplicate of another torrent.',
         ],[
            'category_id'    => 0,
            'type'           => 'banned',
            'name'           => 'Specifically Banned',
            'sequence'       => 230,
            'tracker_reason' => 14,
            'resolve_delete' => true,
            'resolve_warn'   => 4,
            'explanation'    => 'Please specify exactly which entry on the Do Not Upload list this is violating.',
            'pm_body'        =>
                '[rule]h1.2[/rule]. You have uploaded material that is currently forbidden. Items on the Do Not Upload (DNU) list (at the top of the [url=upload.php]upload page[/url]) and in the [url=rules.php?p=upload#h1.2]Specifically Banned[/url] portion of the uploading rules cannot be uploaded to the site. Do not upload them unless your torrent meets a condition specified in the comments of the DNU list.

Your torrent was reported because it contained material from the DNU list or from the Specifically Banned section of the rules.',
        ],[
            'category_id'    => 0,
            'type'           => 'urgent',
            'name'           => 'Urgent',
            'sequence'       => 280,
            'need_image'     => 'optional',
            'need_link'      => 'optional',
            'need_sitelink'  => 'optional',
            'need_track'     => 'optional',
            'explanation'    => 'This report type is only for very urgent reports, usually for personal information being found within a torrent.

Abusing the "Urgent" report type could result in a warning or worse.

As this report type gives the staff absolutely no information about the problem, please be as clear as possible in your comments about what the problem is.',
        ],[
            'category_id'    => 0,
            'type'           => 'other',
            'name'           => 'Other',
            'sequence'       => 200,
            'need_image'     => 'optional',
            'need_link'      => 'optional',
            'need_sitelink'  => 'optional',
            'explanation'    => 'Please include as much information as possible to verify the report.',
        ],[
            'category_id'    => 0,
            'type'           => 'trump',
            'name'           => 'Trump',
            'sequence'       => 20,
            'tracker_reason' => 1,
            'need_sitelink'  => 'required',
            'resolve_delete' => true,
            'explanation'    => 'Please list the specific reason(s) the newer torrent trumps the older one.

Please make sure you are reporting the torrent [important]which has been trumped[/important] and should be deleted, not the torrent that you think should remain on site.',
            'pm_body'        => '[rule]h2.2[/rule]. Your torrent was reported because it was trumped by another torrent.',
        ],[
            'category_id'    => 1,
            'type'           => 'checksum_trump',
            'name'           => 'Checksum Trump',
            'sequence'       => 10,
            'tracker_reason' => 24,
            'need_sitelink'  => 'required',
            'resolve_delete' => true,
            'explanation'    => 'Please make certain that your checksum trump is valid (rules 2.2.10 and below). Only CD media rips are subject to checksum trumps.

Please make sure you are reporting the torrent [important]which has been trumped[/important] and should be deleted, not the torrent that you think should remain on site.',
            'pm_body'        =>
                '[rule]2.2.10.3[/rule]. A FLAC upload with an EAC, XLD, or whipper rip log with a valid checksum that scores 100% on the log checker replaces one with a lower score or bad or missing checksum. No log scoring less than 100% can trump an already existing one that scores under 100%.

Your torrent was reported because it was trumped by another torrent that was ripped with a log file that scored 100%.',
        ],[
            'category_id'    => 1,
            'type'           => 'tag_trump',
            'name'           => 'Tag Trump',
            'sequence'       => 50,
            'tracker_reason' => 4,
            'need_sitelink'  => 'required',
            'resolve_delete' => true,
            'explanation'    => 'Please list the specific tag(s) the newer torrent trumps the older one.

Please make sure you are reporting the torrent [important]which has been trumped[/important] and should be deleted, not the torrent that you think should remain on site.',
            'pm_body'        =>
                '[rule]2.3.16[/rule]. Properly tag your music files. Certain meta tags (e.g., ID3, Vorbis) are required on all music uploads. Make sure to use the appropriate tag format for your files (e.g., no ID3 tags for FLAC - see [rule]2.2.10.8[/rule]). ID3v2 tags for files are highly recommended over ID3v1. ID3 are recommended for AC3 torrents but are not mandatory because the format does not natively support file metadata tagging (for AC3, the file names become the vehicle for correctly labeling media files).

Torrents uploaded with both good ID3v1 tags and blank ID3v2 tags (a dual set of tags) are trumpable by torrents with either just good ID3v1 tags or good ID3v2 tags (a single set of tags). If you upload an album missing one or more of the required tags, then another user may add the tags, re-upload, and report your torrent for deletion.

Your torrent was reported because it was trumped by another torrent with improved metadata tags.',
        ],[
            'category_id'    => 1,
            'type'           => 'vinyl_trump',
            'name'           => 'Vinyl Trump',
            'sequence'       => 60,
            'tracker_reason' => 1,
            'need_sitelink'  => 'required',
            'resolve_delete' => true,
            'explanation'    => 'Please list the specific reason(s) the newer torrent trumps the older one.

[important]Please be as thorough as possible and include as much detail as you can. Refer to specific tracks and time positions to justify your report.[/important]

Please make sure you are reporting the torrent [important]that is being trumped[/important] and should be deleted, not the torrent that you think should remain on site.',
            'pm_body'        =>
                '[rule]2.5.5[/rule]. Vinyl rips may be trumped by better-sounding rips of the same bit depth, regardless of lineage information (see [rule]2.3.9[/rule]).

Your torrent was reported as it was trumped by a better-sounding vinyl rip.',
        ],[
            'category_id'    => 1,
            'type'           => 'folder_trump',
            'name'           => 'Bad Folder Name Trump',
            'sequence'       => 40,
            'tracker_reason' => 3,
            'need_sitelink'  => 'required',
            'resolve_delete' => true,
            'explanation'    => 'Please list the folder name and what is wrong with it.

Please make sure you are reporting the torrent [important]which has been trumped[/important] and should be deleted, not the torrent that you think should remain on site.',
            'pm_body'        =>
                '[rule]2.3.2[/rule]. Name your directories with meaningful titles, such as "Artist - Album (Year) - Format". The minimum acceptable is "Album" although it is preferable to include more information. If the directory name does not include this minimum then another user can rename the directory, re-upload, and report your torrent for deletion. In addition, torrent folders that are named using the scene convention will be trumpable if the Scene label is absent from the torrent.

[rule]2.3.3[/rule]. Avoid creating unnecessary nested folders (such as an extra folder for the actual album) inside your properly named directory. A torrent with unnecessary nested folders is trumpable by a torrent with such folders removed. For single disc albums, all audio files must be included in the main torrent folder. For multi-disc albums, the main torrent folder may include one sub-folder that holds the audio file contents for each disc in the box set, i.e., the main torrent folder is "Adele - 19 (2008) - FLAC" while appropriate sub-folders may include "19 (Disc 1of2)" or "19" and "Live From The Hotel Cafe (Disc 2of2)" or "Acoustic Set Live From The Hotel Cafe, Los Angeles." Additional folders are unnecessary because they do nothing to improve the organization of the torrent. If you are uncertain about what to do for other cases, PM a staff member for guidance.

Your torrent was reported because it was trumped by another torrent with an improved folder name and directory structure.',
        ],[
            'category_id'    => 1,
            'type'           => 'file_trump',
            'name'           => 'Bad File Names Trump',
            'sequence'       => 30,
            'tracker_reason' => 2,
            'need_sitelink'  => 'required',
            'resolve_delete' => true,
            'explanation'    => 'Please describe what is wrong with the file names.

Please make sure you are reporting the torrent [important]which has been trumped[/important] and should be deleted, not the torrent that you think should remain on site.',
            'pm_body'        =>
                '[rule]2.3.11[/rule]. File names must accurately reflect the song titles. You may not have file names like 01track.mp3, 02track.mp3, etc. Torrents containing files that are named with incorrect song titles can be trumped by properly labeled torrents. Also, torrents that are sourced from the scene but do not have the Scene label must comply with site naming rules (no release group names in the file names, no advertisements in the file names, etc.). If all the letters in the track titles are capitalized, the torrent is trumpable.

If you upload an album with improper file names then another user may fix the file names, re-upload, and report yours for deletion.

Your torrent was reported because it was trumped by another torrent with improved file names.',
        ],[
            'category_id'    => 1,
            'type'           => 'tracks_missing',
            'name'           => 'Track(s) Missing',
            'sequence'       => 240,
            'need_link'      => 'required',
            'need_track'     => 'all',
            'tracker_reason' => 15,
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please list the track number and title of the missing track.

Please provide a link to a reputable release catalogue such as Discogs that shows the correct track listing.

If the track has been replaced by a different track or is missing parts, you must provide a link to a version that has the correct audio so that staff may verify the status of the track(s)',
            'pm_body'        =>
                '[rule]2.1.19[/rule]. All music torrents must represent a complete release, and may not be missing tracks (or discs in the case of a multi-disc release).

[rule]2.1.19.2[/rule]. A single track (e.g., one MP3 file) cannot be uploaded on its own unless it is an officially released single. If a specific track can only be found on an album, the entire album must be uploaded in the torrent.

Your torrent was reported because it was missing tracks.',
        ],[
            'category_id'    => 1,
            'type'           => 'discs_missing',
            'name'           => 'Disc(s) Missing',
            'sequence'       => 120,
            'tracker_reason' => 6,
            'need_link'      => 'required',
            'need_track'     => 'required',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please provide a link to a reputable release catalogue such as Discogs, showing the correct track listing and specify which discs are missing.',
            'pm_body'        =>
                '[rule]2.1.19[/rule]. All music torrents must represent a complete release, and may not be missing tracks (or discs in the case of a multi-disc release).

[rule]2.1.19.1[/rule]. If an album is released as a multi-disc set (or box set) of CDs or vinyl discs, then it must be uploaded as a single torrent. Preferably, each individual CD rip in a multi-disc set should be organized in its own folder (see [rule]2.3.12[/rule]).

Your torrent was reported because it was missing discs.',
        ],[
            'category_id'    => 1,
            'type'           => 'mqa',
            'name'           => 'MQA Banned',
            'sequence'       => 130,
            'tracker_reason' => 14,
            'resolve_delete' => true,
            'resolve_log'    => 'MQA-encoded torrent',
            'need_image'     => 'required',
            'explanation'    => 'Please show screenshot proof that this is an MQA-encoded file (unless it is clearly stated in the Release Description).',
            'pm_body'        =>
                '[rule]1.2.9[/rule]. You have uploaded material that is currently forbidden. MQA-encoded FLAC torrents are not allowed on ' . SITE_NAME .'. For more information, see [[MQA]].',
        ],[
            'category_id'    => 1,
            'type'           => 'bonus_tracks',
            'name'           => 'Bonus Tracks Only',
            'sequence'       => 90,
            'is_active'      => false,
            'need_link'      => 'optional',
            'need_track'     => 'required',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please provide a link to a reputable release catalogue such as Discogs, that shows the correct track listing.

Per [rule]2.4.5[/rule], exclusive WEB-sourced bonus tracks are allowed to be uploaded separately.',
            'pm_body'        =>
                '[rule]2.1.19.3[/rule]. Bonus discs may be uploaded separately in accordance with [rule]h2.4[/rule]. Please note that individual bonus tracks cannot be uploaded without the rest of the album. Bonus tracks are not bonus discs. Enhanced audio CDs with data or video tracks must be uploaded without the non-audio tracks. If you want to share the videos or data, you may host the files off-site with a file sharing service and include the link to that service in your torrent description.

Your torrent was reported because it contained only bonus tracks without the full album.',
        ],[
            'category_id'    => 1,
            'type'           => 'transcode',
            'name'           => 'Transcode',
            'sequence'       => 250,
            'tracker_reason' => 16,
            'need_image'     => 'required',
            'need_track'     => 'required',
            'resolve_delete' => true,
            'resolve_warn'   => 2,
            'explanation'    => 'Please list the tracks you checked, and the method used to determine the transcode.

If possible, please include at least one screenshot of any spectral analysis done. You may include more than one.',
            'pm_body'        =>
                '[rule]2.1.2[/rule]. No transcodes or re-encodes of lossy releases are acceptable here.

Your torrent was reported because it contained transcoded audio files.',
        ],[
            'category_id'    => 1,
            'type'           => 'low',
            'name'           => 'Low Bitrate',
            'sequence'       => 170,
            'tracker_reason' => 10,
            'is_active'      => false,
            'need_sitelink'  => 'required',
            'resolve_delete' => true,
            'explanation'    => 'Please tell us the actual bitrate and the software used to check.

Please specify a link to the original torrent.',
            'pm_body'        =>
                '[rule]2.1.3[/rule]. Music releases that have a bitrate below 192 are trumped by higher bitrates.

Your torrent was reported because there are lossy versions with higher bitrates available.',
        ],[
            'category_id'    => 1,
            'type'           => 'mutt',
            'name'           => 'Mutt Rip',
            'sequence'       => 180,
            'tracker_reason' => 11,
            'need_track'     => 'required',
            'resolve_delete' => true,
            'resolve_warn'   => 2,
            'explanation'    => 'Please list at least two (2) tracks which have different bitrates and/or encoders, specifying the differences the tracks.',
            'pm_body'        =>
                '[rule]2.1.6[/rule]. All music torrents must be encoded with a single encoder using the same settings.

Your torrent was reported because it contained one or more audio files that were encoded by different audio encoders or with different encoder settings.',
        ],[
            'category_id'    => 1,
            'type'           => 'single_track',
            'name'           => 'Unsplit Album Rip',
            'sequence'       => 270,
            'tracker_reason' => 18,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please provide a link to a reputable release catalogue such as Discogs, that shows the correct track listing.

This option is for uploads of CDs ripped as a single track when it should be split as on the CD.

This option is not to be confused with uploads of a single track, taken from a CD with multiple tracks (Tracks Missing).',
            'pm_body'        =>
                '[rule]2.1.5[/rule]. Albums must not be ripped or uploaded as a single track.

[rule]2.1.5.1[/rule]. If the tracks on the original CD were separate, you must rip them to separate files. Any unsplit FLAC rips lacking a cue sheet will be deleted outright. Any unsplit FLAC rip that includes a cue sheet will be trumpable by a properly split FLAC torrent. CDs with single tracks can be uploaded without prior splitting.

Your torrent was reported because it contained a single-track rip instead of a rip consisting of separate audio files.',
        ],[
            'category_id'    => 1,
            'type'           => 'tags_lots',
            'name'           => 'Bad Tags / No Tags at All',
            'sequence'       => 82,
            'tracker_reason' => 4,
            'need_track'     => 'required',
            'explanation'    => 'Please specify which tags are missing, and whether they are missing from all tracks.

Ideally, you will replace this torrent with one with fixed tags and report this with the reason "Tag Trump".',
            'pm_body'        =>
                '[rule]2.3.16[/rule]. Properly tag your music files.

The Uploading Rules require that all uploads be properly tagged. Your torrent has been marked as having bad tags. It is now listed on [url=better.php]better.php[/url] and is eligible for trumping. You are of course free to fix this torrent yourself. Add or fix the required tags and upload the replacement torrent to the site. Then, report (RP) the older torrent using the category "Tag Trump" and indicate in the report comments that you have fixed the tags. Be sure to provide a link (PL) to the new replacement torrent.',
        ],[
            'category_id'    => 1,
            'type'           => 'folders_bad',
            'name'           => 'Bad Folder Names',
            'sequence'       => 81,
            'tracker_reason' => 3,
            'explanation'    => 'Please specify the issue with the folder names.

Ideally you will replace this torrent with one with fixed folder names and report this with the reason "Bad Folder Name Trump".',
            'pm_body'        =>
                '[rule]2.3.2[/rule]. Name your directories with meaningful titles, such as "Artist - Album (Year) - Format".

The Uploading Rules require that all uploads contain torrent directories with meaningful names. Your torrent has been marked as having a poorly named torrent directory. It is now listed on [url=better.php]better.php[/url] and is eligible for trumping. You are of course free to fix this torrent yourself. Add or fix the folder/directory name(s) and upload the replacement torrent to the site. Then, report (RP) the older torrent using the category "Folder Trump" and indicate in the report comments that you have fixed the directory name(s). Be sure to provide a link (PL) to the new replacement torrent.',
        ],[
            'category_id'    => 1,
            'type'           => 'wrong_format',
            'name'           => 'Wrong Specified Format',
            'sequence'       => 320,
            'tracker_reason' => 20,
            'explanation'    => 'Please specify the correct format.',
            'pm_body'        =>
                '[rule]2.1.4[/rule]. Bitrates must accurately reflect encoder presets or the average bitrate of the audio files. You are responsible for supplying correct format and bitrate information on the upload page.

Your torrent has now been labeled using the appropriate format and bitrate.',
        ],[
            'category_id'    => 1,
            'type'           => 'wrong_media',
            'name'           => 'Wrong Specified Media',
            'sequence'       => 330,
            'tracker_reason' => 21,
            'explanation'    => 'Please specify the correct media.',
        ],[
            'category_id'    => 1,
            'type'           => 'wrong_lyrics',
            'name'           => 'Wrong Lyrics file',
            'sequence'       => 340,
            'tracker_reason' => 22,
            'explanation'    => 'Please provide the list of song titles included in the lyrics, and if possible, what release they belong to.
"Low-quality" fan-sourced lyrics are not sufficient grounds for reporting.',
        ],[
            'category_id'    => 1,
            'type'           => 'format',
            'name'           => 'Disallowed Format',
            'sequence'       => 100,
            'tracker_reason' => 5,
            'need_track'     => 'all',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'If applicable, list the relevant tracks.',
            'pm_body'        =>
                '[rule]2.1.1[/rule]. The only formats allowed for music are:
[*] Lossy: MP3, AAC, AC3, DTS
[*] Lossless: FLAC
Your torrent was reported because it contained a disallowed format.',
        ],[
            'category_id'    => 1,
            'type'           => 'bitrate',
            'name'           => 'Inaccurate Bitrate',
            'sequence'       => 150,
            'tracker_reason' => 9,
            'need_track'     => 'required',
            'explanation'    => 'Please tell us the actual bitrate and the software used to check.
If the correct bitrate would make this torrent a duplicate, please report it as a dupe, and describe the mislabeling in "Comments".
If the correct bitrate would result in this torrent trumping another, please report it as a trump, and describe the mislabeling in "Comments".',
            'pm_body'        =>
                '[rule]2.1.4[/rule]. Bitrates must accurately reflect encoder presets or the average bitrate of the audio files. You are responsible for supplying correct format and bitrate information on the upload page.

Your torrent was reported because the bitrates of one or more audio files had been misrepresented.',
        ],[
            'category_id'    => 1,
            'type'           => 'source',
            'name'           => 'Radio/TV/FM/WEB Rip',
            'sequence'       => 210,
            'tracker_reason' => 12,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 2,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]2.1.11[/rule]. Music ripped from the radio (Satellite or FM), television, the web, or podcasts are not allowed.

The only allowable media formats are CD, DVD, Vinyl, Soundboard, SACD, DAT, Cassette, WEB, and Blu-ray.',
        ],[
            'category_id'    => 1,
            'type'           => 'discog',
            'name'           => 'Discography',
            'sequence'       => 130,
            'tracker_reason' => 7,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]2.1.20[/rule]. User made discographies may not be uploaded. Multi-album torrents are not allowed on the site under any circumstances. That means no discographies, Pitchfork compilations, etc. If releases (e.g., CD singles) were never released as a bundled set, do not upload them together. Live Soundboard material should be uploaded as one torrent per night, per show, or per venue. Including more than one show in a torrent results in a multi-album torrent.

Your torrent was reported because it consisted of a discography.',
        ],[
            'category_id'    => 1,
            'type'           => 'extra_files',
            'name'           => 'Extraneous Files',
            'sequence'       => 95,
            'tracker_reason' => 23,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'explanation'    => 'Please include as much information as possible to verify the report, identifying the tracks or files that are not part of the release.',
            'pm_body'        =>
                '[rule]2.1.6.2[/rule]. Extraneous material was found in this torrent. The torrent should contain only a single copy of each file that belongs in the release, and no other files that belong to separate releases.

Your torrent was reported because it contained extra files that do not belong in the release.',
        ],[
            'category_id'    => 1,
            'type'           => 'user_discog',
            'name'           => 'User Compilation',
            'sequence'       => 290,
            'tracker_reason' => 19,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]2.1.16[/rule]. User-made compilations are not allowed.

[rule]2.1.16.1[/rule]. These are defined as compilations made by the uploader or anyone else who does not officially represent the artist or the label. Compilations must be reasonably official. User-made and unofficial multichannel mixes are also not allowed.

Your torrent was reported because it was a user compilation.',
        ],[
            'category_id'    => 1,
            'type'           => 'lineage',
            'name'           => 'No Lineage Info',
            'sequence'       => 190,
            'explanation'    => 'Please list the specific information missing from the torrent (hardware, software, etc.).',
            'pm_body'        =>
                '[rule]2.3.9[/rule]. All lossless analog rips should include clear information about source lineage. All lossless SACD digital layer analog rips and vinyl rips must include clear information about recording equipment used (see [rule]h2.8[/rule]). If you used a USB turntable for a vinyl rip, clearly indicate this in your lineage information. Also include all intermediate steps up to lossless encoding, such as the program used for mastering, sound card used, etc. Lossless analog rips missing rip information can be trumped by better documented lossless analog rips of equal or better quality. In order to trump a lossless analog rip without a lineage, this lineage must be included as a .txt or .log file within the new torrent.

Your torrent is now eligible for trumping by a better-sounding rip with complete lineage information.',
        ],[
            'category_id'    => 1,
            'type'           => 'edited',
            'name'           => 'Edited Log',
            'sequence'       => 140,
            'tracker_reason' => 8,
            'resolve_delete' => true,
            'resolve_warn'   => 4,
            'explanation'    => 'Please explain exactly where you believe the log was edited.
The torrent will not show "Reported" on the group page, but rest assured that the report will be seen by moderators.',
            'pm_body'        =>
                '[rule]2.2.10.9[/rule]. No log editing is permitted.

[rule]2.2.10.9.1[/rule]. Forging log data is a serious misrepresentation of quality, and will result in a warning and the loss of your uploading privileges when the edited log is found. We recommend that you do not open the rip log file for any reason. However, if you must open the rip log, do not edit anything in the file for any reason. If you discover that one of your software settings is incorrect in the ripping software preferences, you must rip the CD again with the correct settings. Do not consolidate logs under any circumstances. If you must re-rip specific tracks or an entire disc and the rip results happen to have the new log appended to the original, leave them as is. Do not remove any part of either log, and never copy/paste parts of a new log over an old log.

Your torrent was reported because it contained an edited log (either edited by you or someone else). For questions about your uploading privileges, you must PM the staff member who handled this log case.',
        ],[
            'category_id'    => 1,
            'type'           => 'audience',
            'name'           => 'Audience Recording',
            'sequence'       => 70,
            'tracker_reason' => 22,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]2.1.12[/rule]. No unofficial audience recordings may be uploaded. These include but are not limited to AUD (Audience), IEM (In Ear Monitor), ALD (Assistive Listening Device), Mini-Disc, and Matrix-sourced recordings (see [rule]2.6.3[/rule]).

Your torrent was reported because it was sourced from an audience recording.',
        ],[
            'category_id'    => 1,
            'type'           => 'filename',
            'name'           => 'Bad File Names',
            'sequence'       => 80,
            'tracker_reason' => 2,
            'need_track'     => 'required',
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]2.3.11[/rule]. File names must accurately reflect the song titles. You may not have file names like 01track.mp3, 02track.mp3, etc. Torrents containing files that are named with incorrect song titles can be trumped by properly labeled torrents. Also, torrents that are sourced from the scene but do not have the "Scene" label must comply with site naming rules (no release group names in the file names, no advertisements in the file names, etc.). If all the letters in the track titles are capitalized, the torrent is trumpable.

[rule]2.3.13[/rule]. Track numbers are required in file names (e.g., "01 - TrackName.mp3"). If a torrent without track numbers in the file names is uploaded, then a torrent with the track numbers in the file names can take its place. When formatted properly, file names will sort in order by track number or playing order. Also see [rule]2.3.14[/rule].

The Uploading Rules require that all uploads contain audio tracks with accurate file names. Your torrent has been marked as having incorrect or incomplete file names. It is now listed on [url=better.php]better.php[/url] and is eligible for trumping. You are of course free to fix this torrent yourself. Add or fix the file names and upload the replacement torrent to the site. Then, report (RP) the older torrent using the category "Bad File Names Trump" and indicate in the report comments that you have fixed the file names. Be sure to provide a permalink (PL) to the new replacement torrent.',
        ],[
            'category_id'    => 1,
            'type'           => 'skips',
            'name'           => 'Skips / Encode Errors',
            'sequence'       => 220,
            'need_track'     => 'all',
            'tracker_reason' => 13,
            'resolve_delete' => true,
            'explanation'    => 'If you have not already done so, make sure that your client has marked the torrent as completed and seeding at 100%. You must also perform a force recheck on the torrent to ensure that the files are not corrupted on your end.

Please be as thorough as possible and include as much detail as you can. Identify which tracks have problems, and their nature (silence, glitch, scrambled). Add a time position (mm:ss) where the errors occur. If the tracks are lossless, supply the output of <tt>flac -d &lt;file.flac&gt;</tt> if possible.

If the entire track is incorrect or is missing parts, you must provide a link to a version that has the correct audio so that staff may verify the status of the track(s)

[important]We will dismiss as incomplete[/important] a report that lacks required information on the errors.

[important]You must include in the report 1) detailed information on the nature of the errors 2) the download is complete, and 3) you have forced a recheck on the torrent.[/important]',
            'pm_body'        =>
                '[rule]2.1.8[/rule]. Music not sourced from vinyl must not contain pops, clicks, or skips. They will be deleted for rip/encode errors if reported.

Your torrent was reported because one or more tracks contain encoding errors.',
        ],[
            'category_id'    => 1,
            'type'           => 'rescore',
            'name'           => 'Log Rescore Request',
            'sequence'       => 160,
            'explanation'    => 'Explain exactly why you believe this log requires rescoring. For example, if it is a non-English log that needs scoring, or if the log was not uploaded at all.
For checksum rescores of existing logs, please include the output from the appropriate validator (EAC\'s CheckLog.exe or XLD\'s log checker)and the version used. The scores given on other sites are known to be subject to errors. As a consequence, [b]a log with no checksum warning on another site is not grounds for requesting a rescore if it has an invalid checksum warning on ' . SITE_NAME . '[/b]',
            'pm_body'        =>
                '[rule]2.2.10.3[/rule]. A FLAC upload with an EAC or XLD rip log that scores 100% on the log checker replaces one with a lower score... Note: A FLAC upload with a log that scores 95% for not defeating the audio cache may be rescored to 100% following the procedure outlined in [url=wiki.php?action=article&amp;id=79]this wiki[/url].

[rule]2.2.10.5[/rule]. XLD and EAC logs in languages other than English require a manual log checker score adjustment by staff.

[rule]2.2.10.6.2[/rule]. If you created a CD range rip that has matching CRCs for test and copy, and where every track has an AccurateRip score of 2 or more, then you may submit your torrent for manual score adjustment.

[rule]2.2.10.9.2[/rule]. If you find that an appended log has not been scored properly, please report the torrent and use the log rescore option.

Your torrent has now been properly scored by the staff.',
        ],[
            'category_id'    => 1,
            'type'           => 'lossyapproval',
            'name'           => 'Lossy Master Approval Request',
            'sequence'       => 161,
            'need_image'     => 'proof',
            'explanation'    => 'Please include as much information as possible to verify the report, including spectral analysis images.

For WEB purchases, please include a link to the webstore where you obtained the album and a screenshot of your invoice.

For CDs or other physical media, please include a photograph of the album next to a piece of paper with your username written on it.

[important]Anything included in the proof images field will only be viewable by staff.[/important]',
        ],[
            'category_id'    => 2,
            'type'           => 'missing_crack',
            'name'           => 'No Crack/Keygen/Patch',
            'sequence'       => 70,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]4.1.2[/rule]. All applications must come with a crack, keygen, or other method of ensuring that downloaders can install them easily. App torrents with keygens, cracks, or patches that do not work or torrents missing clear installation instructions will be deleted if reported. No exceptions.

Your torrent was reported because it was missing an installation method.',
        ],[
            'category_id'    => 2,
            'sequence'       => 50,
            'name'           => 'Game',
            'type'           => 'game',
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 4,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]1.2.5[/rule]. Games of any kind. No games of any kind for PC, Mac, Linux, mobile devices, or any other platform are allowed.

[rule]4.1.7[/rule]. Games of any kind are prohibited (see [rule]1.2.5[/rule]).

Your torrent was reported because it contained a game disc rip.',
        ],[
            'category_id'    => 2,
            'type'           => 'free',
            'name'           => 'Freely Available',
            'sequence'       => 40,
            'need_link'      => 'required',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include a link to a source of information or to the freely available app itself.',
            'pm_body'        =>
                '[rule]4.1.3[/rule]. App releases must not be freely available tools. Application releases cannot be freely downloaded anywhere from any official source. Nor may you upload open source applications where the source code is available for free.

Your torrent was reported because it contained a freely available application.',
        ],[
            'category_id'    => 2,
            'type'           => 'description',
            'name'           => 'No Description',
            'sequence'       => 80,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'If possible, please provide a link to an accurate description.',
            'pm_body'        =>
                '[rule]4.1.4[/rule]. Release descriptions for applications must contain good information about the application. You should either have a small description of the program (either taken from its web site or from an NFO file) or a link to the information&#8202;&mdash;&#8202;but ideally both. Torrents missing this information will be deleted when reported.

Your torrent was reported because it lacked adequate release information.',
        ],[
            'category_id'    => 2,
            'type'           => 'pack',
            'name'           => 'Archived Pack',
            'sequence'       => 20,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]2.1.18[/rule]. Sound Sample Packs must be uploaded as applications.

[rule]4.1.9[/rule]. Sound sample packs, template collections, and font collections are allowed if they are official releases, not freely available, and unarchived. Sound sample packs, template collections, and font collections must be official compilations and they must not be uploaded as an archive.

The files contained inside the torrent must not be archived so that users can see what the pack contains. That means if sound sample packs are in WAV format, they must be uploaded as WAV. If the font collection, template collection, or sound sample pack was originally released as an archive, you must unpack the files before uploading them in a torrent. None of the contents in these packs and collections may be freely available.

Your torrent was reported because it was an archived collection.',
        ],[
            'category_id'    => 2,
            'type'           => 'collection',
            'name'           => 'Collection of Cracks',
            'sequence'       => 30,
            'is_active'      => false,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]4.1.11[/rule]. Collections of cracks, keygens or serials are not allowed. The crack, keygen, or serial for an application must be in a torrent with its corresponding application. It cannot be uploaded separately from the application.

Your torrent was reported because it contained a collection of serials, keygens, or cracks.',
        ],[
            'category_id'    => 2,
            'type'           => 'hack',
            'name'           => 'Hacking Tool',
            'sequence'       => 60,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]4.1.12[/rule]. Torrents containing hacking or cracking tools are prohibited.

Your torrent was reported because it contained a hacking tool.',
        ],[
            'category_id'    => 2,
            'type'           => 'virus',
            'name'           => 'Contains Virus',
            'sequence'       => 60,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include as much information as possible to verify the report. Please also double-check that your virus scanner is not incorrectly identifying a keygen or crack as a virus.',
            'pm_body'        =>
                '[rule]4.1.14[/rule]. All applications must be complete. The torrent was determined to be infected with a virus or trojan. In the future, please scan all potential uploads with an antivirus program such as AVG, Avast, or MS Security Essentials.

Your torrent was reported because it contained a virus or trojan.',
        ],[
            'category_id'    => 2,
            'type'           => 'notwork',
            'name'           => 'Not Working',
            'sequence'       => 60,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]4.1.14[/rule]. All applications must be complete. This program was determined to be not fully functional.

Your torrent was reported because it contained a program that did not work or no longer works.',
        ],[
            'category_id'    => 3,
            'type'           => 'unrelated',
            'name'           => 'Ebook Collection',
            'sequence'       => 270,
            'resolve_delete' => true,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]6.5[/rule]. Collections/packs of ebooks are prohibited, even if each title is somehow related to other ebook titles in some way. All ebooks must be uploaded individually and cannot be archived (users must be able to see the ebook format in the torrent).

Your torrent was reported because it contained a collection or pack of ebooks.',
        ],[
            'category_id'    => 4,
            'type'           => 'skips_audiobook',
            'name'           => 'Skips / Encode Errors',
            'sequence'       => 210,
            'need_track'     => 'all',
            'tracker_reason' => 13,
            'resolve_delete' => true,
            'explanation'    => '[important]Please be as thorough as possible and include as much detail as you can. Refer to specific tracks and time positions to justify your report.[/important]',
            'pm_body'        =>
                '[rule]2.1.8[/rule]. Audiobooks must not contain pops, clicks, or skips. They will be deleted for rip/encode errors if reported.

Your torrent was reported because one or more audiobook tracks contain encoding errors.',
        ],[
            'category_id'    => 5,
            'type'           => 'disallowed',
            'name'           => 'Disallowed Topic',
            'sequence'       => 20,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]7.3[/rule]. Tutorials on how to use musical instruments, vocal training, producing music, or otherwise learning the theory and practice of music are the only allowed topics. No material outside of these topics is allowed. For example, instruction videos about Kung Fu training, dance lessons, beer brewing, or photography are not permitted here. What is considered allowable under these topics is ultimately at the discretion of the staff.

Your torrent was reported because it contained a video that has no relevance to the allowed music-related topics on the site.',
        ],[
            'category_id'    => 6,
            'type'           => 'talkshow',
            'name'           => 'Talkshow/Podcast',
            'sequence'       => 270,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'resolve_warn'   => 1,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]3.3[/rule]. No radio talk shows or podcasts are allowed. Those recordings do not belong in any torrent category.

Your torrent was reported because it contained audio files sourced from a talk show or podcast.',
        ],[
            'category_id'    => 7,
            'type'           => 'titles',
            'name'           => 'Multiple Comic Titles',
            'sequence'       => 180,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]5.2.3[/rule]. Collections may not span more than one comic title. You may not include multiple, different comic titles in a single collection, e.g., "The Amazing Spider-Man #1" and "The Incredible Hulk #1."

Your torrent was reported because it contained comics from multiple unrelated series.',
        ],[
            'category_id'    => 7,
            'type'           => 'volumes',
            'name'           => 'Multiple Volumes',
            'sequence'       => 190,
            'need_link'      => 'optional',
            'resolve_delete' => true,
            'explanation'    => 'Please include as much information as possible to verify the report.',
            'pm_body'        =>
                '[rule]5.2.6[/rule]. Torrents spanning multiple volumes are too large and must be uploaded as separate volumes.

Your torrent was reported because it contained multiple comic volumes.',
        ]])->save();

        $this->table('torrent_report_configuration_log', ['id' => false, 'primary_key'=> ['torrent_report_configuration_log_id'], 'encoding' => 'utf8mb4'])
            ->addColumn('torrent_report_configuration_log_id', 'integer', ['identity' => true])
            ->addColumn('torrent_report_configuration_id', 'integer')
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('user_id', 'integer')
            ->addColumn('change_set', 'json')
            ->addForeignKey('torrent_report_configuration_id', 'torrent_report_configuration', 'torrent_report_configuration_id')
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->create();
    }

    public function down(): void {
        $this->table('torrent_report_configuration_log')
            ->drop()
            ->save();
        $this->table('torrent_report_configuration')
            ->drop()
            ->save();
        $this->table('category')
            ->drop()
            ->save();
    }
}
