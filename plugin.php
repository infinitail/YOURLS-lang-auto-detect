<?php
/*
Plugin Name: Lang auto detect
Plugin URI: https://github.com/infinitail
Description: Automatic detection of the language setting from the web browser and changes the display language accordingly.
Version: 0.1
Author: Infinitail
Author URI: https://github.com/infinitail
*/

// No direct call
if( !defined('YOURLS_ABSPATH') ) die();

// Add hook
yourls_add_filter('get_locale', 'infinitail_lang_auto_detect');

/**
 * Automatic detect language setting from web browser and load .mo file
 *
 * @param string $yourls_locale
 * @return string
 */
function infinitail_lang_auto_detect(string $yourls_locale) {
    $headers = getallheaders();

    // No Accept-Language HTTP header found
    if (empty($headers['Accept-Language'])) {
        return $yourls_locale;
    }

    // Parse HTTP header value and get ordered list
    $preferred_langs = get_preferred_langs($headers['Accept-Language']);

    // Get most preferred language if mo file is available
    $preferred_lang = get_avaliable_most_preferred_lang($preferred_langs);
    if ($preferred_lang) {
        return $preferred_lang;
    }

    // No avaiable mo file from $preferred_langs values
    return $yourls_locale;
}

/**
 * Create ordered list from Accept-Language HTTP header value
 * ex. 'ja,en-US;q=0.7,en;q=0.3' -> ['ja', 'en-US', 'en']
 *
 * @param string $accept_lang
 * @return array
 */
function get_preferred_langs(string $accept_lang) {
    $weighted_lang_list = [];

    $langs = explode(',', $accept_lang);
    foreach ($langs as $lang) {
        list($lang_tag, $weight) = explode(';', $lang);

        if(is_null($weight)) {
            $weight = (int) (1.0 * 100);
        } else {
            $weight = (int) ((float) substr($weight, 2) * 100);     // ex. p=0.6 -> 60
        }

        $weighted_lang_list[$weight] = $lang_tag;
    }

    arsort($weighted_lang_list);
    $preferred_langs = array_values($weighted_lang_list);
    return $preferred_langs;
}

/**
 * Check YOURLS languages dir and translation file exist by lang list's order
 *
 * @param array $preferred_langs
 * @param string|false
 */
function get_avaliable_most_preferred_lang(array $preferred_langs) {
    $available_langs = yourls_get_available_languages();
    $available_langs[] = 'en';  // Add default

    foreach ($preferred_langs as $preferred_lang) {
        // Find exact match
        if (in_array($preferred_lang, $available_langs)) {
            infinitail_load_textdomain($preferred_lang);
            return $preferred_lang;
        }

        // Find partial match like 'ja_JP' and 'ja'
        foreach ($available_langs as $available_lang) {
            $lang_partial = locale_get_primary_language($available_lang);
            if ($lang_partial === $preferred_lang) {
                infinitail_load_textdomain($available_lang);
                return $available_lang;
            }
        }
    }

    return false;
}

/**
 * Load specified lang's .mo file
 * (I cant understand why yourls_load_default_textdomain() doesnt work as expected...)
 *
 * @param string $lang
 * @return bool
 */
function infinitail_load_textdomain(string $lang) {
    return yourls_load_textdomain('default', __DIR__.'/../../languages/'.$lang.'.mo');
}