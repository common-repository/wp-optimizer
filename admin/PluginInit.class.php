<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPOptimizer\core;

use WPS\core\Rewriter;
use WPS\core\StringHelper;
use WPS\core\UtilEnv;

/**
 * Main class, used to set up the plugin
 */
class PluginInit
{
    private static ?PluginInit $_instance;

    /**
     * Holds the plugin base name
     */
    private string $plugin_basename;

    public ?PagesHandler $pages_handler;

    private function __construct()
    {
        $this->plugin_basename = UtilEnv::plugin_basename(WPOPT_FILE);

        if (is_admin()) {
            $this->register_actions();
            $this->do_welcome();
        }

        $this->load_textdomain();

        wps_maybe_upgrade('wpopt', WPOPT_VERSION, WPOPT_ADMIN . "upgrades/");
    }

    private function register_actions(): void
    {
        // Plugin Activation/Deactivation.
        register_activation_hook(WPOPT_FILE, array($this, 'plugin_activation'));
        register_deactivation_hook(WPOPT_FILE, array($this, 'plugin_deactivation'));

        add_filter("plugin_action_links_$this->plugin_basename", array($this, 'extra_plugin_link'), 10, 2);
        add_filter('plugin_row_meta', array($this, 'donate_link'), 10, 4);
    }

    /**
     * Loads text domain for the plugin.
     */
    private function load_textdomain(): void
    {
        $locale = apply_filters('wpopt_plugin_locale', get_locale(), 'wpopt');

        $mo_file = "wpopt-$locale.mo";

        if (load_textdomain('wpopt', WP_LANG_DIR . '/plugins/wp-optimizer/' . $mo_file)) {
            return;
        }

        load_textdomain('wpopt', UtilEnv::normalize_path(WPOPT_ABSPATH . 'languages/', true) . $mo_file);
    }

    public static function getInstance(): PluginInit
    {
        if (!isset(self::$_instance)) {
            return self::Initialize();
        }

        return self::$_instance;
    }

    public static function Initialize(): PluginInit
    {
        self::$_instance = new static();

        /**
         * Keep Ajax requests fast:
         * if doing ajax : load only ajax handler and return
         */
        if (wp_doing_ajax()) {

            /**
             * Instancing all modules that need to interact in the Ajax process
             */
            wps('wpopt')->moduleHandler->setup_modules('ajax');
        }
        elseif (wp_doing_cron()) {

            /**
             * Instancing all modules that need to interact in the cron process
             */
            wps('wpopt')->moduleHandler->setup_modules('cron');
        }
        elseif (is_admin()) {

            require_once WPOPT_ADMIN . 'PagesHandler.class.php';

            /**
             * Load the admin pages handler and store it here
             */
            self::$_instance->pages_handler = new PagesHandler();

            /**
             * Instancing all modules that need to interact in admin area
             */
            wps('wpopt')->moduleHandler->setup_modules('admin');
        }
        else {

            /**
             * Instancing all modules that need to interact only on the web-view
             */
            wps('wpopt')->moduleHandler->setup_modules('web-view');
        }

        /**
         * Instancing all modules that need to be always loaded
         */
        wps('wpopt')->moduleHandler->setup_modules('autoload');

        return self::$_instance;
    }

    /**
     * What to do when the plugin on plugin activation
     *
     * @param boolean $network_wide Is network wide.
     * @return void
     */
    public function plugin_activation($network_wide)
    {
        wps('wpopt')->settings->activate();

        wps('wpopt')->cron->activate();

        /**
         * Hook for the plugin activation
         */
        do_action('wpopt-activate');

        wps('wpopt')->settings->update('do_welcome', time(), true);
    }

    /**
     * What to do when the plugin on plugin deactivation
     */
    public function plugin_deactivation($network_wide): void
    {
        global $wp_version;

        if (wps('wpopt')->settings->get('tracking.usage', true)) {

            $mail_content = StringHelper::stringBuilder(
                "Details:",
                "Settings: " . maybe_serialize(wps('wpopt')->settings->get()),
                "Conf: PHP:" . PHP_VERSION . ", WP:$wp_version",
                "\nAutomatically sent message by wps framework."
            );

            if (wps_core()->online) {
                wp_mail('dev.sh1zen@outlook.it', 'WPOPT uninstall report ' . wps_domain(), $mail_content);
            }
        }

        wps('wpopt')->cron->deactivate();

        /**
         * Hook for the plugin deactivation
         */
        do_action('wpopt-deactivate');
    }

    /**
     * Add donate link to plugin description in /wp-admin/plugins.php
     *
     * @param array $plugin_meta
     * @param string $plugin_file
     * @param string $plugin_data
     * @param string $status
     * @return array
     */
    public function donate_link($plugin_meta, $plugin_file, $plugin_data, $status): array
    {
        if ($plugin_file == $this->plugin_basename) {
            $plugin_meta[] = '&hearts; <a target="_blank" href="https://www.paypal.com/donate/?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+WP-Optimizer.&currency_code=EUR">' . __('Buy me a beer', 'wpopt') . ' :o)</a>';
        }

        return $plugin_meta;
    }

    /**
     * Add link to settings in Plugins list page
     *
     * @wp-hook plugin_action_links
     * @param $links
     * @param $file
     * @return mixed
     */
    public function extra_plugin_link($links, $file)
    {
        $links[] = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wpopt-modules-settings'),
            __('Settings', 'wpopt')
        );

        return $links;
    }

    private function do_welcome()
    {
        if(wps('wpopt')->settings->get('do_welcome', false)) {
            wps('wpopt')->settings->update('do_welcome', false, true);
            Rewriter::getInstance()->redirect(admin_url('admin.php?page=wp-optimizer&wpsargs=do_welcome:true'));
        }
    }
}
