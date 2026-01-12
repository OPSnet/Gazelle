<?php

namespace Gazelle\Util;

/**
 * ImageProxy class
 * There are a few uses, mixed up here.
 *  - check if the image hoster is allowed
 *  - prepare image urls to be curled through a proxy to prevent client IP addresses from leaking
 */
class ImageProxy {
    /**
     * Checks if a link's host is (not) good.
     * @param string $url Link to an image
     * @return null if good host, otherwise domain of the bad host
     */
    public static function badHost(string $url): ?string {
        return array_find(IMAGE_HOST_BANNED, fn ($host) => stripos($url, $host) !== false);
    }

    /**
     * Cover art thumbnail in browse, on artist pages etc.
     */
    public static function tgroupThumbnail(\Gazelle\TGroup $tgroup): string {
        $image = $tgroup->cover();
        return '<img src="' . html_escape(image_cache_encode($image, height: 150, width: 150))
            . '" width="90" height="90" loading="lazy" alt="Cover" onclick="lightbox.init(\'' . html_escape(image_cache_encode($image))
            . '\', 90)" data-origin-src="' . html_escape($image) . '">';
    }
}
