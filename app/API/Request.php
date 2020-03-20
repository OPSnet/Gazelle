<?php

namespace Gazelle\API;

class Request extends AbstractAPI {
    public function run() {
        if (!isset($_GET['request_id'])) {
            json_error('Missing request id');
        }

        $request = \Requests::get_request($_GET['request_id']);
        if ($request === false) {
            json_error('Request not found');
        }
        $artists = \Requests::get_artists($_GET['request_id']);
        $request['Artists'] = $artists;
        $request['DisplayArtists'] = \Artists::display_artists($artists, false, false, false);
        $request['Category'] = $this->config['Categories'][$request['CategoryID'] - 1];

        return $request;
    }
}
