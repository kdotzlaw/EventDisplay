<?php
/**
 * Plugin Name: Events Display
 * Description: Displays upcoming events from Events Manager 7.0
 * Version: 1.0.0
 * Author: Katrina Dotzlaw
 */

    // Prevent direct access
    if (!defined('ABSPATH')) {
        exit;
    }
    
    // Define plugin constants
    define('EMCD_VERSION', '1.0.0');
    define('EMCD_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('EMCD_PLUGIN_PATH', plugin_dir_path(__FILE__));
    
    /**
     * Main Plugin Class
     */
    class EventsDisplay {
        //Constructor
        public function __construct() {
            add_action('init', array($this, 'init'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        }//end constructor

        //Initialize the plugin
        public function init() {
             // Load text domain for translations
            load_plugin_textdomain('em-custom-display', false, dirname(plugin_basename(__FILE__)) . '/languages');
            // Register shortcode
            add_shortcode('upcoming_events', array($this, 'upcoming_events_shortcode'));
        }//end init

        //Enqueue plugin styles
        public function enqueue_styles() {
            wp_enqueue_style(
                'em-custom-display-styles',
                
                EMCD_PLUGIN_URL . 'assets/style.css',
                array(),
                EMCD_VERSION
            );
        }//end enqueue_styles

        //activation
        public function activate() {
            // Set default options
            add_option('emcd_default_limit', 3);
            add_option('emcd_card_color', '#13d2cd');
            add_option('emcd_show_time', 1);
            add_option('emcd_excerpt_length', 20);
        }//end activate

        //deactivation
        public function deactivate() {
            // Clean up if needed
        }//end deactivate

        //get upcoming events from database
        public function get_upcoming_events($limit = 5) {
            global $wpdb;
            
            // Check if Events Manager tables exist
            $table_name = $wpdb->prefix . 'em_events';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                return array();
            }
            
            $current_date = current_time('Y-m-d H:i:s');
            
            $events = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    p.ID,
                    p.post_title,
                    p.post_excerpt,
                    p.post_content,
                    em.event_start_date,
                    em.event_start_time,
                    em.event_end_date,
                    em.event_end_time
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->prefix}em_events em ON p.ID = em.post_id
                WHERE p.post_type = 'event'
                AND p.post_status = 'publish'
                AND CONCAT(em.event_start_date, ' ', IFNULL(em.event_start_time, '00:00:00')) >= %s
                ORDER BY em.event_start_date ASC, em.event_start_time ASC
                LIMIT %d
            ", $current_date, $limit));
            
            return $events;
        }//end get_upcoming_events

        // shortcode handler
        public function upcoming_events_shortcode($atts) {
            $atts = shortcode_atts(array(
                'limit' => get_option('emcd_default_limit', 3),
                'show_time' => get_option('emcd_show_time', 1),
                'card_color' => get_option('emcd_card_color', '#13d2cd'),
                'excerpt_length' => get_option('emcd_excerpt_length', 20)
            ), $atts);
            
            $events = $this->get_upcoming_events($atts['limit']);
            
            if (empty($events)) {
                return '<p class="em-no-events">' . __('No upcoming events found.', 'events-display') . '</p>';
            }
            
            ob_start();
            ?>
            <div class="emcd-events-container">
                <div class="emcd-events-scroll">
                    <?php foreach ($events as $event): 
                        // Format date
                        $event_date = new DateTime($event->event_start_date);
                        $day = $event_date->format('d');
                        $month = $event_date->format('M');
                        
                        // Create excerpt
                        $excerpt = '';
                        if (!empty($event->post_excerpt)) {
                            $excerpt = $event->post_excerpt;
                        } elseif (!empty($event->post_content)) {
                            $excerpt = wp_strip_all_tags($event->post_content);
                        }
                        
                        if (!empty($excerpt)) {
                            $words = explode(' ', $excerpt);
                            if (count($words) > $atts['excerpt_length']) {
                                $excerpt = implode(' ', array_slice($words, 0, $atts['excerpt_length'])) . '...';
                            }
                        }
                        
                        // Get event permalink for clickable cards
                        $event_url = get_permalink($event->ID);
                    ?>
                        <a href="<?php echo esc_url($event_url); ?>" class="emcd-event-card-link">
                            <div class="emcd-event-card">
                                <div class="emcd-event-date" style="background-color: <?php echo esc_attr($atts['card_color']); ?>;">
                                    <div class="emcd-date-content">
                                        <span class="emcd-event-day"><?php echo esc_html($day); ?></span>
                                        <span class="emcd-event-month"><?php echo esc_html($month); ?></span>
                                    </div>
                                </div>
                                <div class="emcd-event-content">
                                    <h3 class="emcd-event-title"><?php echo esc_html($event->post_title); ?></h3>
                                    <?php if ($excerpt): ?>
                                        <p class="emcd-event-excerpt"><?php echo esc_html($excerpt); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }//end shortcode handler
        
        //register plugin settings
         public function register_settings() {
            register_setting('emcd_settings', 'emcd_default_limit');
            register_setting('emcd_settings', 'emcd_card_color');
            register_setting('emcd_settings', 'emcd_show_time');
            register_setting('emcd_settings', 'emcd_excerpt_length');
        }//end register_settings

            public static function display_events($args = array()) {
            $instance = new self();
            echo $instance->upcoming_events_shortcode($args);
        }
    }//end event Display
    // Initialize the plugin
    new EventsDisplay();

    //helper function for theme integration
    function display_upcoming_events($args = array()) {
        EventsDisplay::display_events($args);
    } //end helper

    
?>