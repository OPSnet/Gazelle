<?php

authorize(true);

print
    json_encode(
        [
            'status' => 'success',
            'response' => [
                'loadAverage' => sys_getloadavg()
            ]
        ]
    );
