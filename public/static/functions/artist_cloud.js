/* global TagCanvas */

(function() {
    const LIMIT    = 15;
    let artistId   = false;
    let artistName = false;
    let artistTags = false;

    function flipView(e) {
        let view1 = document.getElementById('flip_view_1');
        if (view1.style.display == 'none') {
            view1.style.display = 'block';
            document.getElementById('flip_view_2').style.display = 'none';
            document.getElementById('flipper_title').innerHTML = 'Similar Artist Map';
            document.getElementById('flip_to').innerHTML = 'Switch to cloud';
        } else {
            view1.style.display = 'none';
            document.getElementById('flip_view_2').style.display = 'block';
            document.getElementById('flipper_title').innerHTML = 'Similar Artist Cloud';
            document.getElementById('flip_to').innerHTML = 'Switch to map';
        }
        e.preventDefault();
    }

    async function load_canvas() {
        add_main_artist(artistName);
        const response = await fetch(
            'ajax.php?action=similar_artists&id=' + artistId
                + '&limit=' + LIMIT
        );
        const data = await response.json();
        let ratio = false;
        data.response.forEach((similar) => {
            if (ratio === false) {
                ratio = similar['score'] / 300;
            }
            add_artist(
                similar['artist_id'],
                similar['name'],
                Math.min(150, similar['score'] / ratio)
            );
        });
        try {
            TagCanvas.Start(
                'similarArtistsCanvas',
                'artistTags',
                {
                    wheelZoom: false,
                    freezeActive: true,
                    weightSize: 0.15,
                    interval: 20,
                    textFont: null,
                    textColour: null,
                    textHeight: 25,
                    outlineColour: '#f96',
                    outlineThickness: 4,
                    maxSpeed: 0.04,
                    minBrightness: 0.1,
                    depth: 0.92,
                    pulsateTo: 0.2,
                    pulsateTime: 0.75,
                    initial: [0.1,-0.1],
                    decel: 0.98,
                    reverse: true,
                    shadow: '#ccf',
                    shadowBlur: 3,
                    weight : true,
                    weightFrom: 'data-weight'
                }
            );
        } catch (e) {
            console.error(e);
            document.getElementById('flip_view_2').style.display = 'none';
        }
    }

    function add_main_artist(name) {
        let current = document.getElementById('currentArtist');
        current.getAttribute('href', 'artist.php?id=' + artistId);
        current.text = artistName;
        add_artist(artistId, name, 350);
    }

    function add_artist(id, name, score) {
        let item = document.createElement('li');
        item.innerHTML = '<a style="color:#007DC6;" data-weight="' + score
            + '">' + name + '</a>';
        item.addEventListener('click', (e) => {
            e.preventDefault();
            reinit(id, name);
        });
        artistTags.append(item);
    }

    function reinit(id, name) {
        artistId   = id;
        artistName = name;
        artistTags.innerHTML = '';
        load_canvas();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('currentArtist').text = '';
        artistTags = document.querySelectorAll('#artistTags ul')[0];
        artistName = document.querySelectorAll('h2')[0].textContent;
        artistId   = window.location.search.split("?id=")[1];
        load_canvas();
        document.getElementById('flip_to').addEventListener('click', (e) => {
            flipView(e);
        });
    });
})();
