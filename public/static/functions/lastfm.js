(function() {
    var username;
    // How many entries to show per category before expanding
    var initialCount = 3;
    var lastPlayedTrack = "";
    var topArtists = "";
    var topAlbums = "";
    var topTracks = "";
    var expanded = false;
    $(document).ready(function () {
        // Avoid conflicting with other jQuery instances (userscripts et al).
//        $.noConflict(); // Why is this needed?
        // Fetch the username (appended from php) to base all get requests on.
        username = $('#lastfm_username').text();
        var div = $('#lastfm_stats');
        // Fetch the required data.
        // If data isn't cached, delays are issued in the class to avoid too many parallel requests to Last.fm
        getLastPlayedTrack(div);
        getTopArtists(div);
        getTopAlbums(div);
        getTopTracks(div);
        // Allow expanding the show information to more than three entries.
        // Attach to document as lastfm_expand links are added dynamically when fetching the data.
        $(document).on('click', "#lastfm_expand", function () {
            // Make hidden entries visible and remove the expand button.
            if ($(this).attr("href") == "#topartists") {
                topArtists = topArtists.replace(/\ class="hidden"/g,"");
                topArtists = topArtists.replace(/<li>\[<a\ href=\"#topartists.*\]<\/li>/,"");
            } else if ($(this).attr("href") == "#topalbums") {
                topAlbums = topAlbums.replace(/\ class="hidden"/g,"");
                topAlbums = topAlbums.replace(/<li>\[<a\ href=\"#topalbums.*\]<\/li>/,"");
            } else if ($(this).attr("href") == "#toptracks") {
                topTracks = topTracks.replace(/\ class="hidden"/g,"");
                topTracks = topTracks.replace(/<li>\[<a\ href=\"#toptracks.*\]<\/li>/,"");
            }
            updateDivContents(div);
        });
        // Allow expanding or collapsing the Last.fm data.
        $("#lastfm_expand").on('click', function () {
            if (expanded == false) {
                expanded = true;
                $(this).html("Show less info");
            } else {
                expanded = false;
                $(this).html("Show more info");
            }
            updateDivContents(div);
        });
        // Hide the reload button until data is expanded.
        $("#lastfm_reload_container").addClass("hidden");
        // Allow reloading the data manually.
        $.urlParam = function(name) {
            var results = new RegExp('[\\?&amp;]' + name + '=([^&amp;#]*)').exec(window.location.href);
            return results[1] || 0;
        }
        $("#lastfm_reload").on('click', function () {
            // Clear the cache and the necessary variables.
            $.get('user.php?action=lastfm&mode=flush&username=' + username + '&uid=' + $.urlParam('id'), function (response) {});
            lastPlayedTrack = "";
            topArtists = "";
            topAlbums = "";
            topTracks = "";
            // Revert the sidebar box to its initial state.
            $("#lastfm_stats").html("");
            //$(".box_lastfm").children("ul").append('<li id="lastfm_loading">Loading...</li>');
            $("#lastfm_stats").append('<li id="lastfm_loading">Loading...</li>');
            // Remove the stats reload button.
            $("#lastfm_reload_container").remove();
            getLastPlayedTrack(div);
            getTopArtists(div);
            getTopAlbums(div);
            getTopTracks(div);
        });
    });

    // Allow updating the sidebar element contents as get requests are completed.
    function updateDivContents(div) {
        // Pass all data vars, gets that haven't completed yet append empty strings.
        div.html(
            lastPlayedTrack + topArtists + topAlbums + topTracks + '<li id="lastfm_loading">Loading...</li>'
        );
        // If the data isn't expanded hide most of the info.
        if (expanded == false) {
            $("#lastfm_stats").children(":not(.lastfm_essential)").addClass("hidden");
            $("#lastfm_reload_container").addClass("hidden");
        } else {
            $("#lastfm_reload_container").removeClass("hidden");
        }
        // Once all requests are completed, remove the loading message.
        if (lastPlayedTrack && topArtists && topAlbums && topTracks) {
            $('#lastfm_loading').remove();
        }
    }

    // Escape ampersands with url code to avoid breaking the search links
    function escapeAmpUrl(input) {
        return input.replace(/&/g,"%26");
    }

    // Escape ampersands with html code to avoid breaking the search links
    function escapeHtml(input) {
        return input.replace(/&/g,"&#38;").replace(/</g,"&#60;");
    }

    function getLastPlayedTrack(div) {
        if (!username) {
            return;
        }
        $.get('user.php?action=lastfm&mode=last_track&username=' + username, function (response) {
            if (!response) {
                lastPlayedTrack = " ";
            } else {
                if (response == null) {
                    // No last played track available.
                    // Allow removing the loading message regardless.
                    lastPlayedTrack = " ";
                } else {
                    lastPlayedTrack = '<li class="lastfm_essential">Last played: <a href="artist.php?artistname='
                        + escapeAmpUrl(response['artist']) + '">' + escapeHtml(response['artist']) + '</a> - <a href="torrents.php?artistname='
                        + escapeAmpUrl(response['artist']) +'&filelist=' + escapeAmpUrl(response['name']) + '">' + escapeHtml(response['name']) + '</a></li>';
                }
            }
            updateDivContents(div);
        });
    }

    function topArtistEntry(info) {
        return '<li><a href="artist.php?artistname=' + escapeAmpUrl(info.name) + '">'
            + escapeHtml(info.name) + '</a> (<i>' + info.playcount + ')</i></li>';
   } 

    function getTopArtists(div) {
        if (!username) {
            return;
        }
        $.get('user.php?action=lastfm&mode=top_artists&username=' + username, function (response) {
            var html;
            if (!response) {
                topArtists = " ";
            } else {
                if (response.length == 0) {
                    // No top artists for the specified user, possibly a new Last.fm account.
                    // Allow removing the loading message regardless.
                    topArtists = " ";
                } else {
                    html = "<li>Top Artists:</li><li><ul class=\"nobullet\">";
                    n = Math.min(initialCount, 3);
                    for (var i = 0; i < n; i++) {
                        html += topArtistEntry(response[i]);
                    }
                    if (response.length > 3) {
                        for (i = 3; i < response.length; i++) {
                            html += topArtistEntry(response[i]);
                        }
                        html += '<li><a href="#topartists" id="lastfm_expand" onclick="return false" class="brackets">Expand</a></li>'
                    }
                    topArtists = html + "</ul></li>";
                }
            }
            updateDivContents(div);
        });
    }

    function topAlbumEntry(info) {
        return '<li><a href="artist.php?artistname=' + escapeAmpUrl(info.artist) + '">' + escapeHtml(info.artist)
            + '</a> - <a href="torrents.php?artistname=' + escapeAmpUrl(info.artist) + '&groupname=' + escapeAmpUrl(info.name)
            + '">' + escapeHtml(info.name) + '</a> <i>(' + info.playcount + ')</i></li>';
    }

    function getTopAlbums(div) {
        if (!username) {
            return;
        }
        $.get('user.php?action=lastfm&mode=top_albums&username=' + username, function (response) {
            var html;
            if (!response) {
                topAlbums = " ";
            } else {
                if (response.length == 0) {
                    topAlbums = " ";
                } else {
                    html = "<li>Top Albums:</li><li><ul class=\"nobullet\">";
                    n = Math.min(initialCount, 3);
                    for (var i = 0; i < n; i++) {
                        html += topAlbumEntry(response[i]);
                    }
                    if (response.length > 3) {
                        for (i = 3; i < response.length; i++) {
                            html += topAlbumEntry(response[i]);
                        }
                        html += '<li><a href="#topalbums" id="lastfm_expand" onclick="return false" class="brackets">Expand</a></li>';
                    }
                    topAlbums = html + "</ul></li>";
                }
            }
            updateDivContents(div);
        });
    }

    function topTrackEntry(info) {
        return '<li><a href="artist.php?artistname=' + escapeAmpUrl(info.artist) + '">' + escapeHtml(info.artist) + '</a> - <a href="torrents.php?artistname='
            + escapeAmpUrl(info.artist) + '&filelist=' + escapeAmpUrl(info.name) + '">' + escapeHtml(info.name) + '</a> <i>(' + info.playcount + ')</i></li>'
    }

    function getTopTracks(div) {
        if (!username) {
            return;
        }
        $.get('user.php?action=lastfm&mode=top_tracks&username=' + username, function (response) {
            var html;
            if (!response) {
                topTracks = " ";
            } else {
                if (response.length == 0) {
                    topTracks = " ";
                } else {
                    html = "<li>Top Tracks:</li><li><ul class=\"nobullet\">";
                    n = Math.min(initialCount, 3);
                    for (var i = 0; i < n; i++) {
                        html += topTrackEntry(response[i]);
                    }
                    if (response.length > 3) {
                        for (i = 3; i < response.length; i++) {
                            html += topTrackEntry(response[i]);
                        }
                        html += '<li><a href="#toptracks" id="lastfm_expand" onclick="return false" class="brackets">Expand</a></li>';
                    }
                    topTracks = html + '</ul></li>';
                }
            }
            updateDivContents(div);
        });
    }

})();
