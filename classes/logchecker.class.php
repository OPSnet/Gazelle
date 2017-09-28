<?
/*******************************************************************
 * Automated EAC/XLD/dBpoweramp log checker (V4) *
 * OCT 2013 ~ robotnik *
 *-----------------------------------------------------------------*
 * MAJOR CHANGES V4:
 * - dBpoweramp parsing included (optional)
 * - Removed unimportant, redundant entries for validation report
 * - Scoring for combined logs balanced to resemble multiple single log submissions
 * - Addition of id3 tags will trigger notice instead of deduction
 ********************************************************************/
class LOG_CHECKER
{
	var $Log = '';
	var $Logs = array();
	var $Tracks = array();
	var $Score = 100;
	var $Good = array();
	var $Bad = array();
	var $Offsets = array();
	var $DriveFound = false;
	var $Drives = array();
	var $Drive = null;
	var $SecureMode = true;
	var $NonSecureMode = null;
	var $BadTrack = array();
	var $DecreaseScoreTrack = 0;
	var $RIPPER = null;
	var $VERSION = null;
	var $TrackNumber = null;
	var $ARTracks = array();
	var $Combined = null;
	var $CurrLog = null;
	var $DecreaseBoost = 0;
	var $Range = null;
	var $ARSummary = null;
	var $XLDSecureRipper = false;
	var $Limit = 15; //display low prior msg up to this count
	var $ProcessDBpoweramp = true;
	//http://www.dbpoweramp.com/Version-Changes-DMC.htm
	//don't set safe to anything below 12! latest major revision is assumed best
	var $DBAVersionCheck = array('safe' => 12, 'best' => 14);
	var $LBA = array();
	var $FrameReRipConf = array();
	var $IARTracks = array();
	var $InvalidateCache = true;
	var $DubiousTracks = 0;
	function new_file($Log)
	{
		$this->Log = $Log;
	}
	function parse()
	{
		$Log	   = display_str($this->Log);
		$Log	   = str_replace("\r\n", "\n", $Log);
		$Log	   = str_replace("\r", '', $Log);
		$this->Log = $Log;
		if (preg_match("/^dBpoweramp /i", $Log) && $this->ProcessDBpoweramp) {
			$this->RIPPER = "DBPA";
			$this->parse_dBpoweramp();
			return array(
				$this->Score,
				$this->Good,
				$this->Bad,
				$this->Log
			);
		}
		$Checksum = false;
		if (preg_match("/[\=]+\s+Log checksum/i", $Log)) { // eac checksum
			$Checksum   = true;
			$this->Logs = preg_split("/(\n\=+\s+Log checksum.*)/i", $Log, -1, PREG_SPLIT_DELIM_CAPTURE);
		} elseif (preg_match("/[\-]+BEGIN XLD SIGNATURE[\S\n\-]+END XLD SIGNATURE[\-]+/i", $Log)) { // xld checksum (plugin)
			$Checksum   = true;
			$this->Logs = preg_split("/(\n[\-]+BEGIN XLD SIGNATURE[\S\n\-]+END XLD SIGNATURE[\-]+)/i", $Log, -1, PREG_SPLIT_DELIM_CAPTURE);
		} else { //no checksum
			$this->Logs = preg_split("/(\nEnd of status report)/i", $Log, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($this->Logs as $key => $value) {
                if (preg_match("/---- CUETools DB Plugin V.+/i", $value)) {
                    unset($this->Logs[$key]);
                } //strip empty
            }
		}

		foreach ($this->Logs as $key => $value) {
			if (trim($value) == "") {
				unset($this->Logs[$key]);
			} //strip empty
			//append stat msgs
			elseif (!$Checksum && preg_match("/\nEnd of status report/i", $value)) {
				$this->Logs[$key - 1] .= $value;
				unset($this->Logs[$key]);
			} elseif ($Checksum && preg_match("/[\=]+\s+Log checksum/i", $value)) {
				$this->Logs[$key - 1] .= $value;
				unset($this->Logs[$key]);
			} elseif ($Checksum && preg_match("/[\-]+BEGIN XLD SIGNATURE/i", $value)) {
				$this->Logs[$key - 1] .= $value;
				unset($this->Logs[$key]);
			}
		}

		$this->Logs = array_values($this->Logs); //rebuild index
		if (count($this->Logs) > 1) {
			$this->Combined = count($this->Logs);
		} //is_combined
		foreach ($this->Logs as $LogArrayKey => $Log) {
			$this->CurrLog = $LogArrayKey + 1;
			$CurrScore	 = $this->Score;
			$Log		   = preg_replace('/(\=+\s+Log checksum.*)/i', '<span class="good">$1</span>', $Log, 1, $Count);
			if (preg_match('/Exact Audio Copy (.+) from/i', $Log, $Matches)) { //eac v1 & checksum
				if ($Matches[1] && is_numeric(substr($Matches[1], 1, 1)) && substr($Matches[1], 1, 1) > 0 && !$Count) {
					$this->account('No checksum appended', false, false, true, true);
				}
			}
			$Log = preg_replace('/([\-]+BEGIN XLD SIGNATURE[\S\n\-]+END XLD SIGNATURE[\-]+)/i', '<span class="good">$1</span>', $Log, 1, $Count);
			if (preg_match('/X Lossless Decoder version (\d+) \((.+)\)/i', $Log, $Matches)) { //xld version & checksum
				$this->VERSION = $Matches[1];
				if ($this->VERSION > 20130000 && !$Count) { // 2013++
					$this->account('No checksum appended', false, false, true, true);
				}
			}
			$Log = preg_replace('/Exact Audio Copy (.+) from (.+)/i', 'Exact Audio Copy <span class="log1">$1</span> from <span class="log1">$2</span>', $Log, 1, $Count);
			$Log = preg_replace("/EAC extraction logfile from (.+)\n+(.+)/i", "<span class=\"good\">EAC extraction logfile from <span class=\"log5\">$1</span></span>\n\n<span class=\"log4\">$2</span>", $Log, 1, $EAC);
			$Log = preg_replace("/X Lossless Decoder version (.+) \((.+)\)/i", "X Lossless Decoder version <span class=\"log1\">$1</span> (<span class=\"log1\">$2</span>)", $Log, 1, $Count);
			$Log = preg_replace("/XLD extraction logfile from (.+)\n+(.+)/i", "<span class=\"good\">XLD extraction logfile from <span class=\"log5\">$1</span></span>\n\n<span class=\"log4\">$2</span>", $Log, 1, $XLD);
			if (!$EAC && !$XLD) {
				if ($this->Combined) {
					unset($this->Bad);
					$this->Bad[] = "Combined Log (" . $this->Combined . ")";
					$this->Bad[] = "Unrecognized log file (" . $this->CurrLog . ")! Feel free to report for manual review.";
				} else {
					$this->Bad[] = "Unrecognized log file! Feel free to report for manual review.";
				}
				$this->Score = 1;
				return array(
					$this->Score,
					$this->Good,
					$this->Bad,
					$this->Log
				);
			} else {
				$this->RIPPER = ($EAC) ? "EAC" : "XLD";
			}
			$Log = preg_replace_callback("/Used drive( +): (.+)/i", array(
				$this,
				'drive'
			), $Log, 1, $Count);
			if (!$Count) {
				$this->account('Could not verify used drive', 1);
			}
			$Log = preg_replace_callback("/Media type( +): (.+)/i", array(
				$this,
				'media_type_xld'
			), $Log, 1, $Count);
			if ($XLD && $this->VERSION && $this->VERSION >= 20130127 && !$Count) {
				$this->account('Could not verify media type', 1);
			}
			$Log = preg_replace_callback('/Read mode( +): ([a-z]+)(.*)?/i', array(
				$this,
				'read_mode'
			), $Log, 1, $Count);
			if (!$Count && $EAC) {
				$this->account('Could not verify read mode', 1);
			}
			$Log = preg_replace_callback('/Ripper mode( +): (.*)/i', array(
				$this,
				'ripper_mode_xld'
			), $Log, 1, $XLDRipperMode);
			$Log = preg_replace_callback('/Use cdparanoia mode( +): (.*)/i', array(
				$this,
				'cdparanoia_mode_xld'
			), $Log, 1, $XLDCDParanoiaMode);
			if (!$XLDRipperMode && !$XLDCDParanoiaMode && $XLD) {
				$this->account('Could not verify read mode', 1);
			}
			$Log = preg_replace_callback('/Max retry count( +): (\d+)/i', array(
				$this,
				'max_retry_count'
			), $Log, 1, $Count);
			if (!$Count && $XLD) {
				$this->account('Could not verify max retry count');
			}
			$Log = preg_replace_callback('/Utilize accurate stream( +): (Yes|No)/i', array(
				$this,
				'accurate_stream'
			), $Log, 1, $EAC_ac_stream);
			$Log = preg_replace_callback('/, (|NO )accurate stream/i', array(
				$this,
				'accurate_stream_eac_pre99'
			), $Log, 1, $EAC_ac_stream_pre99);
			if (!$EAC_ac_stream && !$EAC_ac_stream_pre99 && !$this->NonSecureMode && $EAC) {
				$this->account('Could not verify accurate stream');
			}
			$Log = preg_replace_callback('/Defeat audio cache( +): (Yes|No)/i', array(
				$this,
				'defeat_audio_cache'
			), $Log, 1, $EAC_defeat_cache);
			$Log = preg_replace_callback('/ (|NO )disable cache/i', array(
				$this,
				'defeat_audio_cache_eac_pre99'
			), $Log, 1, $EAC_defeat_cache_pre99);
			if (!$EAC_defeat_cache && !$EAC_defeat_cache_pre99 && !$this->NonSecureMode && $EAC) {
				$this->account('Could not verify defeat audio cache', 1);
			}
			$Log = preg_replace_callback('/Disable audio cache( +): (.*)/i', array(
				$this,
				'defeat_audio_cache_xld'
			), $Log, 1, $Count);
			if (!$Count && $XLD) {
				$this->account('Could not verify defeat audio cache', 1);
			}
			$Log = preg_replace_callback('/Make use of C2 pointers( +): (Yes|No)/i', array(
				$this,
				'c2_pointers'
			), $Log, 1, $C2);
			$Log = preg_replace_callback('/with (|NO )C2/i', array(
				$this,
				'c2_pointers_eac_pre99'
			), $Log, 1, $C2_EACpre99);
			if (!$C2 && !$C2_EACpre99 && !$this->NonSecureMode) {
				$this->account('Could not verify C2 pointers', 1);
			}
			$Log = preg_replace_callback('/Read offset correction( +): ([+-]?[0-9]+)/i', array(
				$this,
				'read_offset'
			), $Log, 1, $Count);
			if (!$Count) {
				$this->account('Could not verify read offset', 1);
			}
			$Log = preg_replace("/(Combined read\/write offset correction\s+:\s+\d+)/i", "<span class=\"bad\">$1</span>", $Log, 1, $Count);
			if ($Count) {
				$this->account('Combined read/write offset cannot be verified', 4, false, false, false, 4);
			}
			//xld alternate offset table
			$Log = preg_replace("/(List of \w+ offset correction values) *(\n+)(( *.*confidence .*\) ?\n)+)/i", "<span class=\"log5\">$1</span>$2<span class=\"log4\">$3</span>\n", $Log, 1, $Count);
			$Log = preg_replace("/(List of \w+ offset correction values) *\n( *\# +\| +Absolute +\| +Relative +\| +Confidence) *\n( *\-+) *\n(( *\d+ +\| +\-?\+?\d+ +\| +\-?\+?\d+ +\| +\d+ *\n)+)/i", "<span class=\"log5\">$1</span>\n<span class=\"log4\">$2\n$3\n$4\n</span>", $Log, 1, $Count);
			$Log = preg_replace('/Overread into Lead-In and Lead-Out( +): (Yes|No)/i', '<span class="log5">Overread into Lead-In and Lead-Out$1</span>: <span class="log4">$2</span>', $Log, 1, $Count);
			$Log = preg_replace_callback('/Fill up missing offset samples with silence( +): (Yes|No)/i', array(
				$this,
				'fill_offset_samples'
			), $Log, 1, $Count);
			if (!$Count && $EAC) {
				$this->account('Could not verify missing offset samples', 1);
			}
			$Log = preg_replace_callback('/Delete leading and trailing silent blocks[ \w]*( +): (Yes|No)/i', array(
				$this,
				'delete_silent_blocks'
			), $Log, 1, $Count);
			if (!$Count && $EAC) {
				$this->account('Could not verify silent blocks', 1);
			}
			$Log = preg_replace_callback('/Null samples used in CRC calculations( +): (Yes|No)/i', array(
				$this,
				'null_samples'
			), $Log, 1, $Count);
			if (!$Count && $EAC) {
				$this->account('Could not verify null samples');
			}
			$Log = preg_replace('/Used interface( +): ([^\n]+)/i', '<span class="log5">Used interface$1</span>: <span class="log4">$2</span>', $Log, 1, $Count);
			$Log = preg_replace_callback('/Gap handling( +): ([^\n]+)/i', array(
				$this,
				'gap_handling'
			), $Log, 1, $Count);
			if (!$Count && $EAC) {
				$this->account('Could not verify gap handling');
			}
			$Log = preg_replace_callback('/Gap status( +): (.*)/i', array(
				$this,
				'gap_handling_xld'
			), $Log, 1, $Count);
			if (!$Count && $XLD) {
				$this->account('Could not verify gap status');
			}
			$Log = preg_replace('/Used output format( +): ([^\n]+)/i', '<span class="log5">Used output format$1</span>: <span class="log4">$2</span>', $Log, 1, $Count);
			$Log = preg_replace('/Sample format( +): ([^\n]+)/i', '<span class="log5">Sample format$1</span>: <span class="log4">$2</span>', $Log, 1, $Count);
			$Log = preg_replace('/Selected bitrate( +): ([^\n]+)/i', '<span class="log5">Selected bitrate$1</span>: <span class="log4">$2</span>', $Log, 1, $Count);
			$Log = preg_replace('/( +)(\d+ kBit\/s)/i', '<span>$1</span><span class="log4">$2</span>', $Log, 1, $Count);
			$Log = preg_replace('/Quality( +): ([^\n]+)/i', '<span class="log5">Quality$1</span>: <span class="log4">$2</span>', $Log, 1, $Count);
			$Log = preg_replace_callback('/Add ID3 tag( +): (Yes|No)/i', array(
				$this,
				'add_id3_tag'
			), $Log, 1, $Count);
			if (!$Count && $EAC) {
				$this->account('Could not verify id3 tag setting');
			}
			$Log = preg_replace("/(Use compression offset\s+:\s+\d+)/i", "<span class=\"bad\">$1</span>", $Log, 1, $Count);
			if ($Count) {
				$this->account('Ripped with compression offset', false, 0);
			}
			$Log = preg_replace('/Command line compressor( +): ([^\n]+)/i', '<span class="log5">Command line compressor$1</span>: <span class="log4">$2</span>', $Log, 1, $Count);
			$Log = preg_replace("/Additional command line options([^\n]{70,110} )/", "Additional command line options$1<br>", $Log);
			$Log = preg_replace('/( *)Additional command line options( +): (.+)\n/i', '<span class="log5">Additional command line options$2</span>: <span class="log4">$3</span>' . "\n", $Log, 1, $Count);
			// xld album gain
			$Log = preg_replace("/All Tracks\s*\n(\s*Album gain\s+:) (.*)?\n(\s*Peak\s+:) (.*)?/i", "<span class=\"log5\">All Tracks</span>\n<strong>$1 <span class=\"log3\">$2</span>\n$3 <span class=\"log3\">$4</span></strong>", $Log, 1, $Count);
			if (!$Count && $XLD) {
				$this->account('Could not verify album gain');
			}
			// pre-0.99
			$Log = preg_replace('/Other options( +):/i', '<span class="log5">Other options$1</span>:', $Log, 1, $Count);
			$Log = preg_replace('/\n( *)Native Win32 interface(.+)/i', "\n$1<span class=\"log4\">Native Win32 interface$2</span>", $Log, 1, $Count);
			// 0.99
			$Log = str_replace('TOC of the extracted CD', '<span class="log4 log5">TOC of the extracted CD</span>', $Log);
			$Log = preg_replace('/( +)Track( +)\|( +)Start( +)\|( +)Length( +)\|( +)Start sector( +)\|( +)End sector( ?)/i', '<strong>$0</strong>', $Log);
			$Log = preg_replace('/-{10,100}/', '<strong>$0</strong>', $Log);
			$Log = preg_replace_callback('/( +)([0-9]{1,3})( +)\|( +)(([0-9]{1,3}:)?[0-9]{2}[\.:][0-9]{2})( +)\|( +)(([0-9]{1,3}:)?[0-9]{2}[\.:][0-9]{2})( +)\|( +)([0-9]{1,10})( +)\|( +)([0-9]{1,10})( +)\n/i', array(
				$this,
				'toc'
			), $Log);
			$Log = str_replace('None of the tracks are present in the AccurateRip database', '<span class="badish">None of the tracks are present in the AccurateRip database</span>', $Log);
			$Log = str_replace('Disc not found in AccurateRip DB.', '<span class="badish">Disc not found in AccurateRip DB.</span>', $Log);
			$Log = preg_replace('/No errors occurr?ed/i', '<span class="good">No errors occurred</span>', $Log);
			$Log = preg_replace("/(There were errors) ?\n/i", "<span class=\"bad\">$1</span>\n", $Log);
			$Log = preg_replace("/(Some inconsistencies found) ?\n/i", "<span class=\"badish\">$1</span>\n", $Log);
			$Log = preg_replace('/End of status report/i', '<span class="good">End of status report</span>', $Log);
			$Log = preg_replace('/Track(\s*)Ripping Status(\s*)\[Disc ID: ([0-9a-f]{8}-[0-9a-f]{8})\]/i', '<strong>Track</strong>$1<strong>Ripping Status</strong>$2<strong>Disc ID: </strong><span class="log1">$3</span>', $Log);
			$Log = preg_replace('/(All Tracks Accurately Ripped\.?)/i', '<span class="good">$1</span>', $Log);
			$Log = preg_replace("/\d+ track.* +accurately ripped\.? *\n/i", '<span class="good">$0</span>', $Log);
			$Log = preg_replace("/\d+ track.* +not present in the AccurateRip database\.? *\n/i", '<span class="badish">$0</span>', $Log);
			$Log = preg_replace("/\d+ track.* +canceled\.? *\n/i", '<span class="bad">$0</span>', $Log);
			$Log = preg_replace("/\d+ track.* +could not be verified as accurate\.? *\n/i", '<span class="badish">$0</span>', $Log);
			$Log = preg_replace("/Some tracks could not be verified as accurate\.? *\n/i", '<span class="badish">$0</span>', $Log);
			$Log = preg_replace("/No tracks could be verified as accurate\.? *\n/i", '<span class="badish">$0</span>', $Log);
			$Log = preg_replace("/You may have a different pressing.*\n/i", '<span class="goodish">$0</span>', $Log);
			//xld accurip summary
			$Log = preg_replace_callback("/(Track +\d+ +: +)(OK +)\(A?R?\d?,? ?confidence +(\d+).*?\)(.*)\n/i", array(
				$this,
				'ar_summary_conf_xld'
			), $Log);
			$Log = preg_replace_callback("/(Track +\d+ +: +)(NG|Not Found).*?\n/i", array(
				$this,
				'ar_summary_conf_xld'
			), $Log);
			$Log = preg_replace( //Status line
				"/( *.{2} ?)(\d+ track\(s\).*)\n/i", "$1<span class=\"log4\">$2</span>\n", $Log, 1);
			//(..) may need additional entries
			//accurip summary (range)
			$Log = preg_replace("/\n( *AccurateRip summary\.?)/i", "\n<span class=\"log4 log5\">$1</span>", $Log);
			$Log = preg_replace_callback("/(Track +\d+ +.*?accurately ripped\.? *)(\(confidence +)(\d+)\)(.*)\n/i", array(
				$this,
				'ar_summary_conf'
			), $Log);
			$Log = preg_replace("/(Track +\d+ +.*?in database *)\n/i", "<span class=\"badish\">$1</span>\n", $Log, -1, $Count);
			if ($Count) {
				$this->ARSummary['bad'] = $Count;
			}
			$Log = preg_replace("/(Track +\d+ +.*?(could not|cannot) be verified as accurate.*)\n/i", "<span class=\"badish\">$1</span>\n", $Log, -1, $Count);
			if ($Count) {
				$this->ARSummary['bad'] = $Count;
			} //don't mind the actual count
			//range rip
			$Log = preg_replace("/\n( *Selected range)/i", "\n<span class=\"bad\">$1</span>", $Log, 1, $Range1);
			$Log = preg_replace('/\n( *Range status and errors)/i', "\n<span class=\"bad\">$1</span>", $Log, 1, $Range2);
			if ($Range1 || $Range2) {
				$this->Range = 1;
				$this->account('Range rip detected', 30);
			}
			$FormattedTrackListing = '';
			//------ Handle individual tracks ------//
			if (!$this->Range) {
				preg_match('/\nTrack( +)([0-9]{1,3})([^<]+)/i', $Log, $Matches);
				$TrackListing = $Matches[0];
				$FullTracks   = preg_split('/\nTrack( +)([0-9]{1,3})/i', $TrackListing, -1, PREG_SPLIT_DELIM_CAPTURE);
				array_shift($FullTracks);
				$TrackBodies = preg_split('/\nTrack( +)([0-9]{1,3})/i', $TrackListing, -1);
				array_shift($TrackBodies);
				//------ Range rip ------//
			} else {
				preg_match('/\n( +)Filename +(.*)([^<]+)/i', $Log, $Matches);
				$TrackListing = $Matches[0];
				$FullTracks   = preg_split('/\n( +)Filename +(.*)/i', $TrackListing, -1, PREG_SPLIT_DELIM_CAPTURE);
				array_shift($FullTracks);
				$TrackBodies = preg_split('/\n( +)Filename +(.*)/i', $TrackListing, -1);
				array_shift($TrackBodies);
			}
			$Tracks = array();
			while (list($Key, $TrackBody) = each($TrackBodies)) {
				// The number of spaces between 'Track' and the number, to keep formatting intact
				$Spaces				   = $FullTracks[($Key * 3)];
				// Track number
				$TrackNumber			  = $FullTracks[($Key * 3) + 1];
				$this->TrackNumber		= $TrackNumber;
				// How much to decrease the overall score by, if this track fails and no attempt at recovery is made later on
				$this->DecreaseScoreTrack = 0;
				// List of things that went wrong to add to $this->Bad if this track fails and no attempt at recovery is made later on
				$this->BadTrack		   = array();
				// The track number is stripped in the preg_split, let's bring it back, eh?
				if (!$this->Range) {
					$TrackBody = '<span class="log5">Track</span>' . $Spaces . '<span class="log4 log1">' . $TrackNumber . '</span>' . $TrackBody;
				} else {
					$TrackBody = $Spaces . '<span class="log5">Filename</span> <span class="log4 log3">' . $TrackNumber . '</span>' . $TrackBody;
				}
				$TrackBody = preg_replace('/Filename ((.+)?\.(wav|flac|ape))\n/is', /* match newline for xld multifile encodes */ "<span class=\"log4\">Filename <span class=\"log3\">$1</span></span>\n", $TrackBody, -1, $Count);
				if (!$Count && !$this->Range) {
					$this->account_track('Could not verify filename', 1);
				}
				// xld track gain
				$TrackBody = preg_replace("/( *Track gain\s+:) (.*)?\n(\s*Peak\s+:) (.*)?/i", "<strong>$1 <span class=\"log3\">$2</span>\n$3 <span class=\"log3\">$4</span></strong>", $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/( +)(Statistics *)\n/i', "$1<span class=\"log5\">$2</span>\n", $TrackBody, -1, $Count);
				$TrackBody = preg_replace_callback('/(Read error)( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD) {
					$this->account_track('Could not verify read errors');
				}
				$TrackBody = preg_replace_callback('/(Skipped \(treated as error\))( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && !$this->XLDSecureRipper) {
					$this->account_track('Could not verify skipped errors');
				}
				$TrackBody = preg_replace_callback('/(Edge jitter error \(maybe fixed\))( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && !$this->XLDSecureRipper) {
					$this->account_track('Could not verify edge jitter errors');
				}
				$TrackBody = preg_replace_callback('/(Atom jitter error \(maybe fixed\))( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && !$this->XLDSecureRipper) {
					$this->account_track('Could not verify atom jitter errors');
				}
				$TrackBody = preg_replace_callback( //xld secure ripper
					'/(Jitter error \(maybe fixed\))( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && $this->XLDSecureRipper) {
					$this->account_track('Could not verify jitter errors');
				}
				$TrackBody = preg_replace_callback( //xld secure ripper
					'/(Retry sector count)( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && $this->XLDSecureRipper) {
					$this->account_track('Could not verify retry sector count');
				}
				$TrackBody = preg_replace_callback( //xld secure ripper
					'/(Damaged sector count)( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && $this->XLDSecureRipper) {
					$this->account_track('Could not verify damaged sector count');
				}
				$TrackBody = preg_replace_callback('/(Drift error \(maybe fixed\))( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && !$this->XLDSecureRipper) {
					$this->account_track('Could not verify drift errors');
				}
				$TrackBody = preg_replace_callback('/(Dropped bytes error \(maybe fixed\))( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && !$this->XLDSecureRipper) {
					$this->account_track('Could not verify dropped bytes errors');
				}
				$TrackBody = preg_replace_callback('/(Duplicated bytes error \(maybe fixed\))( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && !$this->XLDSecureRipper) {
					$this->account_track('Could not verify duplicated bytes errors');
				}
				$TrackBody = preg_replace_callback('/(Inconsistency in error sectors)( +:) (\d+)/i', array(
					$this,
					'xld_stat'
				), $TrackBody, -1, $Count);
				if (!$Count && $XLD && !$this->XLDSecureRipper) {
					$this->account_track('Could not verify inconsistent error sectors');
				}
				$TrackBody = preg_replace("/(List of suspicious positions +)(: *\n?)(( *.* +\d{2}:\d{2}:\d{2} *\n)+)/i", '<span class="bad">$1</span><strong>$2</strong><span class="bad">$3</span></span>', $TrackBody, -1, $Count);
				if ($Count) {
					$this->account_track('Suspicious position(s) found', 20);
				}
				$TrackBody = preg_replace('/Suspicious position( +)([0-9]:[0-9]{2}:[0-9]{2})/i', '<span class="bad">Suspicious position$1<span class="log4">$2</span></span>', $TrackBody, -1, $Count);
				if ($Count) {
					$this->account_track('Suspicious position(s) found', 20);
				}
				$TrackBody = preg_replace('/Timing problem( +)([0-9]:[0-9]{2}:[0-9]{2})/i', '<span class="bad">Timing problem$1<span class="log4">$2</span></span>', $TrackBody, -1, $Count);
				if ($Count) {
					$this->account_track('Timing problem(s) found', 20);
				}
				$TrackBody = preg_replace('/Missing samples/i', '<span class="bad">Missing samples</span>', $TrackBody, -1, $Count);
				if ($Count) {
					$this->account_track('Missing sample(s) found', 20);
				}
				$TrackBody = preg_replace('/Copy aborted/i', '<span class="bad">Copy aborted</span>', $TrackBody, -1, $Count);
				if ($Count) {
					$Aborted = true;
					$this->account_track('Copy aborted', 100);
				} else {
					$Aborted = false;
				}
				$TrackBody = preg_replace('/Pre-gap length( +|\s+:\s+)([0-9]{1,2}:[0-9]{2}:[0-9]{2}.?[0-9]{0,2})/i', '<span class="log4">Pre-gap length$1<span class="log3">$2</span></span>', $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/Peak level ([0-9]{1,3}\.[0-9] %)/i', '<span class="log4">Peak level <span class="log3">$1</span></span>', $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/Extraction speed ([0-9]{1,3}\.[0-9]{1,} X)/i', '<span class="log4">Extraction speed <span class="log3">$1</span></span>', $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/Track quality ([0-9]{1,3}\.[0-9] %)/i', '<span class="log4">Track quality <span class="log3">$1</span></span>', $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/Range quality ([0-9]{1,3}\.[0-9] %)/i', '<span class="log4">Range quality <span class="log3">$1</span></span>', $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/CRC32 hash \(skip zero\)(\s*:) ([0-9A-F]{8})/i', '<span class="log4">CRC32 hash (skip zero)$1<span class="log3"> $2</span></span>', $TrackBody, -1, $Count);
				$TrackBody = preg_replace_callback('/Test CRC ([0-9A-F]{8})\n(\s*)Copy CRC ([0-9A-F]{8})/i', array(
					$this,
					'test_copy'
				), $TrackBody, -1, $EACTC);
				$TrackBody = preg_replace_callback('/CRC32 hash \(test run\)(\s*:) ([0-9A-F]{8})\n(\s*)CRC32 hash(\s+:) ([0-9A-F]{8})/i', array(
					$this,
					'test_copy'
				), $TrackBody, -1, $XLDTC);
				if (!$EACTC && !$XLDTC && !$Aborted) {
					$this->account('Test and copy was not used', 10);
					if (!$this->SecureMode) {
						if ($EAC) {
							$Msg = 'Rip was not done in Secure mode, and T+C was not used - as a result, we cannot verify the authenticity of the rip (-40 points)';
						} else if ($XLD) {
							$Msg = 'Rip was not done with Secure Ripper / in CDParanoia mode, and T+C was not used - as a result, we cannot verify the authenticity of the rip (-40 points)';
						}
						if (!in_array($Msg, $this->Bad)) {
							$this->Score -= 40;
							$this->Bad[] = $Msg;
						}
					}
				}
				$TrackBody = preg_replace('/Copy CRC ([0-9A-F]{8})/i', '<span class="log4">Copy CRC <span class="log3">$1</span></span>', $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/CRC32 hash(\s*:) ([0-9A-F]{8})/i', '<span class="log4">CRC32 hash$1<span class="goodish"> $2</span></span>', $TrackBody, -1, $Count);
				$TrackBody = str_replace('Track not present in AccurateRip database', '<span class="badish">Track not present in AccurateRip database</span>', $TrackBody);
				$TrackBody = preg_replace('/Accurately ripped( +)\(confidence ([0-9]+)\)( +)(\[[0-9A-F]{8}\])/i', '<span class="good">Accurately ripped$1(confidence $2)$3$4</span>', $TrackBody, -1, $Count);
				$TrackBody = preg_replace("/Cannot be verified as accurate +\(.*/i", '<span class="badish">$0</span>', $TrackBody, -1, $Count);
				//xld ar
				$TrackBody = preg_replace_callback('/AccurateRip signature( +): ([0-9A-F]{8})\n(.*?)(Accurately ripped\!?)( +\(A?R?\d?,? ?confidence )([0-9]+\))/i', array(
					$this,
					'ar_xld'
				), $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/AccurateRip signature( +): ([0-9A-F]{8})\n(.*?)(Rip may not be accurate\.?)(.*?)/i', "<span class=\"log4\">AccurateRip signature$1: <span class=\"badish\">$2</span></span>\n$3<span class=\"badish\">$4$5</span>", $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/(Rip may not be accurate\.?)(.*?)/i', "<span class=\"badish\">$1$2</span>", $TrackBody, -1, $Count);
				$TrackBody = preg_replace('/AccurateRip signature( +): ([0-9A-F]{8})\n(.*?)(Track not present in AccurateRip database\.?)(.*?)/i', "<span class=\"log4\">AccurateRip signature$1: <span class=\"badish\">$2</span></span>\n$3<span class=\"badish\">$4$5</span>", $TrackBody, -1, $Count);
				$TrackBody = preg_replace("/\(matched[ \w]+;\n *calculated[ \w]+;\n[ \w]+signature[ \w:]+\)/i", "<span class=\"goodish\">$0</span>", $TrackBody, -1, $Count);
				//ar track + conf
				preg_match('/Accurately ripped\!? +\(A?R?\d?,? ?confidence ([0-9]+)\)/i', $TrackBody, $matches);
				if ($matches) {
					$this->ARTracks[$TrackNumber] = $matches[1];
				} else {
					$this->ARTracks[$TrackNumber] = 0;
				} //no match - no boost
				$TrackBody			= str_replace('Copy finished', '<span class="log3">Copy finished</span>', $TrackBody);
				$TrackBody			= preg_replace('/Copy OK/i', '<span class="good">Copy OK</span>', $TrackBody, -1, $Count);
				$Tracks[$TrackNumber] = array(
					'number' => $TrackNumber,
					'spaces' => $Spaces,
					'text' => $TrackBody,
					'decreasescore' => $this->DecreaseScoreTrack,
					'bad' => $this->BadTrack
				);
				$FormattedTrackListing .= "\n" . $TrackBody;
				$this->Tracks[$TrackNumber] = $Tracks[$TrackNumber];
			}
			unset($Tracks);
			$Log					  = str_replace($TrackListing, $FormattedTrackListing, $Log);
			$Log					  = str_replace('<br>', "\n", $Log);
			//xld all tracks statistics
			$Log					  = preg_replace('/( +)?(All tracks *)\n/i', "$1<span class=\"log5\">$2</span>\n", $Log, 1);
			$Log					  = preg_replace('/( +)(Statistics *)\n/i', "$1<span class=\"log5\">$2</span>\n", $Log, 1);
			$Log					  = preg_replace_callback('/(Read error)( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			$Log					  = preg_replace_callback('/(Skipped \(treated as error\))( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			$Log					  = preg_replace_callback('/(Jitter error \(maybe fixed\))( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			$Log					  = preg_replace_callback('/(Edge jitter error \(maybe fixed\))( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			$Log					  = preg_replace_callback('/(Atom jitter error \(maybe fixed\))( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			$Log					  = preg_replace_callback('/(Drift error \(maybe fixed\))( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			$Log					  = preg_replace_callback('/(Dropped bytes error \(maybe fixed\))( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			$Log					  = preg_replace_callback('/(Duplicated bytes error \(maybe fixed\))( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			$Log					  = preg_replace_callback('/(Retry sector count)( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			$Log					  = preg_replace_callback('/(Damaged sector count)( +:) (\d+)/i', array(
				$this,
				'xld_all_stat'
			), $Log, 1);
			//end xld all tracks statistics
			$this->Logs[$LogArrayKey] = $Log;
			$this->check_tracks();
			foreach ($this->Tracks as $Track) { //send score/bad
				if ($Track['decreasescore']) {
					$this->Score -= $Track['decreasescore'];
				}
				if (count($Track['bad']) > 0) {
					$this->Bad = array_merge($this->Bad, $Track['bad']);
				}
			}
			unset($this->Tracks); //fixes weird bug
			if ($this->NonSecureMode) { #non-secure mode
				$this->account($this->NonSecureMode . ' mode was used', 2);
			}
			if ($this->Score != 100) { //boost?
				$boost   = null;
				$minConf = null;
				if (!$this->ARSummary) {
					foreach ($this->ARTracks as $Track => $Conf) {
						if (!is_numeric($Conf) || $Conf < 2) {
							$boost = 0;
							break;
						} //non-ar track found
						else {
							$boost   = 1;
							$minConf = (!$minConf || $Conf < $minConf) ? $Conf : $minConf;
						}
					}
				} elseif (isset($this->ARSummary['good'])) { //range with minConf
					foreach ($this->ARSummary['good'] as $Track => $Conf) {
						if (!is_numeric($Conf)) {
							$boost = 0;
							break;
						} else {
							$boost   = 1;
							$minConf = (!$minConf || $Conf < $minConf) ? $Conf : $minConf;
						}
					}
					if (isset($this->ARSummary['bad']) || isset($this->ARSummary['goodish'])) {
						$boost = 0;
					} //non-ar track found
				}
				if ($boost) {
					$tmp_score   = $this->Score;
					$this->Score = (($CurrScore) ? $CurrScore : 100) - $this->DecreaseBoost;
					if (((($CurrScore) ? $CurrScore : 100) - $tmp_score) != $this->DecreaseBoost) {
						$Msg		 = 'All tracks accurately ripped with at least confidence ' . $minConf . '. Score ' . (($this->Combined) ? "for log " . $this->CurrLog . " " : '') . 'boosted to ' . $this->Score . ' points!';
						$this->Bad[] = $Msg;
					}
				}
			}
			$this->ARTracks	  = array();
			$this->ARSummary	 = array();
			$this->DecreaseBoost = 0;
			$this->SecureMode	= true;
			$this->NonSecureMode = null;
		} //end log loop
		$this->Log   = implode($this->Logs);
		$this->Score = ($this->Score < 0) ? 0 : $this->Score; //min. score
		natcasesort($this->Bad); //sort ci
		$this->format_report();
		if ($this->Combined) {
			array_unshift($this->Bad, "Combined Log (" . $this->Combined . ")");
		} //combined log msg
		return array(
			$this->Score,
			$this->Good,
			$this->Bad,
			$this->Log
		);
	}
	// Callback functions
	function drive($Matches)
	{
		global $DB;
		$FakeDrives = array(
			'Generic DVD-ROM SCSI CdRom Device'
		);
		if (in_array(trim($Matches[2]), $FakeDrives)) {
			$this->account('Virtual drive used: ' . $Matches[2], 20, false, false, false, 20);
			return "<span class=\"log5\">Used Drive$Matches[1]</span>: <span class=\"bad\">$Matches[2]</span>";
		}
		$DriveName   = $Matches[2];
		$DriveName   = preg_replace('/\s+-\s/', ' ', $DriveName);
		$DriveName   = preg_replace('/\s+/', ' ', $DriveName);
		$DriveName   = preg_replace('/[^ ]+:.*$/', '', $DriveName);
		$this->Drive = $DriveName;
		$Search	  = preg_split('/[^0-9a-z]/i', trim($DriveName));
		$SearchText  = implode("%' AND Name LIKE '%", $Search);
		$DB->query("SELECT Offset,Name FROM drives WHERE Name LIKE '%" . $SearchText . "%'");
		$this->Drives  = $DB->collect('Name');
		$Offsets	   = array_unique($DB->collect('Offset'));
		$this->Offsets = $Offsets;
		while (list($Key, $Offset) = each($Offsets)) {
			$StrippedOffset  = preg_replace('/[^0-9]/s', '', $Offset);
			$this->Offsets[] = $StrippedOffset;
		}
		reset($this->Offsets);
		if ($DB->record_count() > 0) {
			$Class			= 'good';
			$this->DriveFound = true;
		} else {
			$Class = 'badish';
			$Matches[2] .= ' (not found in database)';
		}
		return "<span class=\"log5\">Used Drive$Matches[1]</span>: <span class=\"$Class\">$Matches[2]</span>";
	}
	function media_type_xld($Matches)
	{
		// Pressed CD
		if (trim($Matches[2]) == "Pressed CD") {
			$Class = 'good';
		} else { // CD-R etc.; not necessarily "bad" (e.g. commercial CD-R)
			$Class = 'badish';
			$this->account('Not a pressed cd', false, false, true, true);
		}
		return "<span class=\"log5\">Media type$Matches[1]</span>: <span class=\"$Class\">$Matches[2]</span>";
	}
	function read_mode($Matches)
	{
		if ($Matches[2] == 'Secure') {
			$Class = 'good';
		} else {
			$this->SecureMode	= false;
			$this->NonSecureMode = $Matches[2];
			$Class			   = 'bad';
		}
		$Str = '<span class="log5">Read mode' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
		if ($Matches[3]) {
			$Str .= '<span class="log4">' . $Matches[3] . '</span>';
		}
		return $Str;
	}
	function cdparanoia_mode_xld($Matches)
	{
		if (substr($Matches[2], 0, 3) == 'YES') {
			$Class = 'good';
		} else {
			$this->SecureMode = false;
			$Class			= 'bad';
		}
		return '<span class="log5">Use cdparanoia mode' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function ripper_mode_xld($Matches)
	{
		if (substr($Matches[2], 0, 10) == 'CDParanoia') {
			$Class = 'good';
		} elseif ($Matches[2] == "XLD Secure Ripper") {
			$Class				 = 'good';
			$this->XLDSecureRipper = true;
		} else {
			$this->SecureMode = false;
			$Class			= 'bad';
		}
		return '<span class="log5">Ripper mode' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function ar_xld($Matches)
	{
		if (strpos(strtolower($Matches[4]), 'accurately ripped') != -1) {
			$conf = substr($Matches[6], 0, -1);
			if ((int) $conf < 2) {
				$Class = 'goodish';
			} else {
				$Class = 'good';
			}
		} else {
			$Class = 'badish';
		}
		return "<span class=\"log4\">AccurateRip signature$Matches[1]: <span class=\"$Class\">$Matches[2]</span></span>\n$Matches[3]<span class=\"$Class\">$Matches[4]$Matches[5]$Matches[6]</span>";
	}
	function ar_summary_conf_xld($Matches)
	{
		if (strtolower(trim($Matches[2])) == 'ok') {
			if ($Matches[3] < 2) {
				$Class = 'goodish';
			} else {
				$Class = 'good';
			}
		} else {
			$Class = 'badish';
		}
		return "$Matches[1]<span class =\"$Class\">" . substr($Matches[0], strlen($Matches[1])) . "</span>";
	}
	function ar_summary_conf($Matches)
	{
		if ($Matches[3] < 2) {
			$Class						= 'goodish';
			$this->ARSummary['goodish'][] = $Matches[3];
		} else {
			$Class					 = 'good';
			$this->ARSummary['good'][] = $Matches[3];
		}
		return "<span class =\"$Class\">$Matches[0]</span>";
	}
	function max_retry_count($Matches)
	{
		if ($Matches[2] >= 10) {
			$Class = 'goodish';
		} else {
			$Class = 'badish';
			$this->account('Low "max retry count" (potentially bad setting)');
		}
		return '<span class="log5">Max retry count' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function accurate_stream($Matches)
	{
		if ($Matches[2] == 'Yes') {
			$Class = 'goodish';
		} else {
			$Class = 'badish';
		}
		return '<span class="log5">Utilize accurate stream' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function accurate_stream_eac_pre99($Matches)
	{
		if (strtolower($Matches[1]) != 'no ') {
			$Class = 'goodish';
		} else {
			$Class = 'badish';
		}
		return ', <span class="' . $Class . '">' . $Matches[1] . 'accurate stream</span>';
	}
	function defeat_audio_cache($Matches)
	{
		if ($Matches[2] == 'Yes') {
			$Class = 'good';
		} else {
			$Class = 'bad';
			$this->account('"Defeat audio cache" should be yes', 5);
		}
		return '<span class="log5">Defeat audio cache' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function defeat_audio_cache_eac_pre99($Matches)
	{
		if (strtolower($Matches[1]) != 'no ') {
			$Class = 'good';
		} else {
			$Class = 'bad';
			$this->account('Audio cache not disabled', 5);
		}
		return '<span> </span><span class="' . $Class . '">' . $Matches[1] . 'disable cache</span>';
	}
	function defeat_audio_cache_xld($Matches)
	{
		if (substr($Matches[2], 0, 2) == 'OK' || substr($Matches[2], 0, 3) == 'YES') {
			$Class = 'good';
		} else {
			$Class = 'bad';
			$this->account('"Disable audio cache" should be yes/ok', 5);
		}
		return '<span class="log5">Disable audio cache' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function c2_pointers($Matches)
	{
		if (strtolower($Matches[2]) == 'yes') {
			$Class = 'bad';
			$this->account('C2 pointers were used', 10);
		} else {
			$Class = 'good';
		}
		return '<span class="log5">Make use of C2 pointers' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function c2_pointers_eac_pre99($Matches)
	{
		if (strtolower($Matches[1]) == 'no ') {
			$Class = 'good';
		} else {
			$Class = 'bad';
			$this->account('C2 pointers were used', 10);
		}
		return '<span>with </span><span class="' . $Class . '">' . $Matches[1] . 'C2</span>';
	}
	function read_offset($Matches)
	{
		if ($this->DriveFound == true) {
			if (in_array($Matches[2], $this->Offsets)) {
				$Class = 'good';
			} else {
				$Class = 'bad';
				$this->account('Incorrect read offset for drive. Correct offsets are: ' . implode(', ', $this->Offsets) . ' (Checked against the following drive(s): ' . implode(', ', $this->Drives) . ')', 5, false, false, false, 5);
			}
		} else {
			if ($Matches[2] == 0) {
				$Class = 'bad';
				$this->account('The drive was not found in the database, so we cannot determine the correct read offset. However, the read offset in this case was 0, which is almost never correct. As such, we are assuming that the offset is incorrect', 5, false, false, false, 5);
			} else {
				$Class = 'badish';
			}
		}
		return '<span class="log5">' . ($this->RIPPER == "DBPA" ? '' : 'Read offset correction') . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function fill_offset_samples($Matches)
	{
		if ($Matches[2] == 'Yes') {
			$Class = 'good';
		} else {
			$Class = 'bad';
			$this->account('Does not fill up missing offset samples with silence', 5, false, false, false, 5);
		}
		return '<span class="log5">Fill up missing offset samples with silence' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function delete_silent_blocks($Matches)
	{
		if ($Matches[2] == 'Yes') {
			$Class = 'bad';
			$this->account('Deletes leading and trailing silent blocks', 5, false, false, false, 5);
		} else {
			$Class = 'good';
		}
		return '<span class="log5">Delete leading and trailing silent blocks' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function null_samples($Matches)
	{
		if ($Matches[2] == 'Yes') {
			$Class = 'good';
		} else {
			$Class = 'bad';
			$this->account('Null samples should be used in CRC calculations', 5);
		}
		return '<span class="log5">Null samples used in CRC calculations' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function gap_handling($Matches)
	{
		if (strpos($Matches[2], 'Not detected') !== false) {
			$Class = 'bad';
			$this->account('Gap handling was not detected', 5, false, false, false, 5);
		} elseif (strpos($Matches[2], 'Appended to previous track') !== false) {
			$Class = 'good';
		} else {
			$Class = 'goodish';
		}
		return '<span class="log5">Gap handling' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function gap_handling_xld($Matches)
	{
		if (strpos(strtolower($Matches[2]), 'not') !== false) { //?
			$Class = 'bad';
			$this->account('Incorrect gap handling', 5, false, false, false, 5);
		} elseif (strpos(strtolower($Matches[2]), 'analyzed') !== false && strpos(strtolower($Matches[2]), 'appended') !== false) {
			$Class = 'good';
		} else {
			$Class = 'badish';
			$this->account('Incomplete gap handling', 3, false, false, false, 3);
		}
		return '<span class="log5">Gap status' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function add_id3_tag($Matches)
	{
		if ($Matches[2] == 'Yes') {
			$Class = 'badish';
			$this->account('ID3 tags should not be added to FLAC files - they are mainly for MP3 files. FLACs should have vorbis comments for tags instead.', false, false, false, true);
		} else {
			$Class = 'good';
		}
		return '<span class="log5">Add ID3 tag' . $Matches[1] . '</span>: <span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function test_copy($Matches)
	{
		if ($this->RIPPER == "EAC") {
			if ($Matches[1] == $Matches[3]) {
				$Class = 'good';
			} else {
				$Class = 'bad';
				$this->account_track("CRC mismatch: $Matches[1] and $Matches[3]", 30);
				if (!$this->SecureMode) {
					$this->DecreaseScoreTrack += 20;
					$this->BadTrack[] = 'Rip ' . (($this->Combined) ? " (" . $this->CurrLog . ") " : '') . 'was not done in Secure mode, and experienced CRC mismatches (-20 points)';
					$this->SecureMode = true;
				}
			}
			return "<span class=\"log4\">Test CRC <span class=\"$Class\">$Matches[1]</span></span>\n$Matches[2]<span class=\"log4\">Copy CRC <span class=\"$Class\">$Matches[3]</span></span>";
		}
		if ($this->RIPPER == "XLD") {
			if ($Matches[2] == $Matches[5]) {
				$Class = 'good';
			} else {
				$Class = 'bad';
				$this->account_track("CRC mismatch: $Matches[2] and $Matches[5]", 30);
				if (!$this->SecureMode) {
					$this->DecreaseScoreTrack += 20;
					$this->BadTrack[] = 'Rip ' . (($this->Combined) ? " (" . $this->CurrLog . ") " : '') . 'was not done with Secure Ripper / in CDParanoia mode, and experienced CRC mismatches (-20 points)';
					$this->SecureMode = true;
				}
			}
			return "<span class=\"log4\">CRC32 hash (test run)$Matches[1] <span class=\"$Class\">$Matches[2]</span></span>\n$Matches[3]<span class=\"log4\">CRC32 hash$Matches[4] <span class=\"$Class\">$Matches[5]</span></span>";
		}
	}
	function xld_all_stat($Matches)
	{
		if (strtolower($Matches[1]) == 'read error' || strtolower($Matches[1]) == 'skipped (treated as error)' || strtolower($Matches[1]) == 'inconsistency in error sectors' || strtolower($Matches[1]) == 'damaged sector count') {
			if ($Matches[3] == 0) {
				$Class = 'good';
			} else {
				$Class = 'bad';
			}
			return '<span class="log4">' . $Matches[1] . $Matches[2] . '</span> <span class="' . $Class . '">' . $Matches[3] . '</span>';
		}
		if (strtolower($Matches[1]) == 'retry sector count' || strtolower($Matches[1]) == 'jitter error (maybe fixed)' || strtolower($Matches[1]) == 'edge jitter error (maybe fixed)' || strtolower($Matches[1]) == 'atom jitter error (maybe fixed)' || strtolower($Matches[1]) == 'drift error (maybe fixed)' || strtolower($Matches[1]) == 'dropped bytes error (maybe fixed)' || strtolower($Matches[1]) == 'duplicated bytes error (maybe fixed)') {
			if ($Matches[3] == 0) {
				$Class = 'goodish';
			} else {
				$Class = 'badish';
			}
			return '<span class="log4">' . $Matches[1] . $Matches[2] . '</span> <span class="' . $Class . '">' . $Matches[3] . '</span>';
		}
	}
	function xld_stat($Matches)
	{
		if (strtolower($Matches[1]) == 'read error') {
			if ($Matches[3] == 0) {
				$Class = 'good';
			} else {
				$Class = 'bad';
				$err   = ($Matches[3] > 10) ? 10 : $Matches[3]; //max.
				$this->account_track('Read error' . ($Matches[3] == 1 ? '' : 's') . ' detected', $err);
			}
			return '<span class="log4">' . $Matches[1] . $Matches[2] . '</span> <span class="' . $Class . '">' . $Matches[3] . '</span>';
		}
		if (strtolower($Matches[1]) == 'skipped (treated as error)') {
			if ($Matches[3] == 0) {
				$Class = 'good';
			} else {
				$Class = 'bad';
				$err   = ($Matches[3] > 10) ? 10 : $Matches[3]; //max.
				$this->account_track('Skipped error' . ($Matches[3] == 1 ? '' : 's') . ' detected', $err);
			}
			return '<span class="log4">' . $Matches[1] . $Matches[2] . '</span> <span class="' . $Class . '">' . $Matches[3] . '</span>';
		}
		if (strtolower($Matches[1]) == 'inconsistency in error sectors') {
			if ($Matches[3] == 0) {
				$Class = 'good';
			} else {
				$Class = 'bad';
				$err   = ($Matches[3] > 10) ? 10 : $Matches[3]; //max.
				$this->account_track('Inconsistenc' . (($Matches[3] == 1) ? 'y' : 'ies') . ' in error sectors detected', $err);
			}
			return '<span class="log4">' . $Matches[1] . $Matches[2] . '</span> <span class="' . $Class . '">' . $Matches[3] . '</span>';
		}
		if (strtolower($Matches[1]) == 'damaged sector count') { //xld secure ripper
			if ($Matches[3] == 0) {
				$Class = 'good';
			} else {
				$Class = 'bad';
				$err   = ($Matches[3] > 10) ? 10 : $Matches[3]; //max.
				$this->account_track('Damaged sector count of ' . ($Matches[3]), $err);
			}
			return '<span class="log4">' . $Matches[1] . $Matches[2] . '</span> <span class="' . $Class . '">' . $Matches[3] . '</span>';
		}
		if (strtolower($Matches[1]) == 'retry sector count' || strtolower($Matches[1]) == 'jitter error (maybe fixed)' || strtolower($Matches[1]) == 'edge jitter error (maybe fixed)' || strtolower($Matches[1]) == 'atom jitter error (maybe fixed)' || strtolower($Matches[1]) == 'drift error (maybe fixed)' || strtolower($Matches[1]) == 'dropped bytes error (maybe fixed)' || strtolower($Matches[1]) == 'duplicated bytes error (maybe fixed)') {
			if ($Matches[3] == 0) {
				$Class = 'goodish';
			} else {
				$Class = 'badish';
			}
			return '<span class="log4">' . $Matches[1] . $Matches[2] . '</span> <span class="' . $Class . '">' . $Matches[3] . '</span>';
		}
	}
	function toc($Matches)
	{
		return "$Matches[1]<span class=\"log4\">$Matches[2]</span>$Matches[3]<strong>|</strong>$Matches[4]<span class=\"log1\">$Matches[5]</span>$Matches[7]<strong>|</strong>$Matches[8]<span class=\"log1\">$Matches[9]</span>$Matches[11]<strong>|</strong>$Matches[12]<span class=\"log1\">$Matches[13]</span>$Matches[14]<strong>|</strong>$Matches[15]<span class=\"log1\">$Matches[16]</span>$Matches[17]" . "\n";
	}
	function parse_dBpoweramp()
	{
		$Log		= display_str($this->Log);
		$this->Logs = preg_split("/(dBpoweramp Release.*?\n)/i", $Log, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($this->Logs as $key => $value) {
			if (trim($value) == "") {
				unset($this->Logs[$key]);
				continue;
			} //strip empty
			//prepend capture
			if (!preg_match("/dBpoweramp Release.*?\n/i", $value)) {
				$this->Logs[$key - 1] .= $value;
				unset($this->Logs[$key]);
			}
		}
		$this->Logs = array_values($this->Logs); //rebuild index
		if (count($this->Logs) > 1) {
			$this->Combined = count($this->Logs);
		} //is_combined
		foreach ($this->Logs as $LogArrayKey => $Log) {
			// log loop
			$this->CurrLog = $LogArrayKey + 1;
			$CurrScore	 = $this->Score;
			//release / date
			if (preg_match('/^dBpoweramp Release +(\d+)/i', $Log, $Matches)) {
				if ($Matches[1] < $this->DBAVersionCheck['safe']) {
					$this->account('Unsafe version detected, please update to ' . $this->DBAVersionCheck['safe'] . ' | ' . $this->DBAVersionCheck['best'] . ' for best results', false, 0);
					return;
				} else if ($Matches[1] < $this->DBAVersionCheck['best']) {
					$this->account('Please update to version ' . $this->DBAVersionCheck['best'] . ' for best results', false, false, false, true);
				}
			} else {
				$this->account('Error verifying dBpoweramp version', false, 0);
				return;
			}
			$Log = preg_replace("/dBpoweramp Release ([\.\d]+)(.*?)(\d+.*)/i", "dBpoweramp Release <span class=\"log1\">$1</span>$2<span class=\"log1\">$3</span>", $Log, 1, $Count);
			//drive
			$Log = preg_replace_callback("/Ripping with drive(.*?):( +\[.+\])/i", array(
				$this,
				'drive'
			), $Log, 1, $Count);
			if (!$Count) {
				$this->account('Could not verify used drive', 1);
			}
			//offset
			$Log = preg_replace_callback('/(Drive offset\s*): ([+-]?[0-9]+)/i', array(
				$this,
				'read_offset'
			), $Log, 1, $Count);
			if (!$Count) {
				$this->account('Could not verify read offset', 1);
			}
			//overread
			$Log = preg_replace('/Overread Lead-in\/out( *): (Yes|No)/i', '<span class="log5">Overread Lead-in/out$1</span>: <span class="log4">$2</span>', $Log, 1, $Count);
			//accurateRip active
			$Log = preg_replace_callback('/AccurateRip:( +)([\w\s]+)/i', array(
				$this,
				'dbpa_accuraterip_active'
			), $Log, 1, $Count);
			if (!$Count) {
				$this->account('Could not verify accurate rip active', 1);
			}
			//c2 pointers
			$Log = preg_replace_callback('/Using C2:( +)(Yes|No)/i', array(
				$this,
				'dbpa_c2_pointers'
			), $Log, 1, $C2);
			if (!$C2) {
				$this->account('Could not verify c2 pointers', 1);
			}
			//cache (potentially bad)
			$Log = preg_replace_callback('/Cache:( +)(None|\w+)/i', array(
				$this,
				'dbpa_audio_cache'
			), $Log, 1, $Cache);
			if (!$Cache) {
				$this->account('Could not verify cache setting', 1);
				$this->InvalidateCache = false;
			}
			//fua invalidate (bad for non-plextor)
			$Log = preg_replace_callback('/FUA Cache Invalidate:( +)(Yes|No)/i', array(
				$this,
				'dbpa_fua'
			), $Log, 1, $Fua);
			if (!$Fua) {
				$this->account('Could not verify fua setting');
			}
			//passes drive speed
			$Log = preg_replace("/(Pass \d+ Drive Speed)(: +)([^,]+)/i", "<span class=\"log5\">$1</span>$2<span class=\"log4\">$3</span>", $Log, -1, $Count);
			//ultra
			$Log = preg_replace_callback('/Ultra:+( +Vary Drive Speed: +)(Yes|No)(.*?Min Passes: +)(\d+)(.*?Max Passes: +)(\d+)(.*?Finish After Clean Passes: +)(\d+)/i', array(
				$this,
				'dbpa_ultra'
			), $Log, 1, $Ultra);
			if (!$Ultra) {
				$this->SecureMode = false;
				$this->account('Ultra secure mode was not used', 10);
			}
			//bad sector re-rip
			$Log = preg_replace_callback('/Bad Sector Re-rip:+( +Drive Speed: +)(\w+)(.*?Maximum Re-reads: +)(\d+)/i', array(
				$this,
				'dbpa_rerip'
			), $Log, 1, $ReRip);
			if (!$ReRip) {
				$this->account('Could not verify bad sector re-rip setting', 1);
			}
			//encoder
			$Log = preg_replace("/(\nEncoder)(: +)(.*?\n)/i", "<span class=\"log5\">$1</span>$2<span class=\"log4\">$3</span>", $Log, 1, $Count);
			//compression level
			if (preg_match('/FLAC[ =]+\-?compression( |-)+level( |-)+(\d)/i', $Log, $Matches)) {
				if ($Matches[3] < 8) {
					$this->account('Flac compression level should be 8', false, false, false, true);
				}
			}
			//dsp / action
			if ($Log = preg_replace("/(DSP Effects.*?)(:.*?)\n/i", "<span class=\"log5\">$1</span>$2\n", $Log, 1, $Matches)) {
				$badDSP	 = array( //add destructive effect names here, ci, partial
					'Normalize',
					'Fade',
					'Silence Track Deletion',
					'Silence Removal',
					'Grabber',
					'Equalizer',
					'Insert Audio',
					'Karaoke',
					'Loop',
					'Maximum Length',
					'Minimum Length',
					'Reverse',
					'Trim',
					'Write Silence'
				);
				$dubiousDSP = array( //add potentially bad effect names here, ci, partial
					'Bit Depth',
					'Channel Count',
					'Channel Mapper',
					'DirectX',
					'Resample',
					'VST'
				);
				foreach ($badDSP as $dsp) {
					if (preg_match("/dspeffect\d+.*?([^=]+" . $dsp . ".*?)=/i", $Log, $M)) {
						$effect = preg_replace("/&quot;|\"/i", '', $M[1]);
						unset($this->Bad);
						$this->account('Destructive dsp effect used: ' . $effect, false, 0);
						return;
					}
				}
				foreach ($dubiousDSP as $dsp) {
					if (preg_match("/dspeffect\d+.*?([^=]+" . $dsp . ".*?)=/i", $Log, $M)) {
						$effect = preg_replace("/&quot;|\"/i", '', $M[1]);
						$this->account('Dubious dsp effect used: ' . $effect, 10, false, false, false, 10);
					}
				}
			}
			//drive & settings (label)
			$Log = preg_replace("/(\nDrive +(&amp;|and|&) +Settings *\n)/i", "<span class=\"log1\">$1</span>", $Log, 1, $Count);
			//extraction log (label)
			$Log = preg_replace("/(\nExtraction Log *\n)/i", "<span class=\"log1\">$1</span>", $Log, 1, $Count);
			if (!$Count) {
				$this->account('No extraction log', false, 0, true);
			}
			//overread disclaimer
			$Log = preg_replace("/(\n.*? Drive is unable to read .*?\n)/i", "<span class=\"badish\">$1</span>", $Log, 1, $Count);
			if ($Count) {
				$this->account('Uncheck over-read option for this drive', false, false, false, true);
			}
			//------ Handle individual tracks ------//
			$FormattedTrackListing = '';
			preg_match('/\nTrack( +)([0-9]{1,3})([^<]+)/i', $Log, $Matches);
			$TrackListing = $Matches[0];
			$FullTracks   = preg_split('/\nTrack( +)([0-9]{1,3})/i', $TrackListing, -1, PREG_SPLIT_DELIM_CAPTURE);
			array_shift($FullTracks);
			$TrackBodies = preg_split('/\nTrack( +)([0-9]{1,3})/i', $TrackListing, -1);
			array_shift($TrackBodies);
			$Tracks = array();
			while (list($Key, $TrackBody) = each($TrackBodies)) {
				// The number of spaces between 'Track' and the number, to keep formatting intact
				$Spaces				   = $FullTracks[($Key * 3)];
				// Track number
				$TrackNumber			  = $FullTracks[($Key * 3) + 1];
				$this->TrackNumber		= $TrackNumber;
				// How much to decrease the overall score by, if this track fails and no attempt at recovery is made later on
				$this->DecreaseScoreTrack = 0;
				// List of things that went wrong to add to $this->Bad if this track fails and no attempt at recovery is made later on
				$this->BadTrack		   = array();
				// The track number is stripped in the preg_split, let's bring it back, eh?
				$TrackBody				= '<span class="log5">Track</span>' . $Spaces . '<span class="log4 log1">' . $TrackNumber . '</span>' . $TrackBody;
				//lba
				$TrackBody				= preg_replace_callback('/( +Ripped LBA +)(\d+)( +to +)(\d+)(.*?\.)/i', array(
					$this,
					'dbpa_lba'
				), $TrackBody, 1, $LBAGood);
				$TrackBody				= preg_replace_callback('/( +ERROR Ripping LBA +)(\d+)( +to +)(\d+)(.*?\.)/i', array(
					$this,
					'dbpa_lba'
				), $TrackBody, 1, $LBABad);
				if (!$LBAGood && !$LBABad) {
					$this->account_track('Could not verify rip status', 30);
				}
				//filename
				$TrackBody = preg_replace('/Filename: ((.+)?\.(wav|flac|ape|ignore))\n/i', "<span class=\"log4\">Filename: <span class=\"log3\">$1</span></span>\n", $TrackBody, 1, $Count);
				if (!$Count) {
					$this->account_track('Could not verify filename', 1);
				}
				//ar track + conf (not in use atm for dbpa)
				preg_match('/AccurateRip: +Accurate +\(confidence ([0-9]+)\)/i', $TrackBody, $matches);
				if ($matches) {
					$this->ARTracks[$TrackNumber] = $matches[1];
				} else {
					$this->ARTracks[$TrackNumber] = 0;
				}
				//we need this one instead
				preg_match('/AccurateRip: +Inaccurate +\(confidence ([0-9]+)\)/i', $TrackBody, $matches);
				if ($matches) {
					$this->IARTracks[$TrackNumber] = $matches[1];
				} //match - no boost
				else {
					$this->IARTracks[$TrackNumber] = 0;
				}
				//accurate rip
				$TrackBody	= preg_replace_callback('/AccurateRip:(.*?)\(confidence ([0-9]+)\)/i', array(
					$this,
					'dbpa_accurip'
				), $TrackBody, 1, $Count);
				//accurip crc
				$TrackBody	= preg_replace('/AccurateRip CRC: +([a-fA-F0-9]{8})/i', "<span class=\"log4\">AccurateRip CRC: <span class=\"log3\">$1</span></span>", $TrackBody, 1, $Count);
				//accurip verified
				$TrackBody	= preg_replace_callback('/AccurateRip(.+)(\[CRCv\d +)([a-fA-F0-9]+)\]/i', array(
					$this,
					'dbpa_accurip_verified'
				), $TrackBody, -1, $Count);
				//copy crc
				$TrackBody	= preg_replace('/CRC32: +([a-fA-F0-9]{8})/i', "<span class=\"log4\">CRC32: <span class=\"log3\">$1</span></span>", $TrackBody, 1, $Count);
				//disc id
				$TrackBody	= preg_replace('/\[DiscID: +(.*?)\]/i', "[DiscID: <span class=\"log1\">$1</span>]", $TrackBody, 1, $Count);
				//in/secure
				$TrackBody	= preg_replace_callback('/(I?n?Secure)(.*?)(\[.*?\])/i', array(
					$this,
					'dbpa_secure'
				), $TrackBody, 1, $Count);
				//re-rip frame
				$TrackBody	= preg_replace_callback('/Re-rip Frame: +(.*?\n)/i', array(
					$this,
					'dbpa_rerip_frame'
				), $TrackBody, -1, $Count);
				$DubiousTrack = false;
				if ($Count && !$this->InvalidateCache && $this->ARTracks[$TrackNumber] < 2) {
					$DubiousTrack = true;
				}
				$Tracks[$TrackNumber] = array(
					'number' => $TrackNumber,
					'spaces' => $Spaces,
					'text' => $TrackBody,
					'decreasescore' => $this->DecreaseScoreTrack,
					'bad' => $this->BadTrack,
					'lba_start' => $this->LBA[$TrackNumber]['start'],
					'lba_end' => $this->LBA[$TrackNumber]['stop'],
					'rerip_conf' => $this->FrameReRipConf[$TrackNumber],
					'inaccurate' => $this->IARTracks[$TrackNumber],
					'dubious' => $DubiousTrack
				);
				$FormattedTrackListing .= "\n" . $TrackBody;
				$this->Tracks[$TrackNumber] = $Tracks[$TrackNumber];
			}
			unset($Tracks);
			$Log = str_replace($TrackListing, $FormattedTrackListing, $Log);
			$Log = str_replace('<br>', "\n", $Log);
			//status report
			$Log = preg_replace_callback('/([\-\n]+)(\d+ Tracks.*\n?)/is', array(
				$this,
				'dbpa_status_report'
			), $Log, 1, $Status1);
			$Log = preg_replace_callback('/([\-\n]+)(User Stopped Ripping.*\n?)/is', array(
				$this,
				'dbpa_status_report'
			), $Log, 1, $Status2);
			if (!$Status1 && !$Status2) {
				$this->account('No or invalid status report', false, 0, false, false, 100);
			}
			// end parsing
			$this->Logs[$LogArrayKey] = $Log;
			$this->check_tracks();
			$minLBA = $minLBATrack = $prevLBA = null;
			foreach ($this->Tracks as $Num => $Track) { //send score/bad
				if ($Track['decreasescore']) {
					$this->Score -= $Track['decreasescore'];
				}
				if (count($Track['bad']) > 0) {
					$this->Bad = array_merge($this->Bad, $Track['bad']);
				}
				if ($Track['dubious']) {
					$this->DubiousTracks++;
				}
				//boost track
				else if (!$Track['inaccurate'] && $Track['rerip_conf'] && $Track['decreasescore'] > 0) {
					$toScore = ($this->Score + $Track['decreasescore'] <= 0) ? '' : 'to ' . ($this->Score + $Track['decreasescore']) . ' points!';
					$this->account('Track ' . $Num . (($this->Combined) ? " (" . $this->CurrLog . ")" : '') . ': All frames re-ripped matching with good confidence, score boosted ' . $toScore, false, ($this->Score + $Track['decreasescore']));
				}
				$minLBA	  = ($minLBA === null || $Track['lba_start'] < $minLBA) ? $Track['lba_start'] : $minLBA;
				$minLBATrack = ($Track['lba_start'] == $minLBA) ? $Num : $minLBATrack;
				if ($prevLBA && $prevLBA != $Track['lba_start']) {
					$this->account('LBA inconsistency detected at track ' . $Num, 1, false, true, false, 1);
				}
				$prevLBA = $Track['lba_end'];
			}
			if ($minLBATrack != 1) {
				$this->account('Partial rip detected, starting with track ' . $minLBATrack . ' at block ' . $minLBA, false, false, true, true);
			} else if ($minLBA > 75) {
				$this->account('Unusual pre-gap of ' . number_format(($minLBA / 75), 4, '.', ',') . ' sec. detected, first track starting with block ' . $minLBA, false, false, true, true);
			} else if ($minLBA > 0) {
				$this->account('Pre-gap of ' . $minLBA . ' blocks detected', false, false, true, true);
			}
			unset($this->Tracks);
			unset($this->LBA);
			unset($this->FrameReRipConf);
			//boost ar
			$ar_with_conf = false;
			$minConf	  = null;
			foreach ($this->ARTracks as $Track => $Conf) {
				if (!is_numeric($Conf) || $Conf < 2) {
					$ar_with_conf = false;
					break;
				} //non-ar track found
				else {
					$ar_with_conf = true;
					$minConf	  = (!$minConf || $Conf < $minConf) ? $Conf : $minConf;
				}
			}
			if ($this->Score != 100 && $ar_with_conf) {
				$tmp_score   = $this->Score;
				$this->Score = (($CurrScore) ? $CurrScore : 100) - $this->DecreaseBoost;
				if (((($CurrScore) ? $CurrScore : 100) - $tmp_score) != $this->DecreaseBoost) {
					$Msg		 = 'All tracks accurately ripped with at least confidence ' . $minConf . '. Score ' . (($this->Combined) ? "for log " . $this->CurrLog . " " : '') . 'boosted to ' . $this->Score . ' points!';
					$this->Bad[] = $Msg;
				}
			}
			$this->ARTracks		= array();
			$this->IARTracks	   = array();
			$this->DecreaseBoost   = 0;
			$this->SecureMode	  = true;
			$this->NonSecureMode   = null;
			$this->InvalidateCache = true;
		} //end log loop
		$this->Log = implode($this->Logs);
		if ($this->DubiousTracks) {
			$Decrease = ($this->DubiousTracks > 5) ? 5 : $this->DubiousTracks;
			$this->account($this->DubiousTracks . ' track' . ($this->DubiousTracks > 1 ? 's' : '') . ' re-ripped with no cache invalidate', $Decrease);
		}
		$this->Score = ($this->Score < 0) ? 0 : $this->Score; //min. score
		natcasesort($this->Bad); //sort ci
		$this->format_report();
		if ($this->Combined) {
			array_unshift($this->Bad, "Combined Log (" . $this->Combined . ")");
		} //combined log msg
		// end dBpoweramp
	}
	function dbpa_rerip_frame($Matches)
	{
		$Status = strtolower(trim($Matches[1]));
		$Runs   = null;
		if (preg_match('/\d+ +\/ +(\d+)/i', $Matches[1], $M)) {
			$Runs = $M[1];
		}
		if (strpos($Status, "matched")) {
			$Class = ($Runs && $Runs < 20) ? 'goodish' : 'badish';
		} else {
			$Class = 'bad';
		}
		if ($Class == 'goodish' && $this->FrameReRipConf[$this->TrackNumber] !== false) {
			$this->FrameReRipConf[$this->TrackNumber] = true;
		} else if ($Class != 'goodish') {
			$this->FrameReRipConf[$this->TrackNumber] = false;
		}
		return 'Re-rip Frame:' . '<span class="' . $Class . '"> ' . $Matches[1] . '</span>';
	}
	function dbpa_status_report($Matches)
	{
		$Stopped = $Error = $Inaccurate = $Insecure = $Warning = $Secure = $Accurate = false;
		if (preg_match('/Stopped/i', $Matches[2], $Void)) {
			$Stopped = true;
			$this->account('User stopped ripping', false, 0, false, false, 100);
		}
		if (preg_match('/Could not/i', $Matches[2], $Void)) {
			$Error = true;
		}
		if (preg_match('/Inaccurate/i', $Matches[2], $Void)) {
			$Inaccurate = true;
		}
		if (preg_match('/\Insecure/i', $Matches[2], $Void)) {
			$Insecure = true;
		}
		if (preg_match('/\(Warning/i', $Matches[2], $Void)) {
			$Warning = true;
		}
		if (preg_match('/\d+ Secure|Securely/i', $Matches[2], $Void)) {
			$Secure = true;
		}
		if (preg_match('/\d+ Accurate|Accurately/i', $Matches[2], $Void)) {
			$Accurate = true;
		}
		if ($Stopped || $Error || $Inaccurate || $Insecure) {
			$Class = "bad";
		} else if ($Warning) {
			$Class = "badish";
		} else if (($Secure && !$this->SecureMode) || ($Accurate && $Secure)) {
			$Class = "goodish";
		} else if ($Accurate || ($Secure && $this->SecureMode)) {
			$Class = "good";
		}
		return $Matches[1] . '<span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function dbpa_secure($Matches)
	{
		$status  = strtolower(trim($Matches[1]));
		$warning = strtolower(trim($Matches[2]));
		$passes  = $Matches[3];
		if ($status == "secure" && $warning == "(warning)") {
			$Class = 'badish';
			if (!$this->ARTracks[$this->TrackNumber] && !$this->IARTracks[$this->TrackNumber]) {
				if ($this->SecureMode) {
					$this->account_track('Secure with warning', false, false, false, true);
				} else {
					$Class = 'bad';
					$this->account_track('Secure with warning (no ultra, not in database)', 1);
				}
			}
		} else if ($status == "secure") {
			if ($this->SecureMode) {
				$Class = 'good';
			} else {
				$Class = 'goodish';
			}
		} else if ($status == "insecure") {
			$Class = 'bad';
			if (!$this->ARTracks[$TrackNumber] && !strpos(implode(",", $this->BadTrack), "Inaccurate")) {
				$this->account_track('Insecure', 30);
			}
		}
		return '<span class="' . $Class . '">' . $Matches[1] . $Matches[2] . '</span><span class="log3">' . $Matches[3] . '</span>';
	}
	function dbpa_accurip($Matches)
	{
		$status = strtolower(trim($Matches[1]));
		$conf   = $Matches[2];
		if ($status == "inaccurate") {
			if ($conf >= 2) {
				$this->account_track('Inaccurate with confidence ' . $conf, 30);
				$Class = 'bad';
			} else {
				$this->account_track('Inaccurate with confidence ' . $conf, 10);
				$Class = 'badish';
			}
		} else if ($status == "accurate") {
			if ($conf >= 2) {
				$Class = 'good';
			} else {
				$Class = 'goodish';
			}
		} else {
			$Class = 'badish';
		}
		return '<span class="log4">AccurateRip</span>:<span class="' . $Class . '">' . $Matches[1] . '(confidence ' . $Matches[2] . ')</span>';
	}
	function dbpa_accurip_verified($Matches)
	{
		$status = strtolower(trim($Matches[1]));
		if (preg_match('/^verified +confidence +(\d+)/', $status, $M)) {
			if ($M[1] >= 2) {
				$Class = 'good';
			} else {
				$Class = 'goodish';
			}
		} else if (preg_match('/not|can\'t|can\s?not|inaccurate/', $status, $Void)) { //needs verification
			$Class = 'bad';
		} else {
			$Class = 'badish';
		}
		return '<span class="log4">AccurateRip</span><span class="' . $Class . '">' . $Matches[1] . '</span>' . $Matches[2] . '<span class="log3">' . $Matches[3] . '</span>]';
	}
	function dbpa_lba($Matches)
	{
		$Class = 'good';
		if (strpos(strtolower($Matches[1]), "error")) {
			$this->account('Error ripping track ' . $this->TrackNumber, false, 0, true);
			$Class = 'bad';
		}
		$this->LBA[$this->TrackNumber] = array(
			'start' => $Matches[2],
			'stop' => $Matches[4]
		);
		return '<span class="' . $Class . '">' . $Matches[1] . '</span><span class="log3">' . $Matches[2] . '</span>' . $Matches[3] . '<span class="log3">' . $Matches[4] . '</span>' . $Matches[5];
	}
	function dbpa_rerip($Matches)
	{
		if ($Matches[4] > 34) {
			$Class = 'bad';
			$this->account('Bad sector: Too many re-reads, should be 34 or less', 1);
		} else if ($Matches[4] > 19) {
			$Class = 'goodish';
		} else {
			$Class = 'good';
		}
		return '<span class="log5">Bad Sector Re-rip</span>:' . $Matches[1] . $Matches[2] . $Matches[3] . '<span class="' . $Class . '">' . $Matches[4] . '</span>';
	}
	function dbpa_ultra($Matches)
	{
		if ($Matches[2] == 'Yes') {
			$Class_1 = 'good';
		} else {
			$Class_1 = 'badish';
			$this->account('Ultra: Vary Drive Speed should be yes', false, false, false, true);
		}
		if ($Matches[4] >= 3) {
			$Class_2 = 'good';
		} else {
			$Class_2 = 'bad';
			$this->account('Ultra: Min passes should be 3 or more', 1);
		}
		if ($Matches[6] >= 6) {
			$Class_3 = 'good';
		} else {
			$Class_3 = 'badish';
			$this->account('Ultra: Max passes should be 6 or more', false, false, false, true);
		}
		if ($Matches[8] >= 2) {
			$Class_4 = 'good';
		} else {
			$Class_4 = 'bad';
			$this->account('Ultra: Finish After Clean Passes should be 2 or more', 1);
		}
		return '<span class="log5">Ultra</span>:' . $Matches[1] . '<span class="' . $Class_1 . '">' . $Matches[2] . '</span>' . $Matches[3] . '<span class="' . $Class_2 . '">' . $Matches[4] . '</span>' . $Matches[5] . '<span class="' . $Class_3 . '">' . $Matches[6] . '</span>' . $Matches[7] . '<span class="' . $Class_4 . '">' . $Matches[8] . '</span>';
	}
	function check_tracks()
	{
		if (!count($this->Tracks)) { //no tracks
			unset($this->Bad);
			if ($this->Combined) {
				$this->Bad[] = "Combined Log (" . $this->Combined . ")";
				$this->Bad[] = "Invalid log (" . $this->CurrLog . "), no tracks!";
			} else {
				$this->Bad[] = "Invalid log, no tracks!";
			}
			$this->Score = 0;
			return array(
				$this->Score,
				$this->Good,
				$this->Bad,
				$this->Log
			);
		}
	}
	function format_report() //sort by importance & reasonable log length
	{
		if (!count($this->Bad)) {
			return;
		}
		$myBad = array();
		foreach ($this->Bad as $Key => $Val) {
			if (preg_match("/(points?\W)|(boosted)\)/i", $Val)) {
				$myBad['high'][] = $Val;
			} else {
				$myBad['low'][] = $Val;
			}
		}
		$this->Bad = array();
		$this->Bad = $myBad['high'];
		if (count($this->Bad) < $this->Limit) {
			foreach ($myBad['low'] as $Key => $Val) {
				if (count($this->Bad) > $this->Limit) {
					break;
				} else {
					$this->Bad[] = $Val;
				}
			}
		}
		if (count($this->Bad) > $this->Limit) {
			array_push($this->Bad, "(..)");
		}
	}
	function account($Msg, $Decrease = false, $Score = false, $InclCombined = false, $Notice = false, $DecreaseBoost = false)
	{
		$DecreaseScore = $SetScore = false;
		$Append2	   = '';
		$Append1	   = ($InclCombined) ? (($this->Combined) ? " (" . $this->CurrLog . ")" : '') : '';
		$Prepend	   = ($Notice) ? '[Notice] ' : '';
		if ($Decrease) {
			$DecreaseScore = true;
			$Append2	   = ($Decrease > 0) ? ' (-' . $Decrease . ' point' . ($Decrease == 1 ? '' : 's') . ')' : '';
		} else if ($Score || $Score === 0) {
			$SetScore = true;
			$Decrease = 100 - $Score;
			$Append2  = ($Decrease > 0) ? ' (-' . $Decrease . ' point' . ($Decrease == 1 ? '' : 's') . ')' : '';
		}
		if (!in_array($Prepend . $Msg . $Append1 . $Append2, $this->Bad)) {
			$this->Bad[] = $Prepend . $Msg . $Append1 . $Append2;
			if ($DecreaseScore) {
				$this->Score -= $Decrease;
			}
			if ($SetScore) {
				$this->Score = $Score;
			}
			if ($DecreaseBoost) {
				$this->DecreaseBoost += $DecreaseBoost;
			}
		}
	}
	function account_track($Msg, $Decrease = false)
	{
		$tn	 = (intval($this->TrackNumber) < 10) ? '0' . intval($this->TrackNumber) : $this->TrackNumber;
		$Append = '';
		if ($Decrease) {
			$this->DecreaseScoreTrack += $Decrease;
			$Append = ' (-' . $Decrease . ' point' . ($Decrease == 1 ? '' : 's') . ')';
		}
		$Prepend		  = 'Track ' . $tn . (($this->Combined) ? " (" . $this->CurrLog . ")" : '') . ': ';
		$this->BadTrack[] = $Prepend . $Msg . $Append;
	}
	function dbpa_fua($Matches)
	{
		$isPlextor = ($this->Drive && preg_match('/Plextor/i', $this->Drive)) ? true : false;
		if ((trim($Matches[2]) == 'Yes' && $isPlextor) || trim($Matches[2]) == 'No') {
			$Class = 'good';
		} else if (trim($Matches[2]) == 'Yes') {
			$Class = 'bad';
			$this->account('FUA Cache Invalidate should only be set for compatible Plextor drives', 5);
		}
		return '<span class="log5">FUA Cache Invalidate</span>:' . $Matches[1] . '<span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function dbpa_audio_cache($Matches)
	{
		if (trim($Matches[2]) == 'None') {
			$Class = 'badish';
			$this->account('No cache is a potentially bad setting and should only be used if the drive doesn\'t cache audio', false, false, false, true);
			$this->InvalidateCache = false;
		} else {
			preg_match('/(\d+)/i', $Matches[2], $CacheSize);
			if ($CacheSize[1] && $CacheSize[1] >= 1024) {
				$Class = 'good';
			} else if ($CacheSize[1] && $CacheSize[1] > 0) {
				$Class = 'goodish';
			} else {
				$Class = 'bad';
				$this->account('Invalid cache setting', 5);
			}
		}
		return '<span class="log5">Cache</span>:' . $Matches[1] . '<span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function dbpa_c2_pointers($Matches)
	{
		if (trim($Matches[2]) == 'Yes') {
			$Class = 'good';
		} else {
			$Class = 'badish';
			$this->account('C2 pointers should be used if supported', false, false, false, true);
		}
		return '<span class="log5">Using C2</span>:' . $Matches[1] . '<span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
	function dbpa_accuraterip_active($Matches)
	{
		if (trim($Matches[2]) == 'Active') {
			$Class = 'good';
		} else {
			$Class = 'bad';
			$this->account('AccurateRip not active', 5);
		}
		return '<span class="log5">AccurateRip</span>:' . $Matches[1] . '<span class="' . $Class . '">' . $Matches[2] . '</span>';
	}
}
?>