<?php
/**
 * Plugin Name: Advanced RSS Feed Manager
 * Plugin URI: http://example.com/plugins/advanced-rss-feed-manager/
 * Description: A WordPress plugin for bulk importing RSS feeds, pagination, and displaying feeds with images in a card view.
 * Version: 1.0
 * Author: Prakash Pawar
 * Author URI: http://thevenice.in
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AdvancedRSSFeedManager {
    public function __construct() {
        // Initialize plugin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('display_rss_feeds', array($this, 'display_rss_feeds_shortcode'));
        
        // Debug: Add action to print a message in the footer
        add_action('wp_footer', function() {
            echo "<!-- Debug: AdvancedRSSFeedManager constructor called -->\n";
        });
    }

    public function add_admin_menu() {
        add_menu_page('RSS Feed Manager', 'RSS Feed Manager', 'manage_options', 'rss-feed-manager', array($this, 'admin_page'), 'dashicons-rss');
    }

    public function register_settings() {
        register_setting('rss_feed_manager_settings', 'rss_feed_manager_feeds');
        register_setting('rss_feed_manager_settings', 'rss_feed_manager_fallback_image');
        register_setting('rss_feed_manager_settings', 'rss_feed_manager_per_page');
        // {{ edit_1 }} Add setting for refetch interval
        register_setting('rss_feed_manager_settings', 'rss_feed_manager_refetch_interval');
    }

    public function admin_page() {
        // Admin page HTML
        ?>
        <div class="wrap">
            <h1>RSS Feed Manager</h1>
            <form method="post" action="options.php">
                <?php settings_fields('rss_feed_manager_settings'); ?>
                <?php do_settings_sections('rss_feed_manager_settings'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">RSS Feeds (Name, URL, Category)</th>
                        <td>
                            <textarea name="rss_feed_manager_feeds" rows="10" cols="50"><?php echo esc_textarea(get_option('rss_feed_manager_feeds')); ?></textarea>
                            <p class="description">Enter one feed per line in the format: Name, URL, Category</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Fallback Image URL</th>
                        <td>
                            <input type="text" name="rss_feed_manager_fallback_image" value="<?php echo esc_attr(get_option('rss_feed_manager_fallback_image')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Items Per Page</th>
                        <td>
                            <input type="number" name="rss_feed_manager_per_page" value="<?php echo esc_attr(get_option('rss_feed_manager_per_page', 10)); ?>" min="1" />
                        </td>
                    </tr>
                    <!-- // {{ edit_2 }} Add input for refetch interval -->
                    <tr valign="top">
                        <th scope="row">Refetch Interval (Minutes)</th>
                        <td>
                            <input type="number" name="rss_feed_manager_refetch_interval" value="<?php echo esc_attr(get_option('rss_feed_manager_refetch_interval', 60)); ?>" min="1" />
                            <p class="description">Set the interval for refetching data in minutes.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <?php
            // Debug: Display current saved feeds
            $current_feeds = get_option('rss_feed_manager_feeds');
            echo "<h2>Debug: Current Saved Feeds</h2>";
            echo "<pre>" . esc_html($current_feeds) . "</pre>";
            ?>
        </div>
        <?php
    }

    public function display_rss_feeds_shortcode($atts) {
        ob_start();
        
        $atts = shortcode_atts(array(
            'feeds' => '',
            'per_page' => get_option('rss_feed_manager_per_page', 10),
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
        ), $atts);
    
        $feeds = $atts['feeds'] ? explode(',', $atts['feeds']) : $this->get_feed_urls();
        $items = $this->fetch_feed_items($feeds);
        
        $total_items = count($items);
        $offset = ($atts['paged'] - 1) * $atts['per_page'];
        $paged_items = array_slice($items, $offset, $atts['per_page']);
    
        // Updated responsive CSS
        echo '<style>
            .rss-feed-container {
                display: grid;
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px;
            }
            .rss-feed-item {
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                transition: box-shadow 0.3s ease;
            }
            .rss-feed-item:hover {
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .rss-feed-item img {
                width: 100%;
                height: 200px;
                object-fit: cover;
            }
            .rss-feed-content {
                padding: 15px;
                flex-grow: 1;
                display: flex;
                flex-direction: column;
            }
            .rss-feed-title {
                font-size: 16px;
                margin-bottom: 10px;
            }
            .rss-feed-description {
                font-size: 14px;
                color: #666;
                flex-grow: 1;
            }
            .rss-feed-meta {
                font-size: 12px;
                color: #888;
                margin-top: 10px;
            }
            @media (min-width: 768px) {
                .rss-feed-container {
                    grid-template-columns: repeat(3, 1.5fr);
                }
                .rss-feed-title {
                    font-size: 18px;
                }
                .rss-feed-description {
                    font-size: 15px;
                }
                .rss-feed-meta {
                    font-size: 13px;
                }
            }
            @media (min-width: 1024px) {
                .rss-feed-title {
                    font-size: 20px;
                }
                .rss-feed-description {
                    font-size: 16px;
                }
                .rss-feed-meta {
                    font-size: 14px;
                }
                .rss-feed-item img {
                    height: 250px;
                }
            }
        </style>';
    
        echo '<div class="rss-feed-container">';
        if (empty($paged_items)) {
            echo '<p>No RSS feed items found.</p>';
        } else {
            foreach ($paged_items as $item) {
                echo '<div class="rss-feed-item">';
                if (!empty($item['image'])) {
                    echo '<img src="' . esc_url($item['image']) . '" alt="' . esc_attr($item['title']) . '" />';
                } else {
                    echo '<img src="' . esc_url(get_option('rss_feed_manager_fallback_image')) . '" alt="Fallback Image" />';
                }
                echo '<div class="rss-feed-content">';
                echo '<h3 class="rss-feed-title"><a href="' . esc_url($item['link']) . '" target="_blank">' . esc_html($item['title']) . '</a></h3>';
                echo '<p class="rss-feed-description">' . wp_trim_words($item['description'], 30) . '</p>';
                echo '<div class="rss-feed-meta">';
                echo '<span>Category: ' . esc_html($item['category']) . '</span><br>';
                echo '<span>Source: ' . esc_html($item['feed_name']) . '</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        }
        echo '</div>';
        
        echo $this->pagination($total_items, $atts['per_page'], $atts['paged']);
        
        return ob_get_clean();
    }

    private function get_feed_urls() {
        $feeds_option = get_option('rss_feed_manager_feeds');
        
        echo "<!-- Debug: RSS Feed Manager Feeds Option -->\n";
        // var_dump($feeds_option);
        
        if (empty($feeds_option)) {
            echo "<!-- Debug: No feeds found in WordPress options -->\n";
            // return array();
        }
        
        $feeds = explode("\n", $feeds_option);
        $urls = array();
        foreach ($feeds as $feed) {
            $parts = explode(',', trim($feed), 3); // Limit to 3 parts
            if (count($parts) >= 2) { // Allow feeds without a category
                $urls[] = array(
                    'name' => trim($parts[0]),
                    'url' => trim($parts[1]),
                    'category' => isset($parts[2]) ? trim($parts[2]) : 'Uncategorized'
                );
            } else {
                echo "<!-- Debug: Invalid feed format: " . esc_html($feed) . " -->\n";
            }
        }
        
        echo "<!-- Debug: Processed feed URLs -->\n";
        // var_dump($urls);
        
        return $urls;
    }

    private function fetch_feed_items($feed_urls) {
        $items = array();
        foreach ($feed_urls as $feed) {
            $url = $feed['url'];
            
            echo "<!-- Debug: Fetching feed from: " . esc_url($url) . " -->\n";
            
            $rss = fetch_feed($url);
            if (!is_wp_error($rss)) {
                $max_items = $rss->get_item_quantity(10);
                $rss_items = $rss->get_items(0, $max_items);
                foreach ($rss_items as $item) {
                    $items[] = array(
                        'title' => $item->get_title(),
                        'link' => $item->get_permalink(),
                        'description' => $item->get_description(),
                        'image' => $this->get_feed_image($item),
                        'category' => $feed['category'],
                        'feed_name' => $feed['name'],
                    );
                }
            } else {
                echo "<!-- Debug: Error fetching feed: " . esc_html($rss->get_error_message()) . " -->\n";
            }
        }
        return $items;
    }

    private function get_feed_image($item) {
        // Check for the enclosure
        if (method_exists($item, 'get_enclosure')) {
            $enclosure = $item->get_enclosure();
            if ($enclosure && method_exists($enclosure, 'get_thumbnail') && $enclosure->get_thumbnail()) {
                return $enclosure->get_thumbnail();
            }
        }

        // Check for media:content
        if (method_exists($item, 'get_media_content')) {
            $media_content = $item->get_media_content();
            if (!empty($media_content) && isset($media_content[0]['url'])) {
                return $media_content[0]['url'];
            }
        }

        // Check for <img> tags in the description
        if (method_exists($item, 'get_description')) {
            $description = $item->get_description();
            if ($description && preg_match('/<img[^>]+src="([^">]+)"/', $description, $matches)) {
                return $matches[1]; // Return the first image found
            }
        }

        // Check for <content:encoded> for images
        if (method_exists($item, 'get_content_encoded')) {
            $content_encoded = $item->get_content_encoded();
            if ($content_encoded && preg_match('/<img[^>]+src="([^">]+)"/', $content_encoded, $matches)) {
                return $matches[1]; // Return the first image found
            }
        }

        // Fallback: return a default image or empty string
        return ''; // You can replace this with a default image URL if desired
    }

    private function pagination($total_items, $per_page, $current_page) {
        $total_pages = ceil($total_items / $per_page);
        if ($total_pages <= 1) {
            return '';
        }

        $links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page
        ));

        return '<div class="rss-feed-pagination">' . $links . '</div>';
    }
}

$advanced_rss_feed_manager = new AdvancedRSSFeedManager();