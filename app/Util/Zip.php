<?php

namespace Gazelle\Util;

class Zip {
    public static function make(string $name): \ZipStream\ZipStream {
        return new \ZipStream\ZipStream(
            defaultDeflateLevel: 9,
            enableZip64: false,  // for macOS compatibility
            flushOutput: true,   // flush on each file to save on memory
            outputName: SITE_NAME . '-' . safeFilename($name) . '.zip',
        );
    }
}
