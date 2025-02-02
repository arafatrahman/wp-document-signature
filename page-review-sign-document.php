<?php


/*
 * Template Name: Review and Sign Document
 * Template Post Type: page
 */

// Don't load anything if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}



// When the document is signed (button clicked), update the status and redirect
if (isset($_POST['finish_document'])) {
    $document_id = $_GET['document_id']; // Get the document ID from the URL
    wp_update_post([
        'ID' => $document_id,
        'post_status' => 'signed_document', // Change status to signed
    ]);
    // Update the document's custom field (meta)
    update_post_meta($document_id, 'document_signed', true);

    // Redirect to avoid resubmission of the form and show the new status
    wp_redirect(get_permalink($document_id)); // Redirect to the same document page
    exit;
}



$document_id = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;
if (!$document_id) {
    echo "Invalid document.";
    exit;
}

// Get the document content
$document = get_post($document_id);
if (!$document || $document->post_type !== 'document') {
    echo "Document not found.";
    exit;
}

// Get the document title and content
$document_title = get_the_title($document_id);
$document_content = apply_filters('the_content', $document->post_content); // The actual content of the document

// Output the content for review
echo "<div class='document-view'>
    <h2>" . esc_html($document_title) . "</h2>
    <div class='document-content'>" . $document_content . "</div>";

?>



<?php


// Get the 'document_signed' meta value for this document
$is_signed = get_post_meta($document_id, 'document_signed', true);

// Check if the document is signed, if not, display the "Finish Document" button
if ($document_id && !$is_signed) {
    ?>
    <form method="POST">
        <input type="submit" name="finish_document" value="Finish Document" />
    </form>
    <?php
} else {
    // If the document is already signed, display a message
    echo '<p>This document has already been signed.</p>';
}


