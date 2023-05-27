<?php

namespace Gazelle\Util;

class Zip {
    public static function make(string $title): \ZipStream\ZipStream {
        $options = new \ZipStream\Option\Archive;
        $options->setSendHttpHeaders(true);
        $options->setEnableZip64(false); // for macOS compatibility
        $options->setFlushOutput(true); // flush on each file to save on memory
        $options->setContentType('application/x-zip');
        $options->setDeflateLevel(8);

        return new \ZipStream\ZipStream(SITE_NAME . '-' . safeFilename($title) . '.zip', $options);
    }
}
