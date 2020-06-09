<?php

/**
 * Plugin Name: WP Offload Media Avatars
 * Plugin URI:  https://github.com/log1x/wp-offload-media-avatars
 * Description: WP Offload Media integration for local avatar plugins.
 * Version:     1.0.1
 * Author:      Brandon Nifong
 * Author URI:  https://github.com/log1x
 * Licence:     MIT
 */

use DeliciousBrains\WP_Offload_Media\Pro\Integration_Manager;
use DeliciousBrains\WP_Offload_Media\Pro\Integrations\Integration;

add_filter('plugins_loaded', new class {
    /**
     * Invoke the plugin.
     *
     * @return void
     */
    public function __invoke()
    {
        add_filter('as3cf_pro_init', function ($plugin) {
            if (! $plugin->is_plugin_setup() || ! $plugin->get_setting('serve-from-s3')) {
                return;
            }

            return Integration_Manager::get_instance()->register_integration(
                new class($plugin) extends Integration
                {
                    /**
                     * Determine if WP User Avatars or Simple Local Avatar is currently
                     * installed and activated.
                     *
                     * @return bool
                     */
                    public function is_installed()
                    {
                        return function_exists('_wp_user_avatars') || function_exists('get_simple_local_avatar');
                    }

                    /**
                     * Fix the improperly returned s3:// protocol when fetching
                     * a users avatar image.
                     *
                     * @param  string $avatar
                     * @return string
                     */
                    public function init()
                    {
                        foreach([
                            'get_avatar',
                            'get_avatar_url',
                            'simple_local_avatar'
                        ] as $filter) {
                            add_filter($filter, function ($avatar) {
                                if (! $this->contains($avatar, [
                                    home_url(),
                                    's3://',
                                    'gs://',
                                    str_replace(
                                        '-',
                                        '',
                                        $this->as3cf->get_setting('region')
                                    ) . '://',
                                ])) {
                                    return $avatar;
                                }

                                return $this->parse($avatar);
                            });
                        }
                    }

                    /**
                     * Parse and convert the URL within the provided image string into a
                     * valid provider URL if the image is available on the remote bucket.
                     *
                     * @param  string $string
                     * @return string
                     */
                    protected function parse($string = null)
                    {
                        if (empty($string)) {
                            return;
                        }

                        $original = $string;

                        if (
                            ! empty($tag = (new DOMXPath(@DOMDocument::loadHTML($string)))) &&
                            ! empty($tag = $tag->evaluate('string(//img/@src)'))
                        ) {
                            $string = $tag;
                        }

                        return apply_filters(
                            'as3cf_filter_post_local_to_provider',
                            str_replace($string, home_url(
                                parse_url(
                                    str_replace(home_url('/'), '', $string),
                                    PHP_URL_PATH
                                )
                            ), $original)
                        );
                    }

                    /**
                     * Determine if a given string contains a given substring.
                     *
                     * @param  string  $haystack
                     * @param  string|string[]  $needles
                     * @return bool
                     */
                    protected function contains($haystack, $needles)
                    {
                        foreach ((array) $needles as $needle) {
                            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                                return true;
                            }
                        }

                        return false;
                    }
                }
            );
        });
    }
});
