<?php
/**
 * Plugin Name: Cyberlife PDF and PPT User Post Plugin
 * Description: Converts the first page of uploaded PDF or PowerPoint files to a 150x150 pixel JPG image using the Zamzar API, creates a post, and sets the image as the WordPress featured image.
 * Version: 1.0
 * Author: Henry Shedrach
 * Author URI: https://wa.me/2348031975415
 */

// Include the Zamzar PHP library autoload file
require __DIR__ . '/vendor/autoload.php';

// Enqueue your plugin's styles
function cyberlife_enqueue_styles() {
    wp_enqueue_style('cyberlife-post', plugin_dir_url(__FILE__) . 'css/cyberlife-styles.css');
}
add_action('wp_enqueue_scripts', 'cyberlife_enqueue_styles');

use DevCoder\DotEnv;

$absolutePathToEnvFile = __DIR__ . '/.env';
(new DotEnv($absolutePathToEnvFile))->load();

// Define shortcode [submission_form]
add_shortcode('submission_form', 'ccp2j_render_submission_form');

// Hook to handle form submission
add_action('init', 'ccp2j_handle_form_submission');

// Function to render the submission form
function ccp2j_render_submission_form()
{
    // Display the form HTML here
    ob_start();
    ?>
    <div id="submit-alert" style="display: none;"></div>
    <form method="post" enctype="multipart/form-data">
        <input type="text" name="title" required placeholder="Enter Title" class="wf-title">

        <textarea name="description" required placeholder="Write your document description" class="wf-desc"></textarea>

        <input type="file" name="file" accept=".pdf, .ppt, .pptx, .pptm" required class="wf-upload">

        <input type="submit" name="submit" value="Submit" class="wf-submit">
    </form>
    <?php
    return ob_get_clean();
}

// Function to generate a unique filename
function generate_filename()
{
    // Get the current date and time
    $date = date('Y-m-d-H-i-s');

    // Generate a random string
    $random_string = md5(uniqid());

    // Combine the date and random string to create the filename
    $filename = $date . '-' . $random_string;

    return $filename;
}

// Function to handle form submission
function ccp2j_handle_form_submission()
{
    if (isset($_POST['submit'])) {
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);

        // Check if a file was uploaded
        if (!empty($_FILES['file']['name'])) {

            $file = $_FILES['file'];
            $upload_dir = wp_upload_dir(); // Get the WordPress upload directory
            $upload_path = $upload_dir['path'] . '/' . basename($file['name']);

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {

                $zamzar_api_key = getenv('ZAMZAR_API_KEY');

                if (!$zamzar_api_key) {
                    // Display an error message if the API key is not set
                    echo '<div class="error-message">Zamzar API key is missing. Please configure it.</div>';
                    return;
                }

                // Determine the file type based on the file extension
                $file_extension = pathinfo($upload_path, PATHINFO_EXTENSION);

                $viewer = '';
                if (in_array($file_extension, ['pdf', 'ppt', 'pptx', 'pptm'])) {
                    if ($file_extension === 'pdf') {
                        $viewer = 'adobe';
                    } else {
                        $viewer = 'microsoft';
                    }
                } else {
                    echo 'Unsupported file format. Please upload a PDF or PowerPoint file.';
                    return;
                }

                // Initialize the Zamzar API client with your API key
                $targetFormat = "jpg";

                $response = createZamzarJob($zamzar_api_key, $upload_path, $targetFormat);

                if (isset($response['id'])) {
                    $jobID = $response['id'];

                    // Poll Zamzar API until the job is no longer "converting"
                    $status = "converting";
                    while ($status === "converting") {
                        sleep(10); // Wait for 10 seconds before checking again
                        $job = checkZamzarJobStatus($zamzar_api_key, $jobID);
                        $status = $job['status'];

                        if ($status === 'successful') {
                            $fileID = $job['target_files'][0]['id'];
                            $localFilename = generate_filename() . ".jpg";

                            // Save the converted image to the "converted" folder within the plugin directory
                            $converted_folder = plugin_dir_path(__FILE__) . 'converted/';
                            if (!file_exists($converted_folder)) {
                                mkdir($converted_folder, 0755, true);
                            }
                            $converted_image_path = $converted_folder . $localFilename;

                            downloadZamzarFile($fileID, $converted_image_path, $zamzar_api_key, $localFilename);

                            // Create a new WordPress post with the embedded document

                            // Save the original uploaded document to the media library
                            $media_upload_result = wp_upload_bits(basename($file['name']), null, file_get_contents($upload_path));
                            if ($media_upload_result['error']) {
                                echo 'Error uploading the file to the media library: ' . $media_upload_result['error'];
                                return;
                            }

                            // Create a new WordPress post for the uploaded document
                            $post_content = $description . '[embeddoc url="' . $media_upload_result['url'] . '" width="950px" height="500px" download="none" viewer="' . $viewer . '"]';

                            $post_id = wp_insert_post([
                                'post_title' => $title,
                                'post_content' => $post_content,
                                'post_status' => 'publish', // Set the post status to "pending"
                                'post_type' => 'post',
                            ]);

                            ccp2j_set_featured_image($post_id, $converted_image_path);

                            // Display a success message in a modal
                            echo '<div id="success-modal" class="modal">
                                    <div class="modal-content">
                                        <span class="close">&times;</span>
                                        <p>Your submission was successful!</p>
                                    </div>
                                </div>';

                            break; // Exit the loop since we have a successful conversion
                        }
                    }
                } else {
                    echo "Failed to create Upload job because the file is too large and the execution time has been exceeded. Kindly upload a file size of below 10MB.";
                }
            } else {
                echo 'No file uploaded.';
            }
        }
    }
}

// Add JavaScript to handle modal display
function ccp2j_add_modal_script() {
    ?>
    <script>
        // Get the modal element
        var modal = document.getElementById("success-modal");

        // Get the close button for the modal
        var closeBtn = document.querySelector(".close");

        // Function to open the modal
        function openModal() {
            modal.style.display = "block";
        }

        // Function to close the modal
        function closeModal() {
            modal.style.display = "none";
        }

        // Close the modal when the close button is clicked
        closeBtn.addEventListener("click", closeModal);

        // Open the modal when the form submission is successful
        openModal();
    </script>
    <?php
}

// Add the modal script to your page
add_action('wp_footer', 'ccp2j_add_modal_script');

function createZamzarJob($apiKey, $sourceFilePath, $targetFormat)
{
    $endpoint = "https://api.zamzar.com/v1/jobs";

    if (function_exists('curl_file_create')) {
        $sourceFile = curl_file_create($sourceFilePath);
    } else {
        $sourceFile = '@' . realpath($sourceFilePath);
    }

    $postData = array(
        "source_file" => $sourceFile,
        "target_format" => $targetFormat
    );

    $ch = curl_init(); // Init curl
    curl_setopt($ch, CURLOPT_URL, $endpoint); // API endpoint
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true); // Enable the @ prefix for uploading files
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
    curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true);
}

function checkZamzarJobStatus($apiKey, $jobID)
{
    $cendpoint = "https://api.zamzar.com/v1/jobs/$jobID";

    $ch = curl_init(); // Init curl
    curl_setopt($ch, CURLOPT_URL, $cendpoint); // API endpoint
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
    curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
    $cbody = curl_exec($ch);
    curl_close($ch);

    return json_decode($cbody, true);
}

// Function to download Zamzar file and return image data
function downloadZamzarFile($fileID, $localFilename, $apiKey, $downloadFilename)
{
    $endpoint = "https://api.zamzar.com/v1/files/$fileID/content";

    $ch = curl_init(); // Init curl
    curl_setopt($ch, CURLOPT_URL, $endpoint); // API endpoint
    curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

    $fh = fopen($localFilename, "wb");
    curl_setopt($ch, CURLOPT_FILE, $fh);

    curl_exec($ch);
    curl_close($ch);

    fclose($fh);

    return $localFilename; // Return the path to the saved image
}

function ccp2j_set_featured_image($post_id, $image_url)
{
    // Check if the image URL is set
    if (!isset($image_url)) {
        return;
    }

    // Check if the post ID is valid
    if (!is_numeric($post_id)) {
        return;
    }

    // Download the image data
    $image_data = file_get_contents($image_url);

    // Create a unique filename for the image
    $filename = generate_filename() . '.jpg';

    // Specify the upload directory
    $upload_dir = wp_upload_dir();

    // Define the path to save the image
    $file_path = $upload_dir['path'] . '/' . $filename;

    // Save the image data to the file
    file_put_contents($file_path, $image_data);

    // Prepare the attachment data
    $attachment = [
        'post_mime_type' => 'image/jpeg',
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit',
        'post_parent' => $post_id,
    ];

    // Insert the attachment into the WordPress Media Library
    $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);

    // Generate attachment metadata
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Set the attachment as the featured image
    set_post_thumbnail($post_id, $attach_id);

    return $attach_id;
}