<?php

namespace Gazelle\Util;

/**
 * ImageProxy class
 * There are a few uses, mixed up here.
 *  - check if the image hoster is allowed
 *  - prepare image urls to be curled through a proxy to prevent client IP addresses from leaking
 */

class ImageProxy {
    public function __construct(
        protected readonly \Gazelle\User $viewer,
    ) {}

    /**
     * Checks if a link's host is (not) good, otherwise displays an error.
     * @param string $url Link to an image
     */
    public function badHost(string $url): ?string {
        foreach (IMAGE_HOST_BANNED as $host) { /** @phpstan-ignore-line */
            if (stripos($url, (string) $host) !== false) {
                return $host;
            }
        }
        return null;
    }

    /**
     * Cover art thumbnail in browse, on artist pages etc.
     */
    public function tgroupThumbnail(\Gazelle\TGroup $tgroup): string {
        $image = $tgroup->image() ?: STATIC_SERVER . '/common/noartwork/' . strtolower($tgroup->categoryName()) . '.png';
        return '<img src="' . html_escape(image_cache_encode($image, height: 150, width: 150))
            . '" width="90" height="90" alt="Cover" onclick="lightbox.init(\'' . html_escape(image_cache_encode($image))
            . '\', 90)" data-origin-src="' . html_escape($image) . '" />';
    }
}
