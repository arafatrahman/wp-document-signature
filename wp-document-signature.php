<?php
/*
Plugin Name: WP Document Signature
Description: Adds a custom post type "Documents" with menu items for "All Documents", "Add Documents", and a Settings page.
Version: 1.1
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class CustomDocumentsPlugin {
    
    public function __construct() {
        // Hook to register custom post type.
        add_action('init', [$this, 'register_documents_post_type']);
        
        // Add custom submenu under Documents.
        add_action('admin_menu', [$this, 'add_documents_submenu']);
        add_filter('views_edit-document', [$this, 'modify_documents_admin_views']);
        add_action('init', [$this, 'register_custom_post_statuses']);
        add_filter('post_row_actions', [$this, 'add_send_documents_action'], 10, 2);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_footer', [$this, 'add_send_documents_modal']);
        add_action('wp_ajax_send_documents', [$this, 'handle_send_documents_ajax']); // For logged-in users
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_custom_js']);
        add_filter('get_edit_post_link', [$this, 'modify_post_title_link'], 10, 2);
        add_filter('manage_document_posts_columns', [$this, 'add_sent_to_column']);
        add_action('manage_document_posts_custom_column', [$this, 'display_sent_to_column'], 10, 2);

   
    }

    public function display_sent_to_column($column, $post_id) {
        if ($column === 'sent_to') {
            $post = get_post($post_id);
    
            // Only display the "Sent To" info if the post is in "send_document" status
            if ($post->post_status === 'send_document') {
                // Retrieve the "Sent To" data from post meta
                $sent_to = get_post_meta($post_id, '_sent_to', true);
    
                if ($sent_to) {
                    echo esc_html($sent_to); // Display emails or names
                } else {
                    echo 'Not Sent'; // If no "Sent To" info, show "Not Sent"
                }
            } else {
                echo '-'; // If status is not "send_document", show a placeholder
            }
        }
    }

    // Add custom column in Documents admin table
public function add_sent_to_column($columns) {
    global $post_type, $post;



    // Only add "Sent To" column for "document" post type
    if ($_GET['post_status'] === 'send_document') {
        // Add the "Sent To" column
        $columns['sent_to'] = 'Sent To';
    }

    return $columns;
}

    public function modify_post_title_link($url, $post_id) {
        
        
        // Check if the post type is "document" and its status is "send_document"
        $post = get_post($post_id);
        if (isset($_GET['post_status'])) {
            // Change the link to the "View" link
            return get_permalink($post_id); // Link to the document's front-end view
        }
        return $url; // Otherwise, return the original link (which is the "Edit" link)
    }

  public function enqueue_admin_custom_js() {
        // Enqueue the custom JavaScript
        wp_enqueue_script('admin-custom-js', plugin_dir_url(__FILE__) . 'admin-custom.js', array('jquery'), null, true);
    
        // Localize script and pass the AJAX URL and other data
        wp_localize_script('admin-custom-js', 'ajax_obj', array(
            'ajaxurl' => admin_url('admin-ajax.php'), // Pass the AJAX URL
            'nonce'   => wp_create_nonce('send_documents_nonce_action') // Example of passing a nonce for security
        ));
    }


    public function handle_send_documents_ajax() {
        if (isset($_POST['data'])) {
            parse_str($_POST['data'], $parsed_data); // Parse the serialized string into an associative array
            
            if (!isset($parsed_data['send_documents_nonce']) || !wp_verify_nonce($parsed_data['send_documents_nonce'], 'send_documents_nonce_action')) {
                wp_send_json_error(array('message' => 'Nonce verification failed.'));
                return; // Prevent further processing if nonce fails
            }
    
            // Now we can process the data from $parsed_data
            $post_id = isset($parsed_data['post_id']) ? sanitize_text_field($parsed_data['post_id']) : '';
            $signers = isset($parsed_data['signers']) ? $parsed_data['signers'] : [];
    
            // Store the "Sent To" information as post meta
            $sent_to = [];
            foreach ($signers as $signer) {
                $sent_to[] = sanitize_text_field($signer['name']) . ' <' . sanitize_email($signer['email']) . '>';
            }
            update_post_meta($post_id, '_sent_to', implode(', ', $sent_to)); // Store as comma-separated list
        


            // Change the post status to 'send_document' after sending the email
            $document_post = get_post($post_id);
            if ($document_post) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'send_document'
                ]);
            }
    
            // Get the document title and description
            $document_title = get_the_title($post_id);
            $document_description = get_post_field('post_content', $post_id);
    
            // Process signers and send email
            foreach ($signers as $signer) {
                $name = sanitize_text_field($signer['name']);
                $email = sanitize_email($signer['email']);
    
                // Create the email subject and body
                $subject = "Review and Sign: " . $document_title;
                $body = "
                    <h3>Document: $document_title</h3>
                    <p>$document_description</p>
                    <p><a href='" . site_url() . "/review-sign-document?document_id=$post_id' target='_blank' style='background-color:#4CAF50;color:white;padding:10px;text-decoration:none;border-radius:5px;'>Review and Sign</a></p>
                ";
    
                // Send the email to the signer
                wp_mail($email, $subject, $body, [
                    'Content-Type: text/html; charset=UTF-8', // Ensure the email is sent as HTML
                ]);
            }
    
            // Return a success response
            wp_send_json_success(array('message' => 'Documents sent successfully!'));
        } else {
            wp_send_json_error(array('message' => 'No data received.'));
        }
    }
    
    
    

// Enqueue Bootstrap and Custom JS for Admin.
public function enqueue_admin_assets($hook) {
    // Only load on the Documents admin page.
    if ('edit.php' === $hook && isset($_GET['post_type']) && $_GET['post_type'] === 'document') {
        wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js', ['jquery'], null, true);
        wp_enqueue_script('custom-admin-js', plugin_dir_url(__FILE__) . 'admin-custom.js', ['jquery'], null, true);

    
    }
}

public function add_send_documents_modal() {
    global $post_type;
    if ($post_type === 'document') {
        ?>
        <div class="modal fade" style="margin-top:10%;" id="sendDocumentsModal" tabindex="-1" role="dialog" aria-labelledby="sendDocumentsModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="sendDocumentsModalLabel">Send Documents</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="send-documents-form">
                            <input type="hidden" name="post_id" id="modal-post-id" value="">
                            <input type="hidden" name="send_documents_nonce" value="<?php echo wp_create_nonce('send_documents_nonce_action'); ?>" />

                            <!-- Container for signers -->
                            <div id="signers-container">
                                <div class="row signer-row">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="signers[0][name]" placeholder="Name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="email" class="form-control" name="signers[0][email]" placeholder="Email" required>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger remove-signer d-none">✖</button>
                                    </div>
                                </div>
                            </div>
                            
                        </form>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="add-signer-btn">Add Signer</button>

                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="send-documents-submit">Send Documents</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
    


public function add_send_documents_action($actions, $post) {
    // Check if the post type is "document"
    
    if(isset($_GET['post_status']) && $_GET['post_status'] == 'send_document'){
        return [];
    }
    $actions['send_documents'] = '<a href="#" class="send-documents-button" data-toggle="modal" data-target="#sendDocumentsModal" data-post-id="' . $post->ID . '">Send Documents</a>';
    return $actions;
}

    

    // Register custom post statuses for "Send Documents" and "Signed Documents".
public function register_custom_post_statuses() {
    register_post_status('send_document', [
        'label'                     => 'Send Documents',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Send Documents (%s)', 'Send Documents (%s)'),
    ]);

    register_post_status('signed_document', [
        'label'                     => 'Signed Documents',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Signed Documents (%s)', 'Signed Documents (%s)'),
    ]);
}

public function modify_documents_admin_views($views) {
    global $wpdb;

    unset($views['publish']); // Remove "Published" from the list

    // Check if the custom post type is "document".
    if (get_post_type() === 'document') {
        // Change the "All" label to "All Documents".
        if (isset($views['all'])) {
            $views['all'] = str_replace('All', 'All Documents', $views['all']);
        }
    }



    $all_documents_count = (int) $wpdb->get_var("
            SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'document' 
            AND (post_status = 'publish' OR post_status = 'send_document' OR post_status = 'draft' AND post_status != 'trash')
    ");
    
    $send_documents_count = $wpdb->get_var("
        SELECT COUNT(ID)
        FROM $wpdb->posts
        WHERE post_type = 'document'
        AND post_status = 'send_document'
    ");
    
    $signed_documents_count = $wpdb->get_var("
        SELECT COUNT(ID)
        FROM $wpdb->posts
        WHERE post_type = 'document'
        AND post_status = 'signed_document'
    ");
    $trash_view = isset($views['trash']) ? $views['trash'] : '';

    $views = []; 
    // Update the views with counts
    $views['all'] = sprintf(
        '<a href="%s" %s>All Documents (%d)</a>',
        admin_url('edit.php?post_type=document'),
        (isset($_GET['post_status']) && $_GET['post_status'] === '') ? 'class="current"' : '',
        $all_documents_count
    );

    $views['send_documents'] = sprintf(
        '<a href="%s" %s>Send Documents (%d)</a>',
        admin_url('edit.php?post_type=document&post_status=send_document'),
        (isset($_GET['post_status']) && $_GET['post_status'] === 'send_document') ? 'class="current"' : '',
        $send_documents_count
    );

    $views['signed_documents'] = sprintf(
        '<a href="%s" %s>Signed Documents (%d)</a>',
        admin_url('edit.php?post_type=document&post_status=signed_document'),
        (isset($_GET['post_status']) && $_GET['post_status'] === 'signed_document') ? 'class="current"' : '',
        $signed_documents_count
    );

    // Add back the "Trash" link if it exists
    if ($trash_view) {
        $views['trash'] = $trash_view;
    }

    return $views;
}


    // Register the custom post type.
    public function register_documents_post_type() {
        $labels = [
            'name'               => 'Your Documents',
            'singular_name'      => 'Document',
            'menu_name'          => 'Documents',
            'name_admin_bar'     => 'Document',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Document',
            'new_item'           => 'New Document',
            'edit_item'          => 'Edit Document',
            'view_item'          => 'View Document',
            'all_items'          => 'All Documents',
            'search_items'       => 'Search Documents',
            'parent_item_colon'  => 'Parent Documents:',
            'not_found'          => 'No documents found.',
            'not_found_in_trash' => 'No documents found in Trash.',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true, // Keep it true so WordPress auto-generates the menu.
            'query_var'          => true,
            'rewrite'            => ['slug' => 'document'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-welcome-write-blog',
            'supports'           => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'],
            'show_in_rest'       => true,
        ];

        register_post_type('document', $args);
    }

    // Add custom submenu items under the default "Documents" menu.
    public function add_documents_submenu() {
        add_submenu_page(
            'edit.php?post_type=document', // Parent menu (auto-generated by WP)
            'Settings',                // Page title
            'Settings',                // Menu title
            'manage_options',          // Capability
            'documents-settings',          // Menu slug
            [$this, 'render_settings_page'] // Callback function
        );
    }

    // Render the settings page.
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Documents Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('documents_settings_group');
                do_settings_sections('documents-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin.
new CustomDocumentsPlugin();


