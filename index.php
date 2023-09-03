<?php
/**
 * Plugin Name: Cyberlife PDF and PPT to JPG Converter
 * Description: Converts the first page of uploaded PDF or PowerPoint files to a 150x150 pixel JPG image, creates a post, and sets the image as the featured image.
 * Version: 1.0
 * Author: Henry Shedrach +234 803 1975 415
 */
var_dump($response);

use \CloudConvert\CloudConvert;
use \CloudConvert\Models\Job;
use \CloudConvert\Models\Task;

// Define shortcode [submission_form]
add_shortcode('submission_form', 'ccp2j_render_submission_form');

// Hook to handle form submission
add_action('init', 'ccp2j_handle_form_submission');

// Function to render the submission form
function ccp2j_render_submission_form() {
    // Display the form HTML here
    ob_start();
    ?>
    <form method="post" enctype="multipart/form-data">
        <label for="title">Title</label>
        <input type="text" name="title" required>
        
        <label for="description">Brief Description</label>
        <textarea name="description" required></textarea>
        
        <label for="file">Upload File</label>
        <input type="file" name="file" accept=".pdf, .ppt, .pptx" required>
        
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
    return ob_get_clean();
}

// Function to handle form submission
function ccp2j_handle_form_submission() {
    if (isset($_POST['submit'])) {
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);

        // Check if a file was uploaded
        if (!empty($_FILES['file']['name'])) {
            // Handle file upload here
            $file = $_FILES['file'];

            // Check file type
            $allowed_types = [
                'application/pdf',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ];
            $file_type = mime_content_type($file['tmp_name']);

            if (in_array($file_type, $allowed_types)) {
                // Prepare data for CloudConvert API request
                $api_url = 'https://api.sandbox.cloudconvert.com/v2/jobs';
                $api_key = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiYTRjZDRmMDUyNWQ5MmRhMzZkODJkNzYwYWFjOTkwZjQzYWJkM2UxZGNjNTQ2NzY0ZDhkMWExODcwYWYzZmE2YWQxYmNiZjg1NGU5YzhjN2UiLCJpYXQiOjE2OTM2OTU4ODMuNzcyNDY2LCJuYmYiOjE2OTM2OTU4ODMuNzcyNDY4LCJleHAiOjQ4NDkzNjk0ODMuNzY5NTgyLCJzdWIiOiI2NDk0NzA2NSIsInNjb3BlcyI6WyJ1c2VyLnJlYWQiLCJ1c2VyLndyaXRlIiwidGFzay5yZWFkIiwidGFzay53cml0ZSIsIndlYmhvb2sucmVhZCIsIndlYmhvb2sud3JpdGUiLCJwcmVzZXQucmVhZCIsInByZXNldC53cml0ZSJdfQ.ZtzSJxFDjeRcb9vbZSK7YcgeHDXy0XxtiW-FCh9jIOaNzdpX4j_B7zFwI4z9za75ZwyUAPRQPErMtwFJiYNFb2WuTO6Z2uHMKq5hFlbzh7otrI4oji2NTJALPpcANzXLva2tv3jemoFLKKSGDQBnCnAN5kxKzttmaPNrXirBH-lP67tdLY6zkjilxCFlm-xioeUCRHrTuCvJZem-aTItU82jwKrV9oIwPoTOAHepZBtPzBVxXF3Nc-PdXnL6u_KiN0h8twfKegiHwyR02gPCUwlIOZJ7SFCKOTf9lzyrMVCv2MaVhW5GNDyM9FGcWqplfvyar58jXRqakt2OwC1A5Vhj_SuAV-KKcl72nYXxVQ03tg5yXs6eSeZUzPih_V78NIt2-rsgFM5ObQd63CMNb-uYdydEkQk-PxHfDxhrBHQVW_jfaoe1ACf-u7oMWRJ4TLh6SqmEJCBLLLvRVGIDy9PQtL7wqutd5fc0PZub8mwoehhyO2IO_9K57RDQI9n2ZNlPrMecwAu96dJLCCR_BlAYg9T_5ZBU_W6y15RMIVldD51NSiFazXvC2oYcZSZQqi-xgdSpJyhR7GgQDQzD2K8uHCEKFJH7OuABdk0A5c6t00QrhfCOVQ5QGKdXCvLiXcvWyk2ushR2nxamLVIPiU6W4EOHuZyP0bzIJefIxXo'; // Replace with your actual CloudConvert sandbox API key
                $input_format = 'pdf,pptx,ppt';
                $output_format = 'jpg';

                // Create the input file for CloudConvert
                $input_file = [
                    'name' => 'input.' . pathinfo($file['name'], PATHINFO_EXTENSION), // Adjust the filename
                    'file' => fopen($file['tmp_name'], 'r'), // Open the file for reading
                ];

                // Prepare the job data
                $job_data = [
                    'tasks' => [
                        [
                            'name' => 'import/upload',
                            'file' => 'input', // Use the input file
                        ],
                        [
                            'name' => 'convert',
                            'input' => 'import-0', // Use the output of the 'import/upload' task
                            'output_format' => $output_format,
                            'output' => [
                                'resize' => [
                                    'width' => 150,
                                    'height' => 150,
                                ],
                            ],
                        ],
                    ],
                ];

                // Create headers for the API request
                $headers = [
                    'Authorization: Bearer ' . $api_key,
                    'Content-Type: application/json',
                ];

                // Prepare the request arguments
                $request_args = [
                    'body' => json_encode($job_data),
                    'headers' => $headers,
                    'timeout' => 30, // Adjust the timeout as needed
                    'method' => 'POST',
                ];

                // Send the API request
                $response = wp_remote_request($api_url, $request_args);

                // Check for errors
                if (is_wp_error($response)) {
                    echo '<div class="error-message">Error: ' . $response->get_error_message() . '</div>';
                } else {
                    $response_code = wp_remote_retrieve_response_code($response);
                    if ($response_code !== 200) {
                        echo '<div class="error-message">HTTP Request failed with code ' . $response_code . '</div>';
                    } else {
                        // Decode the response JSON
                        $response_data = json_decode(wp_remote_retrieve_body($response), true);

                        if ($response_data && isset($response_data['data']['id'])) {
                            // Job creation was successful

                            // TODO: Implement handling of the conversion job status and result
                            // You should check the job status and download the converted image

                            // Create a new WordPress post with the title and description
                            $post_data = [
                                'post_title' => $title,
                                'post_content' => $description,
                                'post_status' => 'publish', // Automatically approve the post
                                'post_type' => 'post', // You can change the post type as needed
                            ];

                            $post_id = wp_insert_post($post_data);

                            // Embed the uploaded document using [embeddoc] shortcode
                            $embed_code = '[embeddoc url="' . esc_url($file['tmp_name']) . '" width="100%" height="300px" download="none" viewer="microsoft"]';
                            add_post_meta($post_id, '_embeddoc_shortcode', $embed_code);

                            // Set the converted image as the featured image
                            $featured_image_url = $response_data['data']['tasks'][1]['result']['files'][0]['url'];
                            $image_id = ccp2j_set_featured_image($post_id, $featured_image_url);

                            // Display a success message
                            echo '<div class="success-message">File conversion initiated successfully!</div>';
                        } else {
                            // Display an error message
                            echo '<div class="error-message">Failed to initiate file conversion. Please try again later.</div>';
                        }
                    }
                }
            } else {
                // Invalid file type, show an error message
                echo '<div class="error-message">Invalid file type. Please upload a PDF or PowerPoint file.</div>';
            }
        }
    }
}

// Function to set the featured image for a post
function ccp2j_set_featured_image($post_id, $image_url) {
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);

    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $file, $post_id);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);

    set_post_thumbnail($post_id, $attach_id);

    return $attach_id;
}
