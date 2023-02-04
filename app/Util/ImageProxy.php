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
        protected readonly \Gazelle\User $viewer) {
    }

    /**
     * Checks if a link's host is (not) good, otherwise displays an error.
     * @param string $url Link to an image
     */
    public function badHost(string $url): ?string {
        foreach (IMAGE_HOST_BANNED as $host) {
            if (stripos($url, (string) $host) !== false) {
                return $host;
            }
        }
        return null;
    }

    /**
     * Determine the image URL. This takes care of the image proxy and thumbnailing.
     * @param string $url
     * @param bool|string $check - accepts one of false, "avatar", "avatar2", or "donoricon"
     * @param bool|number $UserID - user ID for avatars and donor icons
     * @return string
     */
    public function process($url, bool|string $check = false, $UserID = false) {
        if (empty($url) || !$this->viewer->permitted('site_proxy_images')) {
            return $url;
        }
        $extra = '';
        if ($check && $UserID) {
            $extra = "&amp;userid=$UserID";
            if (in_array($check, ['avatar', 'avatar2', 'donoricon'])) {
                $extra .= "&amp;type=$check";
            }
        }
        return "image.php?c=1$extra&amp;i=" . urlencode($url);
    }

    /**
     * Cover art thumbnail in browse, on artist pages etc.
     */
    public function tgroupThumbnail(\Gazelle\TGroup $tgroup): string {
        if ($tgroup->image()) {
            $Src = $this->process($tgroup->image(), true);
            $Lightbox = $this->process($tgroup->image());
        } else {
            $Src = STATIC_SERVER . '/common/noartwork/' . strtolower($tgroup->categoryName()) . '.png';
            $Lightbox = $Src;
        }
        return "<img src=\"$Src\" width=\"90\" height=\"90\" alt=\"Cover\" onclick=\"lightbox.init('$Lightbox', 90)\" />";
    }
}
