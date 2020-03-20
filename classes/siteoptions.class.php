<?php

/**
 * Class to manage site options
 */
class SiteOptions {

    /**
     * Get a site option
     *
     * @param string $Name The option name
     * @param string $DefaultValue The value to default to if the name can't be found in the cache
     */
    public static function getSiteOption($Name, $DefaultValue) {
        $Value = G::$Cache->get_value('site_option_' . $Name);

        if ($Value === false) {
            G::$DB->prepared_query('SELECT Value FROM site_options WHERE Name = ?', $Name);
            list($Value) = G::$DB->next_record();
            if (isset($Value)) {
                G::$Cache->cache_value('site_option_' . $Name, $Value, 3600);
            }
        }
        return (is_null($Value) ? $DefaultValue : $Value);
    }
}
