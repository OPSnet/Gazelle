<?php
/*
 * This array is the backbone of the reports system.
 * Important thing to note about the array:
 *   1. When coding for a non music site, you need to ensure that the top level of the
 * array lines up with the CATEGORIES array in lib/config.php.
 *   2. The first sub array contains resolves that are present on every report type
 * regardless of category.
 *   3. The only part that shouldn't be self-explanatory is that for the tracks field in
 * the report_fields arrays, 0 means not shown, 1 means required, 2 means required but
 * you can't select the 'All' box.
 *   4. The current report_fields that are set up are tracks, sitelink, link and image. If
 * you wanted to add a new one, you'd need to add a field to the reportsv2 table, elements
 * to the relevant report_fields arrays here, add the HTML in ajax_report and add security
 * in takereport.
 */

return [
    'master' => [
        'dupe' => [
            'priority' => '10',
            'reason' => '0',
            'title' => 'Dupe',
            'report_messages' => [
                'Please specify a link to the original torrent.'
            ],
            'report_fields' => [
                'sitelink' => '1'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]h2.2[/rule]. Your torrent was reported because it was a duplicate of another torrent.'
            ]
        ],
        'banned' => [
            'priority' => '230',
            'reason' => '14',
            'title' => 'Specifically Banned',
            'report_messages' => [
                'Please specify exactly which entry on the Do Not Upload list this is violating.'
            ],
            'report_fields' => [
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '4',
                'delete' => '1',
                'pm' => '[rule]h1.2[/rule]. You have uploaded material that is currently forbidden. Items on the Do Not Upload (DNU) list (at the top of the [url=upload.php]upload page[/url]) and in the [url=rules.php?p=upload#h1.2]Specifically Banned[/url] portion of the uploading rules cannot be uploaded to the site. Do not upload them unless your torrent meets a condition specified in the comments of the DNU list.
Your torrent was reported because it contained material from the DNU list or from the Specifically Banned section of the rules.'
            ]
        ],
        'urgent' => [
            'priority' => '280',
            'reason' => '-1',
            'title' => 'Urgent',
            'report_messages' => [
                'This report type is only for very urgent reports, usually for personal information being found within a torrent.',
                'Abusing the "Urgent" report type could result in a warning or worse.',
                'As this report type gives the staff absolutely no information about the problem, please be as clear as possible in your comments about what the problem is.'
            ],
            'report_fields' => [
                'sitelink' => '0',
                'track' => '0',
                'link' => '0',
                'image' => '0',
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => ''
            ]
        ],
        'other' => [
            'priority' => '200',
            'reason' => '-1',
            'title' => 'Other',
            'report_messages' => [
                'Please include as much information as possible to verify the report.'
            ],
            'report_fields' => [
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => ''
            ]
        ],
        'trump' => [
            'priority' => '20',
            'reason' => '1',
            'title' => 'Trump',
            'report_messages' => [
                'Please list the specific reason(s) the newer torrent trumps the older one.',
                'Please make sure you are reporting the torrent <strong class="important_text">which has been trumped</strong> and should be deleted, not the torrent that you think should remain on site.'
            ],
            'report_fields' => [
                'sitelink' => '1'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]h2.2[/rule]. Your torrent was reported because it was trumped by another torrent.'
            ]
        ]
    ],

    '1' => [ //Music Resolves
        'checksum_trump' =>  [
            'priority' => '10',
            'reason' => '24',
            'title' => 'Checksum Trump',
            'report_messages' => [
                'Please make certain that your checksum trump is valid (rules 2.2.10 and below). Only CD media rips are subject to checksum trumps.',
                'Please make sure you are reporting the torrent <strong class="important_text">which has been trumped</strong> and should be deleted, not the torrent that you think should remain on site.'
            ],
            'report_fields' => [
                'sitelink' => '1'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]2.2.10.3[/rule]. A FLAC upload with an EAC, XLD, or whipper rip log with a valid checksum that scores 100% on the log checker replaces one with a lower score or bad or missing checksum. No log scoring less than 100% can trump an already existing one that scores under 100%. Your torrent was reported because it was trumped by another torrent that was ripped with a log file that scored 100%.'
            ]
        ],
        'tag_trump' =>  [
            'priority' => '50',
            'reason' => '4',
            'title' => 'Tag Trump',
            'report_messages' => [
                'Please list the specific tag(s) the newer torrent trumps the older one.',
                'Please make sure you are reporting the torrent <strong class="important_text">which has been trumped</strong> and should be deleted, not the torrent that you think should remain on site.'
            ],
            'report_fields' => [
                'sitelink' => '1'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]2.3.16[/rule]. Properly tag your music files. Certain meta tags (e.g., ID3, Vorbis) are required on all music uploads. Make sure to use the appropriate tag format for your files (e.g., no ID3 tags for FLAC - see [rule]2.2.10.8[/rule]). ID3v2 tags for files are highly recommended over ID3v1. ID3 are recommended for AC3 torrents but are not mandatory because the format does not natively support file metadata tagging (for AC3, the file names become the vehicle for correctly labeling media files). Torrents uploaded with both good ID3v1 tags and blank ID3v2 tags (a dual set of tags) are trumpable by torrents with either just good ID3v1 tags or good ID3v2 tags (a single set of tags). If you upload an album missing one or more of the required tags, then another user may add the tags, re-upload, and report your torrent for deletion.
Your torrent was reported because it was trumped by another torrent with improved metadata tags.'
            ]
        ],
        'vinyl_trump' => [
            'priority' => '60',
            'reason' => '1',
            'title' => 'Vinyl Trump',
            'report_messages' => [
                'Please list the specific reason(s) the newer torrent trumps the older one.',
                '<strong class="important_text">Please be as thorough as possible and include as much detail as you can. Refer to specific tracks and time positions to justify your report.</strong>',
                'Please make sure you are reporting the torrent <strong class="important_text">which has been trumped</strong> and should be deleted, not the torrent that you think should remain on site.'
            ],
            'report_fields' => [
                'sitelink' => '1'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]2.5.5[/rule]. Vinyl rips may be trumped by better-sounding rips of the same bit depth, regardless of lineage information (see [rule]2.3.9[/rule]).
Your torrent was reported as it was trumped by a better-sounding vinyl rip.'
            ]
        ],
        'folder_trump' =>  [
            'priority' => '40',
            'reason' => '3',
            'title' => 'Bad Folder Name Trump',
            'report_messages' => [
                'Please list the folder name and what is wrong with it.',
                'Please make sure you are reporting the torrent <strong class="important_text">which has been trumped</strong> and should be deleted, not the torrent that you think should remain on site.'
            ],
            'report_fields' => [
                'sitelink' => '1'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]2.3.2[/rule]. Name your directories with meaningful titles, such as "Artist - Album (Year) - Format". The minimum acceptable is "Album" although it is preferable to include more information. If the directory name does not include this minimum then another user can rename the directory, re-upload, and report your torrent for deletion. In addition, torrent folders that are named using the scene convention will be trumpable if the Scene label is absent from the torrent.
[rule]2.3.3[/rule]. Avoid creating unnecessary nested folders (such as an extra folder for the actual album) inside your properly named directory. A torrent with unnecessary nested folders is trumpable by a torrent with such folders removed. For single disc albums, all audio files must be included in the main torrent folder. For multi-disc albums, the main torrent folder may include one sub-folder that holds the audio file contents for each disc in the box set, i.e., the main torrent folder is "Adele - 19 (2008) - FLAC" while appropriate sub-folders may include "19 (Disc 1of2)" or "19" and "Live From The Hotel Cafe (Disc 2of2)" or "Acoustic Set Live From The Hotel Cafe, Los Angeles." Additional folders are unnecessary because they do nothing to improve the organization of the torrent. If you are uncertain about what to do for other cases, PM a staff member for guidance.
Your torrent was reported because it was trumped by another torrent with an improved folder name and directory structure.'
            ]
        ],
        'file_trump' =>  [
            'priority' => '30',
            'reason' => '2',
            'title' => 'Bad File Names Trump',
            'report_messages' => [
                'Please describe what is wrong with the file names.',
                'Please make sure you are reporting the torrent <strong class="important_text">which has been trumped</strong> and should be deleted, not the torrent that you think should remain on site.'
            ],
            'report_fields' => [
                'sitelink' => '1'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]2.3.11[/rule]. File names must accurately reflect the song titles. You may not have file names like 01track.mp3, 02track.mp3, etc. Torrents containing files that are named with incorrect song titles can be trumped by properly labeled torrents. Also, torrents that are sourced from the scene but do not have the Scene label must comply with site naming rules (no release group names in the file names, no advertisements in the file names, etc.). If all the letters in the track titles are capitalized, the torrent is trumpable. If you upload an album with improper file names, then another user may fix the file names, re-upload, and report yours for deletion.
Your torrent was reported because it was trumped by another torrent with improved file names.'
            ]
        ],
        'tracks_missing' => [
            'priority' => '240',
            'reason' => '15',
            'title' => 'Track(s) Missing',
            'report_messages' => [
                'Please list the track number and title of the missing track.',
                'Please provide a link to a reputable release catalogue such as Discogs that shows the correct track listing.'
            ],
            'report_fields' => [
                'track' => '2',
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]2.1.19[/rule]. All music torrents must represent a complete release, and may not be missing tracks (or discs in the case of a multi-disc release).
[rule]2.1.19.2[/rule]. A single track (e.g., one MP3 file) cannot be uploaded on its own unless it is an officially released single. If a specific track can only be found on an album, the entire album must be uploaded in the torrent.
Your torrent was reported because it was missing tracks.'
            ]
        ],
        'discs_missing' => [
            'priority' => '120',
            'reason' => '6',
            'title' => 'Disc(s) Missing',
            'report_messages' => [
                'Please provide a link to a reputable release catalogue such as Discogs, showing the correct track listing and specify which discs are missing.'
            ],
            'report_fields' => [
                'track' => '0',
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]2.1.19[/rule]. All music torrents must represent a complete release, and may not be missing tracks (or discs in the case of a multi-disc release).
[rule]2.1.19.1[/rule]. If an album is released as a multi-disc set (or box set) of CDs or vinyl discs, then it must be uploaded as a single torrent. Preferably, each individual CD rip in a multi-disc set should be organized in its own folder (see [rule]2.3.12[/rule]).
Your torrent was reported because it was missing discs.'
            ]
        ],
        'mqa' => [
            'priority' => '130',
            'reason' => '14',
            'title' => 'MQA Banned',
            'report_messages' => [
                'Please show screenshot proof that this is an MQA-encoded file (unless it is explicitly stated in the Release Description).'
            ],
            'extra_log' => 'MQA-encoded torrent',
            'report_fields' => [
                'image' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]1.2.9[/rule]. You have uploaded material that is currently forbidden. MQA-encoded FLAC torrents are not allowed on ' . SITE_NAME .'. For more information, see [[MQA]].'
            ]
        ],
        'bonus_tracks' => [
            'priority' => '90',
            'reason' => '-1',
            'title' => 'Bonus Tracks Only',
            'report_messages' => [
                'Please provide a link to a reputable release catalogue such as Discogs, that shows the correct track listing.',
                'Per [rule]2.4.5[/rule], exclusive WEB-sourced bonus tracks are allowed to be uploaded separately.'
            ],
            'report_fields' => [
                'track' => '0',
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]2.1.19.3[/rule]. Bonus discs may be uploaded separately in accordance with [rule]h2.4[/rule]. Please note that individual bonus tracks cannot be uploaded without the rest of the album. Bonus tracks are not bonus discs. Enhanced audio CDs with data or video tracks must be uploaded without the non-audio tracks. If you want to share the videos or data, you may host the files off-site with a file sharing service and include the link to that service in your torrent description.
Your torrent was reported because it contained only bonus tracks without the full album.'
            ]
        ],
        'transcode' => [
            'priority' => '250',
            'reason' => '16',
            'title' => 'Transcode',
            'report_messages' => [
                "Please list the tracks you checked, and the method used to determine the transcode.",
                "If possible, please include at least one screenshot of any spectral analysis done. You may include more than one."
            ],
            'report_fields' => [
                'image' => '0',
                'track' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '2',
                'delete' => '1',
                'pm' => '[rule]2.1.2[/rule]. No transcodes or re-encodes of lossy releases are acceptable here.
Your torrent was reported because it contained transcoded audio files.'
            ]
        ],
        'low' => [
            'priority' => '170',
            'reason' => '10',
            'title' => 'Low Bitrate',
            'report_messages' => [
                "Please tell us the actual bitrate and the software used to check.",
                'Please specify a link to the original torrent.'
            ],
            'report_fields' => [
                'sitelink' => '1'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]2.1.3[/rule]. Music releases that have a bitrate below 192 are trumped by higher bitrates.
Your torrent was reported because there are lossy versions with higher bitrates available.'
            ]
        ],
        'mutt' => [
            'priority' => '180',
            'reason' => '11',
            'title' => 'Mutt Rip',
            'report_messages' => [
                "Please list at least two (2) tracks which have different bitrates and/or encoders, specifying the differences the tracks."
            ],
            'report_fields' => [
                'track' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '2',
                'delete' => '1',
                'pm' => '[rule]2.1.6[/rule]. All music torrents must be encoded with a single encoder using the same settings.
Your torrent was reported because it contained one or more audio files that were encoded by different audio encoders or with different encoder settings.'
            ]
        ],
        'single_track' => [
            'priority' => '270',
            'reason' => '18',
            'title' => 'Unsplit Album Rip',
            'report_messages' => [
                "Please provide a link to a reputable release catalogue such as Discogs, that shows the correct track listing.",
                "This option is for uploads of CDs ripped as a single track when it should be split as on the CD.",
                "This option is not to be confused with uploads of a single track, taken from a CD with multiple tracks (Tracks Missing)."
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]2.1.5[/rule]. Albums must not be ripped or uploaded as a single track.
[rule]2.1.5.1[/rule]. If the tracks on the original CD were separate, you must rip them to separate files. Any unsplit FLAC rips lacking a cue sheet will be deleted outright. Any unsplit FLAC rip that includes a cue sheet will be trumpable by a properly split FLAC torrent. CDs with single tracks can be uploaded without prior splitting.
Your torrent was reported because it contained a single-track rip instead of a rip consisting of separate audio files.'
            ]
        ],
        'tags_lots' => [
            'priority' => '82',
            'reason' => '4',
            'title' => 'Bad Tags / No Tags at All',
            'report_messages' => [
                "Please specify which tags are missing, and whether they're missing from all tracks.",
                "Ideally, you will replace this torrent with one with fixed tags and report this with the reason \"Tag Trump\"."
            ],
            'report_fields' => [
                'track' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => "[rule]2.3.16[/rule]. Properly tag your music files.
The Uploading Rules require that all uploads be properly tagged. Your torrent has been marked as having bad tags. It is now listed on [url=better.php]better.php[/url] and is eligible for trumping. You are of course free to fix this torrent yourself. Add or fix the required tags and upload the replacement torrent to the site. Then, report (RP) the older torrent using the category \"Tag Trump\" and indicate in the report comments that you have fixed the tags. Be sure to provide a link (PL) to the new replacement torrent."
            ]
        ],
        'folders_bad' => [
            'priority' => '81',
            'reason' => '3',
            'title' => 'Bad Folder Names',
            'report_messages' => [
                "Please specify the issue with the folder names.",
                "Ideally you will replace this torrent with one with fixed folder names and report this with the reason \"Bad Folder Name Trump\"."
                ],
            'report_fields' => [],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => "[rule]2.3.2[/rule]. Name your directories with meaningful titles, such as \"Artist - Album (Year) - Format\".
The Uploading Rules require that all uploads contain torrent directories with meaningful names. Your torrent has been marked as having a poorly named torrent directory. It is now listed on [url=better.php]better.php[/url] and is eligible for trumping. You are of course free to fix this torrent yourself. Add or fix the folder/directory name(s) and upload the replacement torrent to the site. Then, report (RP) the older torrent using the category \"Folder Trump\" and indicate in the report comments that you have fixed the directory name(s). Be sure to provide a link (PL) to the new replacement torrent."
            ]
        ],
        'wrong_format' => [
            'priority' => '320',
            'reason' => '20',
            'title' => 'Wrong Specified Format',
            'report_messages' => [
                "Please specify the correct format."
            ],
            'report_fields' => [
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => '[rule]2.1.4[/rule]. Bitrates must accurately reflect encoder presets or the average bitrate of the audio files. You are responsible for supplying correct format and bitrate information on the upload page.
Your torrent has now been labeled using the appropriate format and bitrate.'
            ]
        ],
        'wrong_media' => [
            'priority' => '330',
            'reason' => '21',
            'title' => 'Wrong Specified Media',
            'report_messages' => [
                "Please specify the correct media."
            ],
            'report_fields' => [
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => ''
            ]
        ],
        'wrong_lyrics' => [
            'priority' => '340',
            'reason' => '22',
            'title' => 'Wrong Lyrics file',
            'report_messages' => [
                "Please provide the list of song titles included in the lyrics, and if possible, what release they belong to.",
                '"Low-quality" fan-sourced lyrics are not sufficient grounds for reporting.',
            ],
            'report_fields' => [
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => ''
            ]
        ],

        'format' => [
            'priority' => '100',
            'reason' => '5',
            'title' => 'Disallowed Format',
            'report_messages' => [
                "If applicable, list the relevant tracks."
            ],
            'report_fields' => [
                'track' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]2.1.1[/rule]. The only formats allowed for music are:
Lossy: MP3, AAC, AC3, DTS
Lossless: FLAC
Your torrent was reported because it contained a disallowed format.'
            ]
        ],
        'bitrate' => [
            'priority' => '150',
            'reason' => '9',
            'title' => 'Inaccurate Bitrate',
            'report_messages' => [
                "Please tell us the actual bitrate and the software used to check.",
                "If the correct bitrate would make this torrent a duplicate, please report it as a dupe, and describe the mislabeling in \"Comments\".",
                "If the correct bitrate would result in this torrent trumping another, please report it as a trump, and describe the mislabeling in \"Comments\"."
            ],
            'report_fields' => [
                'track' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => '[rule]2.1.4[/rule]. Bitrates must accurately reflect encoder presets or the average bitrate of the audio files. You are responsible for supplying correct format and bitrate information on the upload page.
Your torrent was reported because the bitrates of one or more audio files had been misrepresented.'
            ]
        ],
        'source' => [
            'priority' => '210',
            'reason' => '12',
            'title' => 'Radio/TV/FM/WEB Rip',
            'report_messages' => [
                "Please include as much information as possible to verify the report."
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '2',
                'delete' => '1',
                'pm' => '[rule]2.1.11[/rule]. Music ripped from the radio (Satellite or FM), television, the web, or podcasts are not allowed.
The only allowable media formats are CD, DVD, Vinyl, Soundboard, SACD, DAT, Cassette, WEB, and Blu-ray.'
            ]
        ],
        'discog' => [
            'priority' => '130',
            'reason' => '7',
            'title' => 'Discography',
            'report_messages' => [
                "Please include as much information as possible to verify the report."
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]2.1.20[/rule]. User made discographies may not be uploaded. Multi-album torrents are not allowed on the site under any circumstances. That means no discographies, Pitchfork compilations, etc. If releases (e.g., CD singles) were never released as a bundled set, do not upload them together. Live Soundboard material should be uploaded as one torrent per night, per show, or per venue. Including more than one show in a torrent results in a multi-album torrent.
Your torrent was reported because it consisted of a discography.'
            ]
        ],
        'extra_files' => [
            'priority' => '95',
            'reason' => '23',
            'title' => 'Extraneous Files',
            'report_messages' => [
                "Please include as much information as possible to verify the report, identifying the tracks or files that are not part of the release."
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]2.1.6.2[/rule]. Extraneous material was found in this torrent.
[rule]2.1.6.2[/rule]. The torrent should contain only a single copy of each file that belongs in the release, and no other files that belong to separate releases.
Your torrent was reported because it contained extra files that do not belong in the release.'
            ]
        ],
        'user_discog' => [
            'priority' => '290',
            'reason' => '19',
            'title' => 'User Compilation',
            'report_messages' => [
                "Please include as much information as possible to verify the report."
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]2.1.16[/rule]. User-made compilations are not allowed.
[rule]2.1.16.1[/rule]. These are defined as compilations made by the uploader or anyone else who does not officially represent the artist or the label. Compilations must be reasonably official. User-made and unofficial multichannel mixes are also not allowed.
Your torrent was reported because it was a user compilation.'
            ]
        ],
        'lineage' => [
            'priority' => '190',
            'reason' => '-1',
            'title' => 'No Lineage Info',
            'report_messages' => [
                "Please list the specific information missing from the torrent (hardware, software, etc.)."
            ],
            'report_fields' => [
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => '[rule]2.3.9[/rule]. All lossless analog rips should include clear information about source lineage. All lossless SACD digital layer analog rips and vinyl rips must include clear information about recording equipment used (see [rule]h2.8[/rule]). If you used a USB turntable for a vinyl rip, clearly indicate this in your lineage information. Also include all intermediate steps up to lossless encoding, such as the program used for mastering, sound card used, etc. Lossless analog rips missing rip information can be trumped by better documented lossless analog rips of equal or better quality. In order to trump a lossless analog rip without a lineage, this lineage must be included as a .txt or .log file within the new torrent.
Your torrent is now eligible for trumping by a better-sounding rip with complete lineage information.'
            ]
        ],
        'edited' => [
            'priority' => '140',
            'reason' => '8',
            'title' => 'Edited Log',
            'report_messages' => [
                "Please explain exactly where you believe the log was edited.",
                "The torrent will not show 'reported' on the group page, but rest assured that the report will be seen by moderators."
            ],
            'report_fields' => [
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '4',
                'delete' => '1',
                'pm' => '[rule]2.2.10.9[/rule]. No log editing is permitted.
[rule]2.2.10.9.1[/rule]. Forging log data is a serious misrepresentation of quality, and will result in a warning and the loss of your uploading privileges when the edited log is found. We recommend that you do not open the rip log file for any reason. However, if you must open the rip log, do not edit anything in the file for any reason. If you discover that one of your software settings is incorrect in the ripping software preferences, you must rip the CD again with the correct settings. Do not consolidate logs under any circumstances. If you must re-rip specific tracks or an entire disc and the rip results happen to have the new log appended to the original, leave them as is. Do not remove any part of either log, and never copy/paste parts of a new log over an old log.
Your torrent was reported because it contained an edited log (either edited by you or someone else). For questions about your uploading privileges, you must PM the staff member who handled this log case.'
            ]
        ],
        'audience' => [
            'priority' => '70',
            'reason' => '22',
            'title' => 'Audience Recording',
            'report_messages' => [
                "Please include as much information as possible to verify the report."
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]2.1.12[/rule]. No unofficial audience recordings may be uploaded. These include but are not limited to AUD (Audience), IEM (In Ear Monitor), ALD (Assistive Listening Device), Mini-Disc, and Matrix-sourced recordings (see [rule]2.6.3[/rule]).
Your torrent was reported because it was sourced from an audience recording.'
            ]
        ],
        'filename' => [
            'priority' => '80',
            'reason' => '2',
            'title' => 'Bad File Names',
            'report_messages' => [
            ],
            'report_fields' => [
                'track' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => '[rule]2.3.11[/rule]. File names must accurately reflect the song titles. You may not have file names like 01track.mp3, 02track.mp3, etc. Torrents containing files that are named with incorrect song titles can be trumped by properly labeled torrents. Also, torrents that are sourced from the scene but do not have the "Scene" label must comply with site naming rules (no release group names in the file names, no advertisements in the file names, etc.). If all the letters in the track titles are capitalized, the torrent is trumpable.

[rule]2.3.13[/rule]. Track numbers are required in file names (e.g., "01 - TrackName.mp3"). If a torrent without track numbers in the file names is uploaded, then a torrent with the track numbers in the file names can take its place. When formatted properly, file names will sort in order by track number or playing order. Also see [rule]2.3.14[/rule].
The Uploading Rules require that all uploads contain audio tracks with accurate file names. Your torrent has been marked as having incorrect or incomplete file names. It is now listed on [url=better.php]better.php[/url] and is eligible for trumping. You are of course free to fix this torrent yourself. Add or fix the file names and upload the replacement torrent to the site. Then, report (RP) the older torrent using the category "Bad File Names Trump" and indicate in the report comments that you have fixed the file names. Be sure to provide a permalink (PL) to the new replacement torrent.'
            ]
        ],
        'skips' => [
            'priority' => '220',
            'reason' => '13',
            'title' => 'Skips / Encode Errors',
            'report_messages' => [
                'If you have not already done so, make sure that your client has marked the torrent as completed and seeding at 100%. You must also perform a force recheck on the torrent to ensure that the files are not corrupted on your end. ',
                'Please be as thorough as possible and include as much detail as you can. Identify which tracks have problems, and their nature (silence, glitch, scrambled). Add a time position (mm:ss) if the problem is some distance from the beginning of the track. If the tracks are lossless, supply the output of <tt>flac -d &lt;file.flac&gt;</tt> if possible.',
                '<strong class="important_text">We will dismiss as incomplete a report that lacks specific information on the errors and you must state that 1) the download is complete, and 2) you have forced a recheck on the torrent.</strong>'
            ],
            'report_fields' => [
                'track' => '2'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]2.1.8[/rule]. Music not sourced from vinyl must not contain pops, clicks, or skips. They will be deleted for rip/encode errors if reported.
Your torrent was reported because one or more tracks contain encoding errors.'
            ]
        ],
        'rescore' => [
            'priority' => '160',
            'reason' => '-1',
            'title' => 'Log Rescore Request',
            'report_messages' => [
                "It helps to explain exactly why you believe this log requires rescoring. For example, if it's a foreign log which needs scoring, or if the log wasn't uploaded at all.",
                "For checksum rescores of existing logs, please include the output from the appropriate validator (EAC's CheckLog.exe or XLD's log checker) and the version used. The scores given on other sites are known to be subject to errors. As a consequence, <b>a log with no checksum warning on another site is not grounds for requesting a rescore if it has an invalid checksum warning on " . SITE_NAME . ".</b>",
            ],
            'report_fields' => [
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0',
                'pm' => '[rule]2.2.10.3[/rule]. A FLAC upload with an EAC or XLD rip log that scores 100% on the log checker replaces one with a lower score... . Note: A FLAC upload with a log that scores 95% for not defeating the audio cache may be rescored to 100% following the procedure outlined in [url=wiki.php?action=article&amp;id=79]this wiki[/url].
[rule]2.2.10.5[/rule]. XLD and EAC logs in languages other than English require a manual log checker score adjustment by staff.
[rule]2.2.10.6.2[/rule]. If you created a CD range rip that has matching CRCs for test and copy, and where every track has an AccurateRip score of 2 or more, then you may submit your torrent for manual score adjustment.
[rule]2.2.10.9.2[/rule]. If you find that an appended log has not been scored properly, please report the torrent and use the log rescore option.
Your torrent has now been properly scored by the staff.'
            ]
        ],
        'lossyapproval' => [
            'priority' => '161',
            'reason' => '-1',
            'title' => 'Lossy Master Approval Request',
            'report_messages' => [
                'Please include as much information as possible to verify the report, including spectral analysis images.',
                'For WEB purchases, please include a link to the webstore where you obtained the album and a screenshot of your invoice.',
                'For CDs or other physical media, please include a photograph of the album next to a piece of paper with your username written on it.',
                '<strong class="important_text">Anything included in the proof images field will only be viewable by staff.</strong>'
            ],
            'report_fields' => [
                'proofimages' => '2'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '0'
            ]
        ],
    ],

    '2' => [ //Applications Rules Broken
        'missing_crack' => [
            'priority' => '70',
            'reason' => '-1',
            'title' => 'No Crack/Keygen/Patch',
            'report_messages' => [
                'Please include as much information as possible to verify the report.',
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]4.1.2[/rule]. All applications must come with a crack, keygen, or other method of ensuring that downloaders can install them easily. App torrents with keygens, cracks, or patches that do not work or torrents missing clear installation instructions will be deleted if reported. No exceptions.
Your torrent was reported because it was missing an installation method.'
            ]
        ],
        'game' => [
            'priority' => '50',
            'reason' => '-1',
            'title' => 'Game',
            'report_messages' => [
                'Please include as much information as possible to verify the report.',
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '4',
                'delete' => '1',
                'pm' => '[rule]1.2.5[/rule]. Games of any kind. No games of any kind for PC, Mac, Linux, mobile devices, or any other platform are allowed.
[rule]4.1.7[/rule]. Games of any kind are prohibited (see [rule]1.2.5[/rule]).
Your torrent was reported because it contained a game disc rip.'
            ]
        ],
        'free' => [
            'priority' => '40',
            'reason' => '-1',
            'title' => 'Freely Available',
            'report_messages' => [
                'Please include a link to a source of information or to the freely available app itself.',
            ],
            'report_fields' => [
                'link' => '1'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]4.1.3[/rule]. App releases must not be freely available tools. Application releases cannot be freely downloaded anywhere from any official source. Nor may you upload open source applications where the source code is available for free.
Your torrent was reported because it contained a freely available application.'
            ]
        ],
        'description' => [
            'priority' => '80',
            'reason' => '-1',
            'title' => 'No Description',
            'report_messages' => [
                'If possible, please provide a link to an accurate description.',
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]4.1.4[/rule]. Release descriptions for applications must contain good information about the application. You should either have a small description of the program (either taken from its web site or from an NFO file) or a link to the information&#8202;&mdash;&#8202;but ideally both. Torrents missing this information will be deleted when reported.
Your torrent was reported because it lacked adequate release information.'
            ]
        ],
        'pack' => [
            'priority' => '20',
            'reason' => '-1',
            'title' => 'Archived Pack',
            'report_messages' => [
                'Please include as much information as possible to verify the report.'
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]2.1.18[/rule]. Sound Sample Packs must be uploaded as applications.
[rule]4.1.9[/rule]. Sound sample packs, template collections, and font collections are allowed if they are official releases, not freely available, and unarchived. Sound sample packs, template collections, and font collections must be official compilations and they must not be uploaded as an archive. The files contained inside the torrent must not be archived so that users can see what the pack contains. That means if sound sample packs are in WAV format, they must be uploaded as WAV. If the font collection, template collection, or sound sample pack was originally released as an archive, you must unpack the files before uploading them in a torrent. None of the contents in these packs and collections may be freely available.
Your torrent was reported because it was an archived collection.'
            ]
        ],
        'collection' => [
            'priority' => '30',
            'reason' => '-1',
            'title' => 'Collection of Cracks',
            'report_messages' => [
                'Please include as much information as possible to verify the report.'
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]4.1.11[/rule]. Collections of cracks, keygens or serials are not allowed. The crack, keygen, or serial for an application must be in a torrent with its corresponding application. It cannot be uploaded separately from the application.
Your torrent was reported because it contained a collection of serials, keygens, or cracks.'
            ]
        ],
        'hack' => [
            'priority' => '60',
            'reason' => '-1',
            'title' => 'Hacking Tool',
            'report_messages' => [
                'Please include as much information as possible to verify the report.',
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]4.1.12[/rule]. Torrents containing hacking or cracking tools are prohibited.
Your torrent was reported because it contained a hacking tool.'
            ]
        ],
        'virus' => [
            'priority' => '60',
            'reason' => '-1',
            'title' => 'Contains Virus',
            'report_messages' => [
                'Please include as much information as possible to verify the report. Please also double-check that your virus scanner is not incorrectly identifying a keygen or crack as a virus.',
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]4.1.14[/rule]. All applications must be complete.
The torrent was determined to be infected with a virus or trojan. In the future, please scan all potential uploads with an antivirus program such as AVG, Avast, or MS Security Essentials.
Your torrent was reported because it contained a virus or trojan.'
            ]
        ],
        'notwork' => [
            'priority' => '60',
            'reason' => '-1',
            'title' => 'Not Working',
            'report_messages' => [
                'Please include as much information as possible to verify the report.',
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]4.1.14[/rule]. All applications must be complete.
This program was determined to be not fully functional.
Your torrent was reported because it contained a program that did not work or no longer works.'
            ]
        ]
    ],

    '3' => [ //Ebook Rules Broken
        'unrelated' => [
            'priority' => '270',
            'reason' => '-1',
            'title' => 'Ebook Collection',
            'report_messages' => [
                'Please include as much information as possible to verify the report.'
            ],
            'report_fields' => [
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]6.5[/rule]. Collections/packs of ebooks are prohibited, even if each title is somehow related to other ebook titles in some way. All ebooks must be uploaded individually and cannot be archived (users must be able to see the ebook format in the torrent).
Your torrent was reported because it contained a collection or pack of ebooks.'
            ]
        ]
    ],

    '4' => [ //Audiobook Rules Broken
        'skips' => [
            'priority' => '210',
            'reason' => '13',
            'title' => 'Skips / Encode Errors',
            'report_messages' => [
                '<strong class="important_text">Please be as thorough as possible and include as much detail as you can. Refer to specific tracks and time positions to justify your report.</strong>'
            ],
            'report_fields' => [
                'track' => '2'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '0',
                'delete' => '1',
                'pm' => '[rule]2.1.8[/rule]. Music not sourced from vinyl must not contain pops, clicks, or skips. They will be deleted for rip/encode errors if reported.
Your torrent was reported because one or more audiobook tracks contain encoding errors.'
            ]
        ]
    ],

    '5' => [ //E-Learning videos Rules Broken
        'dissallowed' => [
            'priority' => '20',
            'reason' => '-1',
            'title' => 'Disallowed Topic',
            'report_messages' => [
                'Please include as much information as possible to verify the report.'
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]7.3[/rule]. Tutorials on how to use musical instruments, vocal training, producing music, or otherwise learning the theory and practice of music are the only allowed topics. No material outside of these topics is allowed. For example, instruction videos about Kung Fu training, dance lessons, beer brewing, or photography are not permitted here. What is considered allowable under these topics is ultimately at the discretion of the staff.
Your torrent was reported because it contained a video that has no relevance to the allowed music-related topics on the site.'
            ]
        ]
    ],

    '6' => [ //Comedy Rules Broken
        'talkshow' => [
            'priority' => '270',
            'reason' => '-1',
            'title' => 'Talkshow/Podcast',
            'report_messages' => [
                'Please include as much information as possible to verify the report.'
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '1',
                'delete' => '1',
                'pm' => '[rule]3.3[/rule]. No radio talk shows or podcasts are allowed. Those recordings do not belong in any torrent category.
Your torrent was reported because it contained audio files sourced from a talk show or podcast.'

            ]
        ]
    ],

    '7' => [ //Comics Rules Broken
        'titles' => [
            'priority' => '180',
            'reason' => '-1',
            'title' => 'Multiple Comic Titles',
            'report_messages' => [
                'Please include as much information as possible to verify the report.'
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '',
                'delete' => '1',
                'pm' => '[rule]5.2.3[/rule]. Collections may not span more than one comic title. You may not include multiple, different comic titles in a single collection, e.g., "The Amazing Spider-Man #1" and "The Incredible Hulk #1."
Your torrent was reported because it contained comics from multiple unrelated series.'
            ]
        ],
        'volumes' => [
            'priority' => '190',
            'reason' => '-1',
            'title' => 'Multiple Volumes',
            'report_messages' => [
                'Please include as much information as possible to verify the report.'
            ],
            'report_fields' => [
                'link' => '0'
            ],
            'resolve_options' => [
                'upload' => '0',
                'warn' => '',
                'delete' => '1',
                'pm' => '[rule]5.2.6[/rule]. Torrents spanning multiple volumes are too large and must be uploaded as separate volumes.
Your torrent was reported because it contained multiple comic volumes.'
            ]
        ]
    ]
];
