<?php

namespace Gazelle\Util;

/**
 * ImageProxy class
 * There are a few uses, mixed up here.s
 *  - check if the image hoster is allowed
 *  - prepare image urls to be curled through a proxy to prevent client IP addresses from leaking
 */
class ImageProxy {

    protected \Gazelle\User $viewer;

    public function setViewer(\Gazelle\User $viewer) {
        $this->viewer = $viewer;
        return $this;
    }

    /**
     * Checks if a link's host is (not) good, otherwise displays an error.
     * @param string $url Link to an image
     * @return string|null
     */
    public function badHost(string $url): ?string {
        foreach (IMAGE_HOST_BANNED as $host) {
            if (stripos($url, $host) !== false) {
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
    public function process($url, $check = false, $UserID = false) {
        if (empty($url) || (isset($this->viewer) && !$this->viewer->permitted('site_proxy_images'))) {
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
    public function thumbnail(string $url, int $CategoryID): string {
        if ($url) {
            $Src = $this->process($url, true);
            $Lightbox = $this->process($url);
        } else {
            $Src = STATIC_SERVER . '/common/noartwork/' . CATEGORY_ICON[$CategoryID - 1];
            $Lightbox = $Src;
        }
        return "<img src=\"$Src\" width=\"90\" height=\"90\" alt=\"Cover\" onclick=\"lightbox.init('$Lightbox', 90)\" />";
    }
}
