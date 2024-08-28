/**
 * Allows the user to populate upload form using MusicBrainz.
 * Popup display code based on example found here http://yensdesign.com/2008/09/how-to-create-a-stunning-and-smooth-popup-using-jquery/
 *
 * @author Gwindow
 *
 * TODO Center dynamically based on scroll position
 */


(function() {
    // global variables
    var $catalog_number;
    var $record_label;
    var $release_group_id;
    var $release_type;
    var $tags;
    var $year_original;
    var $year_release;

    var $popup_state = 0;
    var $searched    = false;

    document.addEventListener('DOMContentLoaded', function() {
        loadCSS();
        enableMusicBrainzButton();
        controlPopup();

        $("#musicbrainz_button").click(function() {
            let $album = $("#title").val();
            let $artist = $("#artist_0").val();
            if ($artist.length > 0 || $album.length > 0) {
                jQuery('#results1').empty();
                jQuery('#results2').empty();
                jQuery('#results1').show();
                jQuery('#results2').show();
                $searched = true;
                $.ajax({
                    type: "GET",
                    url : "https://musicbrainz.org/ws/2/release-group/?query=artist:%22"
                        + encodeURIComponent($artist) + "%22%20AND%20releasegroup:%22"
                        + encodeURIComponent($album) + "%22",
                    dataType: "xml",
                    success: showReleaseGroups
                });
            } else {
                alert("Please fill out artist and/or album fields.");
            }
        });

        $("#results1").click(function(event) {
            let $id = event.target.id;
            if ($id != "results1") {
                jQuery('#results1').hide();
                jQuery('#results2').empty();
                jQuery('#results2').show();
                jQuery('#popup_back').empty();
                $.ajax({
                    type: "GET",
                    url: "https://musicbrainz.org/ws/2/release-group/" + $id
                        + "?inc=artist-credits%2Breleases+tags+media",
                    dataType: "xml",
                    success: showReleases
                });
            }
        });

        $("#results2").click(function(event) {
            let $id = event.target.id;
            if ($id != "mb" && $id != "results2") {
                jQuery('#results2').empty();
                jQuery('#results2').show();
                $.ajax({
                    type: "GET",
                    url: "https://musicbrainz.org/ws/2/release/" + $id
                        + "?inc=artist-credits%2Blabels%2Bdiscids%2Brecordings+tags+media+label-rels",
                    dataType: "xml",
                    success: populateForm
                });
            }
        });

        $("#popup_back").click(function(event) {
            let $id = event.target.id;
            if ($id == "back" ) {
                jQuery('#results2').hide();
                jQuery('#results1').show();
                jQuery('#popup_back').empty();
                jQuery('#popup_title').text("Choose Release Group");
            }
        });

        $("#remaster").click(function() {
            if ($("#remaster").attr("checked") && $searched == true) {
                populateEditionsForm();
            } else if ($searched == true) {
                depopulateEditionsForm();
            }

        });
    });

    /**
     * Shows the release groups
     * @param xml
     */
    function showReleaseGroups(xml) {
        if ($(xml).find("release-group-list").attr("count") == 0 ) {
            alert("Could not find on MusicBrainz");
        } else {
            jQuery('#popup_title').text("Choose release group");
            openPopup();
        }

        $(xml).find("release-group").each(function() {
            let $releaseId = $(this).attr("id");
            let $result = $(this).find("name:first").text() + " - "
                + $(this).find("title:first").text() + " [Type: "
                + $(this).attr("type") + ", Score: " + $(this).attr("ext:score")
                + "]";
            $('<a href="#null">' + $result + "<p />").attr("id", $releaseId).appendTo("#results1");
        });
    }

    /**
     * Shows releases inside a release group
     * @param xml
     */
    function showReleases(xml) {
        let $date_release_group = $(xml).find("first-release-date").text();
        $release_type     = $(xml).find("release-group").attr("type");
        $release_group_id = $(xml).find("release-group").attr("id");
        $year_original    = $date_release_group.substring(0,4);

        jQuery('#popup_title').html("Choose release " + '<a href="https://musicbrainz.org/release-group/'
                + $release_group_id + '" target="_new" class="brackets">View on MusicBrainz</a>');
        jQuery('#popup_back').html('<a href="#null" id="back" class="brackets">Go back</a>');

        $(xml).find("release").each(function() {
            let $format;
            let $tracks;
            $(this).find("medium-list").each(function() {
                $(this).find("medium").each(function() {
                    $format = $(this).find("format").text();
                    $(this).find("track-list").each(function() {
                        $tracks = $(this).attr("count");
                    });
                });
            });
            let $date   = $(this).find("date").text();
            let $result = $(this).find("title").text() + " [Year: "
                + $date.substring(0,4) + ", Format: " + $format + ", Tracks: "
                + $tracks + ", Country: " + $(this).find("country").text()
                + "]";
            let $release_id = $(this).attr("id");
            $('<a href="#null">' + $result + "</a>").attr("id", $release_id).appendTo("#results2");
            $('<a href="https://musicbrainz.org/release/' + $release_id
                + '" target="_new" class="brackets">View on MusicBrainz</a>'
                + "<p />"
            ).attr("id", "mb").appendTo("#results2");
        });

        parseTags(xml);
    }

    function isValidTag(name) {
        // relevancy of contents to be reviewed
        return [
            '1950s', '1960s', '1970s', '1980s', '1990s', '2000s', '2010s', '8bit',
            'abstract', 'acid', 'acid.jazz', 'acoustic', 'alternative',
            'alternative.country', 'alternative.rock', 'ambient', 'americana',
            'anime', 'apps.mac', 'apps.sound', 'apps.windows', 'audio.books',
            'australia', 'avant.garde', 'baroque', 'bass', 'beats', 'big.band',
            'black.metal', 'bluegrass', 'blues', 'blues.rock', 'brazil',
            'brazilian', 'breakbeat', 'breakcore', 'breaks', 'britpop', 'canada',
            'canadian', 'celtic', 'chanson', 'chillout', 'chillwave', 'chiptune',
            'christian', 'christmas', 'classic.rock', 'classical', 'club',
            'clubhouse', 'comedy', 'comics', 'contemporary', 'country', 'covers',
            'dance', 'dancehall', 'dark.ambient', 'darkwave', 'dc', 'dc.comics',
            'death.metal', 'deep.house', 'deutsche.grammophon', 'disco', 'doom',
            'doom.metal', 'downtempo', 'dream.pop', 'drone', 'drum.and.bass', 'dub',
            'dub.techno', 'dubstep', 'dutch', 'easy.listening', 'ebm', 'ebooks',
            'ebooks.fiction', 'ebooks.non.fiction', 'elearning.videos', 'electro',
            'electro.house', 'electronic', 'electronica', 'emo', 'epub',
            'euro.house', 'eurodance', 'experimental', 'fantasy', 'female.vocalist',
            'fiction', 'finland', 'finnish', 'folk', 'folk.rock', 'france',
            'free.improvisation', 'free.jazz', 'freely.available', 'french', 'funk',
            'fusion', 'future.jazz', 'game', 'garage', 'german', 'germany',
            'glitch', 'goa.trance', 'gospel', 'gothic', 'gothic.metal', 'grime',
            'grindcore', 'grunge', 'guitar', 'hard.rock', 'hard.trance', 'hardcore',
            'hardcore.dance', 'hardstyle', 'heavy.metal', 'hebrew', 'hip.hop',
            'holiday', 'house', 'idm', 'indie', 'indie.pop', 'indie.rock',
            'industrial', 'instrumental', 'irish', 'israeli', 'italian',
            'italo.disco', 'jam', 'jam.band', 'japan', 'japanese', 'jazz', 'jpop',
            'jrock', 'jungle', 'korean', 'kpop', 'krautrock', 'latin', 'leftfield',
            'live', 'lo.fi', 'lounge', 'magazine', 'marvel', 'mashup', 'math.rock',
            'melodic.death.metal', 'metal', 'metalcore', 'minimal', 'mobi',
            'modern.classical', 'mpb', 'neofolk', 'new.age', 'new.wave',
            'new.zealand', 'noise', 'noise.rock', 'non.fiction', 'nonfiction',
            'norway', 'norwegian', 'nu.disco', 'nu.metal', 'opera', 'pdf', 'piano',
            'pop', 'pop.punk', 'pop.rock', 'portugal', 'post.bop', 'post.hardcore',
            'post.punk', 'post.rock', 'power.metal', 'power.pop', 'progressive',
            'progressive.house', 'progressive.metal', 'progressive.rock',
            'progressive.trance', 'psychedelic', 'psychedelic.rock', 'psytrance',
            'punk', 'punk.rock', 'quebec', 'rave', 'reggae', 'remix',
            'rhythm.and.blues', 'rock', 'rock.and.roll', 'rockabilly', 'roots',
            'russian', 'samples', 'science', 'science.fiction', 'score', 'screamo',
            'sheet.music', 'shoegaze', 'singer.songwriter', 'ska', 'sludge', 'soul',
            'soundtrack', 'southern.rock', 'space.rock', 'spanish', 'stoner',
            'stoner.rock', 'surf', 'sweden', 'swedish', 'swing', 'synth.pop',
            'tech.house', 'techno', 'thrash.metal', 'trance', 'tribal', 'trip.hop',
            'uk', 'uk.garage', 'underground', 'united.kingdom', 'united.states',
            'vanity.house', 'video.game', 'vocal', 'world', 'world.music',
        ].includes(name);
    }

    /**
     * Parses the tags to the gazelle conventions
     * @param xml
     */
    function parseTags(xml) {
        $tags = "";
        $(xml).find("tag").each(function() {
            let $tag = cleanTag($(this).find("name").text());
            if (isValidTag($tag)) {
                $tags += "," + $tag;
            }
        });
        if ($tags.charAt(0) == ',') {
            $tags = $tags.substring(1);
        }
    }

    function cleanTag($t) {
        $t = $t.replace(/ +(?= )/g,',');
        $t = $t.replace('-','.');
        $t = $t.replace(' ','.');
        return $t;
    }

    /**
     * Populates the upload form
     * @param xml
     */
    function populateForm(xml) {
        let $date       = $(xml).find("release").find("date").text();
        $year_release   = $date.substring(0,4);
        $catalog_number = $(xml).find("catalog-number").text();
        $record_label   = $(xml).find("label").find("sort-name").text();

        let $track_count  = $(xml).find("track-list").attr("count");
        let $track_titles = new Array();
        $(xml).find("track-list").find("title").each(function() {
            $track_titles.push($(this).text());
        });

        let $asin        = $(xml).find("asin").text();
        let $amazon_link = ($asin.length > 0)
            ? "[url=http://www.amazon.com/exec/obidos/ASIN/" + $asin + "]Amazon[/url]" + "\n"
            : "";
        let $country = $(xml).find("country").text();
        let $country_text = ($country.length > 0)
            ? "Country: " + $country + "\n"
            : "";
        let $barcode = $(xml).find("barcode").text();
        let $barcode_text = ($barcode.length > 0)
            ? "Barcode: " + $barcode + "\n"
            : "";

        let $description = $amazon_link
            + "[url=https://musicbrainz.org/release-group/" + $release_group_id
            + "]MusicBrainz[/url]" + "\n\n" + $country_text + $barcode_text
            + "Tracks: " + $track_count + "\n\n" + "Track list:" + "\n";
        for (let i = 0; i < $track_titles.length; i++) {
            $description = $description + "[#]" + $track_titles[i] + "\n";
        };

        closePopup();
        clear();

        $("#artist_0").val($(xml).find("artist-credit:first").find("name:first").text());
        $("#title").val($(xml).find("release").find("title:first").text());
        $("#year").val($year_original);
        $("#record_label").val($record_label);
        $("#catalogue_number").val($(xml).find("catalog-number").text());
        $("#tags").val($tags);
        $("#releasetype").val(getReleaseType());
        $("#album_desc").val($description);
    }

    function populateEditionsForm() {
        $('#remaster_true').show();
        $("#record_label").val("");
        $("#catalogue_number").val("");
        $("#remaster_year").val($year_release);
        $("#remaster_record_label").val($record_label);
        $("#remaster_catalogue_number").val($catalog_number);
    }

    function depopulateEditionsForm() {
        $("#record_label").val($record_label);
        $("#catalogue_number").val($catalog_number);
        $("#remaster_year").val("");
        $("#remaster_record_label").val("");
        $("#remaster_catalogue_number").val("");
    }

    function closeEditionsForm() {
        if ($("#remaster").attr("checked")) {
            $('#remaster_true').hide();
        }
        $("#remaster").attr("checked", false);
        $("#remaster_year").val("");
        $("#remaster_record_label").val("");
        $("#remaster_catalogue_number").val("");
    }

    /**
     * Gets the release type
     * @returns value of type
     */
    function getReleaseType() {
        let $value;
        switch ($release_type) {
            case "Album":
                $value = 1;
                break;
            case "Soundtrack":
                $value = 3;
                break;
            case "EP":
                $value = 5;
                break;
            case "Compilation":
                $value = 7;
                break;
            case "Single":
                $value = 9;
                break;
            case "Live":
                $value = 11;
                break;
            case "Remix":
                $value = 13;
                break;
            case "Interview":
                $value = 15;
                break;
            default:
                $value = "---";
                break;
        }
        return $value;
    }

    /**
     * Enables the musicbrainz button only when the "Music" type is selected
     * and a format isn't being uploaded.
     */
    function enableMusicBrainzButton() {
        if ($('#categories').is(':disabled') == false) {
            $("#categories").click(function() {
                if ($("#categories").val() != 0 ) {
                    $("#musicbrainz_button").attr("disabled", "disabled");
                } else {
                    $("#musicbrainz_button").removeAttr("disabled");
                }
            });
        } else {
            $("#musicbrainz_button").attr("disabled", "disabled");
        }
    }

    /**
     * Clears fields in the upload form
     */
    function clear() {
        closeEditionsForm();

        $("#artist_0").val("");
        $("#title").val("");
        $("#year").val("");
        $("#record_label").val("");
        $("#catalogue_number").val("");
        $("#tags").val("");
        $("#releasetype").val("");
        $("#album_desc").val("");
        $("#remaster_year").val("");
        $("#remaster_record_label").val("");
        $("#remaster_catalogue_number").val("");
    }

    /**
     * Loads the popup
     * @returns
     */
    function openPopup() {
        centerPopup();
        if ($popup_state == 0) {
            $("#popup_background").css({
                "opacity": "0.7"
            });
            $("#popup_background").fadeIn("fast");
            $("#musicbrainz_popup").fadeIn("fast");
            $popup_state = 1;
        }
    }

    /**
     * Closes the popup
     * @returns
     */
    function closePopup() {
        if ($popup_state == 1) {
            $("#popup_background").fadeOut("fast");
            $("#musicbrainz_popup").fadeOut("fast");
            jQuery('#popup_back').html("");
            $popup_state = 0;
        }
    }

    /**
     * Centers the popup on the screen
     * @returns
     */
    function centerPopup() {
        //TODO Center dynamically based on scroll position

        let windowWidth    = document.documentElement.clientWidth;
        let windowHeight   = document.documentElement.clientHeight;
        let popupWidth     = $("#musicbrainz_popup").width();

        $("#musicbrainz_popup").css({
            "position": "absolute ! important",
            "left": windowWidth / 2 - popupWidth / 2 + "! important"
        });
        $("#popup_background").css({
            "height": windowHeight
        });
    }

    /**
     * Controls the popup state based on user input
     * @returns
     */
    function controlPopup() {
        $("#popup_close").click(function() {
            closePopup();
        });
        $(document).keypress(function(e) {
            if (e.keyCode == 27 && $popup_state == 1) {
                closePopup();
            }
        });
    }

    function loadCSS() {
        let link = document.createElement('link');
        // TODO: FIX_STATIC_SERVER
        link.href = 'static/styles/musicbrainz.css';
        link.rel = 'stylesheet';
        link.type = 'text/css';
        document.body.appendChild(link);
    }

    })();
