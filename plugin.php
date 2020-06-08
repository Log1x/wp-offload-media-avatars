<?php

/**
 * Plugin Name: WP Offload Media Avatars
 * Plugin URI:  https://github.com/log1x/wp-offload-media-avatars
 * Description: WP Offload Media integration for local avatar plugins.
 * Version:     1.0.0
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
            Integration_Manager::get_instance()->register_integration(
                new class($plugin) extends Integration {
                    /**
                     * Check if WP User Avatars or Simple Local Avatar is currently
                     * installed and activated.
                     *
                     * @return bool
                     */
                    public function is_installed() {
                        return function_exists('_wp_user_avatars') || function_exists('get_simple_local_avatar');
                    }

                    /**
                     * Fix the improperly returned s3:// protocol when fetching
                     * a users avatar image.
                     *
                     * @param  string $image
                     * @return string
                     */
                    public function init() {
                        foreach (['get_avatar', 'simple_local_avatar'] as $filter) {
                            add_filter($filter, function ($image) {
                                return str_replace(
                                    home_url('s3://'),
                                    (is_ssl() || (bool) $this->as3cf->get_setting('force-https')) ? 'https://' : 'http://',
                                    $image
                                );
                            });
                        }
                    }
                }
            );
        });
    }
});
