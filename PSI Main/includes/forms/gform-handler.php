<?php

class Gravity_Forms_Handler {
    
    public function __construct() {
        // Restrict access to listed forms by user role
        add_filter('gform_pre_render', array($this, 'restrict_form_access'));  

        add_action('gform_after_submission_11', array($this, 'create_staff_page'), 20, 2);
        add_action('gform_after_submission_12', array($this, 'update_staff_page'), 20, 2);
       
        add_action('gform_after_submission_10', array($this, 'create_project'), 10, 2);
        add_action('gform_after_submission_8',  array($this, 'update_project'), 10, 2);

        add_action('gform_after_submission_13', array($this, 'create_article'), 10, 2);
        add_action('gform_after_submission_14', array($this, 'update_article'), 10, 2);

        // Add excerpt to Gravity Forms Advanced Post Creation
        add_filter( 'gform_advancedpostcreation_excerpt', function( $enable_excerpt ) {
            return true;
        }, 10, 1 );

        // for the edit article form, this allows for 1000 posts to be queries by the edit form.
        add_filter( 'gppa_query_limit_14_1', function( $query_limit, $object_type ) {
            // Update "1000" to the maximum number of results that should be returned for the query populating this field.
            return 1000;
        }, 10, 2 );

        // for the create project form, this allows for 1000 posts to be queries by the related CS/PR section.
        add_filter( 'gppa_query_limit_10_22', function( $query_limit, $object_type ) {
            // Update "1000" to the maximum number of results that should be returned for the query populating this field.
            return 1000;
        }, 10, 2 );
        
    }

    // NOTE: Will depricate once Gravity Chest accepts php functions
    // Currently only used for the edit project form
    public function restrict_form_access($form) {
        // All form IDs listed here will be checked
        if($form['id'] != 8){
            return $form;
        }

        $user = wp_get_current_user();
        $user_id = $user->ID;
        $roles = (array) $user->roles
        
        if (empty($roles)) {
            wp_redirect( home_url() );
            exit;
        }

        $role = $roles[0]; // Assuming the user has only one role

        // Allow staff member editors and administrators to access the form
        if ( $role == 'staff_member_editor' || $role == 'administrator' ) {
            return $form;
        }

        if ( !isset( $_GET['project-name'] ) && ($role != 'staff_member_editor' || $role != 'administrator')) {
            wp_redirect( home_url() );
            exit;
        } 

        $project_id = $_GET['project-name'];
        $principal_investigator = get_field('primary_investigator', $project_id);

        if ( $principal_investigator && $user_id != $principal_investigator->ID ) {
            wp_redirect( home_url() );
            exit;
        }

        return $form;
    }

    public function create_article($entry, $form) {
        $title = rgar( $entry, '37' ); 
        $post_id = get_page_by_title( $title, OBJECT, 'post' );

        $image_captions = array();
        $additional_images = array();

        
        
        for ($i = 0; isset($_POST['captionInput' . $i]); $i++) {
            $image_captions[] = sanitize_text_field($_POST['captionInput' . $i]);
        } 

        // FIND POST ID TO-DO
        $image_caption_data = array(
            isset($entry["gpml_ids_24"][0]) ? $entry["gpml_ids_24"][0] : false => $image_captions[0],
            isset($entry["gpml_ids_26"][0]) ? $entry["gpml_ids_26"][0] : false => $image_captions[1],
            isset($entry["gpml_ids_27"][0]) ? $entry["gpml_ids_27"][0] : false => $image_captions[2],
            isset($entry["gpml_ids_28"][0]) ? $entry["gpml_ids_28"][0] : false => $image_captions[3],
            isset($entry["gpml_ids_29"][0]) ? $entry["gpml_ids_29"][0] : false => $image_captions[4],
            isset($entry["gpml_ids_30"][0]) ? $entry["gpml_ids_30"][0] : false => $image_captions[5], 
            isset($entry["gpml_ids_31"][0]) ? $entry["gpml_ids_31"][0] : false => $image_captions[6],
        );
        $related_staff = rgar($entry, '23');
        $related_projects = rgar($entry, '32');  

        foreach($image_caption_data as $id => $caption) {
            if(!$id){
                continue;
            }
            if ( key($image_caption_data) !== $id ) {
                $additional_images[] = $id;
            } else {
                // Featured Image
                set_post_thumbnail($post_id, $id);
            }
            wp_update_post(array(
                'ID'            => $id,
                'post_excerpt'  => $caption,
            ));
        }

        update_field('additional_images', $additional_images, $post_id);

        if (!empty($related_staff)) {
            $unserialized_related_staff = json_decode($related_staff);
            if (is_array($unserialized_related_staff)) {
                update_field('field_65021ce5287c6', $unserialized_related_staff, $post_id);
            }
        }

        if (!empty($related_projects)) {
            $unserialized_related_projects = json_decode($related_projects);
            if (is_array($unserialized_related_projects)) {
                update_field('field_65a6fa6acef28', $unserialized_related_projects, $post_id);
            }
        }

    }

    public function update_article($entry, $form) {

        // Get the post ID you want to update
        $post_id = rgar($entry, '15');
        $title = rgar($entry, '3');
        $content = rgar($entry, '4');
        $excerpt = rgar($entry, '5');
        $tags = rgar($entry, '6');
        $featured_image = rgar($entry, '12');
        $related_staff = rgar($entry, '10');
        $related_projects = rgar($entry, '16');
        $date = rgar($entry, '13');
        $time = rgar($entry, '14');
        
        // Convert date from "mm/dd/yyyy" to "Y-m-d"
        $converted_date = !empty($date) ? new DateTime($date) : new DateTime(); // Use current date if empty
        $format_date = $converted_date->format('Y-m-d');

        // Convert time from "hh/mm/(am or pm)" to "H:i:s"
        $converted_time = !empty($time) ? new DateTime($time) : new DateTime(); // Use current time if empty
        $format_time = $converted_time->format('H:i:s');

        // Set post_date to current date and time if date and/or time is empty
        $scheduled_date = new DateTime(); // Initialize $scheduled_date here

        if (!empty($date) && !empty($time)) {
            $post_date = $format_date . ' ' . $format_time;

            // Check if the scheduled date is in the future
            $scheduled_date = new DateTime($post_date, new DateTimeZone(get_option('timezone_string')));
            $current_date = new DateTime(null, new DateTimeZone(get_option('timezone_string')));
            if ($scheduled_date > $current_date) {
                // Set post_status to 'future' if the date is in the future
                $post_status = 'future';
            } else {
                // Set post_status to 'publish' if the date is in the past or present
                $post_status = 'publish';
            }
        } else {
            $wp_timezone = get_option('timezone_string');
            $current_date = new DateTime(null, new DateTimeZone($wp_timezone));
            $post_date = $current_date->format('Y-m-d H:i:s');
            $post_status = 'publish';
        }

        // Set post_date_gmt to be the same as post_date
        $post_date_gmt = $post_date;
        
        $post_data = array(
            'ID'           => $post_id,
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $post_status,
            'post_date'    => $post_date,
            'post_date_gmt' => $post_date_gmt,
        );

        $category_name = rgar($entry, '23');

        $category = get_term_by( 'name', $category_name, 'category' );
        
        $category_id = $category->term_id;

        $image_captions = []; // Array to store captions

        // Assuming the field names were created dynamically with IDs like 'captionInput0', 'captionInput1', ...
        for ($i = 0; isset($_POST['captionInput' . $i]); $i++) {
            $image_captions[] = sanitize_text_field($_POST['captionInput' . $i]);
        }

        $additional_images_data = array(
            isset($_POST["imageInput-featured-image"]) ? $_POST["imageInput-featured-image"] : rgar($entry, '12') => isset($_POST["captionInput0"]) ? $_POST["captionInput0"] : $image_captions[0],
            isset($_POST["imageInput-0"]) ? $_POST["imageInput-0"] : rgar($entry, '17') => isset($_POST["captionInput1"]) ? $_POST["captionInput1"] : $image_captions[1],
            isset($_POST["imageInput-1"]) ? $_POST["imageInput-1"] : rgar($entry, '18') => isset($_POST["captionInput2"]) ? $_POST["captionInput2"] : $image_captions[2],
            isset($_POST["imageInput-2"]) ? $_POST["imageInput-2"] : rgar($entry, '19') => isset($_POST["captionInput3"]) ? $_POST["captionInput3"] : $image_captions[3],
            isset($_POST["imageInput-3"]) ? $_POST["imageInput-3"] : rgar($entry, '20') => isset($_POST["captionInput4"]) ? $_POST["captionInput4"] : $image_captions[4],
            isset($_POST["imageInput-4"]) ? $_POST["imageInput-4"] : rgar($entry, '21') => isset($_POST["captionInput5"]) ? $_POST["captionInput5"] : $image_captions[5],
            isset($_POST["imageInput-5"]) ? $_POST["imageInput-5"] : rgar($entry, '22') => isset($_POST["captionInput6"]) ? $_POST["captionInput6"] : $image_captions[6],
        );

        $input_data = [
            'input_12',
            'input_17',
            'input_18',
            'input_19',
            'input_20',
            'input_21',
            'input_22',
        ];

        $image_input = [
            "imageInput-featured-image",
            "imageInput-0",
            "imageInput-1",
            "imageInput-2",
            "imageInput-3",
            "imageInput-4",
            "imageInput-5",
        ];

        $processed_featured_image = false;
        $index = 0;
        $uploaded_attachment_ids = array(); // Array to store uploaded attachment IDs

        foreach ($additional_images_data as $image_url => $image_caption) {
            
            // Check if $image_url is empty in both $_POST and rgar()
            if (empty($_POST[$image_input[$index]]) && empty(json_decode(rgar($entry, $image_url), true))) {
                // File not present in both $_POST and rgar(), continue to the next iteration
               
                $file_input_name = $input_data[$index];
                
                if (isset($_FILES[$file_input_name]) && !empty($_FILES[$file_input_name]['name'])) {
                    // File is present in $_FILES, continue processing
                    $file_name = $_FILES[$file_input_name]['name'];

                    if (!$processed_featured_image && $file_input_name === 'input_12') {
                        // This is the featured image, handle it differently if needed
                        $processed_featured_image = true;
                        
                        // Set the post thumbnail (featured image)
                        $attachment_id = $this->handle_uploaded_image($_FILES[$file_input_name]['tmp_name'], $file_name, $image_captions[$index]);
                        if ($attachment_id) {
                            set_post_thumbnail($post_id, $attachment_id);

                            wp_update_post(array(
                                'ID'           => $attachment_id,
                                'post_excerpt' => $image_captions[$index],
                                'post_parent'  => $post_id,
                            ));

                            $index++;
                            continue;
                        }
                        $index++;
                    } else {
                        // This is a regular attachment, handle it accordingly
                        $attachment_id = $this->handle_uploaded_image($_FILES[$file_input_name]['tmp_name'], $file_name, $image_captions[$index]);

                        // Update Caption of Image
                        if ($attachment_id) {
                            wp_update_post(array(
                                'ID'           => $attachment_id,
                                'post_excerpt' => $image_captions[$index],
                                'post_parent'  => $post_id,
                            ));
                            $uploaded_attachment_ids[] = $attachment_id; // Add the attachment ID to the array
                        }
                        $index++;
                    }
                    
                    $index++;
                }  
            }

            if (isset($_POST[$image_input[$index]])) {
                
                if (!$processed_featured_image) {
                    // This is the featured image, handle it differently if needed
                    $processed_featured_image = true;

                    $attachment_id = attachment_url_to_postid($_POST[$image_input[$index]]);

                    set_post_thumbnail($post_id, $attachment_id);
                    
                    if ($attachment_id) {
                        // Do something with the attachment ID if needed
                        wp_update_post(array(
                            'ID'           => $attachment_id,
                            'post_excerpt' => $image_caption,
                            'post_parent'  => $post_id,
                        ));
                        
                        $index++;
                        
                        continue;
                    }
                }
                // Get attachment ID from the URL
                $attachment_id = attachment_url_to_postid($_POST[$image_input[$index]]);
                
                if ($attachment_id) {
                    // Do something with the attachment ID if needed
                    wp_update_post(array(
                        'ID'           => $attachment_id,
                        'post_excerpt' => $image_caption,
                        'post_parent'  => $post_id,
                    ));
                    $uploaded_attachment_ids[] = $attachment_id;
                    $index++;
                    
                    continue;
                }
                $index++;
            } else {
                // $image_url is from rgar($entry, ...) which means it is a newly uploaded image
                $image = json_decode($image_url);
                
                
                if (is_array($image) && isset($image[0])) {

                    if (!$processed_featured_image) {
                        // This is the featured image, handle it differently if needed
                        $processed_featured_image = true;

                        $attachment_id = attachment_url_to_postid($image[0]);

                        set_post_thumbnail($post_id, $attachment_id);
                        
                        if ($attachment_id) {
                            // Do something with the attachment ID if needed
                            wp_update_post(array(
                                'ID'           => $attachment_id,
                                'post_excerpt' => $image_caption,
                                'post_parent'  => $post_id,
                            ));
                            
                            $index++;
                            
                            continue;
                        }
                    }
                    
                    $attachment_id = attachment_url_to_postid($image[0]);
                    if (!$attachment_id) {
                        continue;
                    }

                    // Attach the image to the post
                    wp_update_post(array(
                        'ID'           => $attachment_id,
                        'post_excerpt' => $image_caption,
                        'post_parent'  => $post_id,
                    ));

                    $uploaded_attachment_ids[] = $attachment_id; // Add the attachment ID to the array
                    
                    $index++;
                }

                $index++;
            }
        }

        update_field('field_65ba89acc66e1', $uploaded_attachment_ids, $post_id);

        // Update the post
        wp_update_post($post_data);

        // Update post tags
        wp_set_post_tags($post_id, $tags);
        wp_set_post_categories($post_id, array($category_id));

        update_field('related_staff', json_decode($related_staff), $post_id);
        update_field('related_projects', json_decode($related_projects), $post_id); 
                 
        // Update related projects with the value of $post_id
        $related_projects_array = json_decode($related_projects);

        if (is_array($related_projects_array)) {
            foreach ($related_projects_array as $related_project_id) {
                update_field('related_articles', $post_id, $related_project_id);
            }
        }
    }

    public function create_project($entry, $form) {
        $title = rgar($entry, '1');
        $funding_instrument = rgar($entry, '12');
        $passthrough_entities = rgar($entry, '13');
        $non_psi_personel = rgar($entry, '14');
        $principal_investigator = rgar($entry, '15');
        $collaborators = rgar($entry, '16');
        $co_investigators = rgar($entry, '17');
        $nickname = rgar($entry, '4');
        $featured_image = rgar($entry, '5');
        $description = rgar($entry, '3');
        $project_number = rgar($entry, '6');
        $agency_award_number = rgar($entry, '7');
        $project_website = rgar($entry, '10');
        $start_date = rgar($entry, '8');
        $end_date = rgar($entry, '9');
        $related_articles = rgar($entry, '22');
        $funding_source = rgar($entry, '18');
        $funding_program = rgar($entry, '19');

        $meta_fields = [
            'field_65652c908b353' => $funding_instrument,
            'field_656541da7d94d' => $passthrough_entities,
            'field_656541b47d94c' => $non_psi_personel,
            'field_6565323600251' => $principal_investigator,
            'field_656541567d94b' => json_decode($collaborators),
            'field_656539a3fe4ba' => json_decode($co_investigators),
            'field_65652d6d24359' => $nickname,
            'field_65652c058b351' => $project_number,
            'field_65652c7e8b352' => $agency_award_number,
            'field_65652f772435e' => $project_website,
            'field_65652f0a2435c' => $start_date,
            'field_65652f552435d' => $end_date,
            'field_6574c321bde33' => json_decode($related_articles),
        ];

        $taxonomies = array(
            'funding-agency'   => $funding_source,
            'funding-program'  => $funding_program,
        );

        // Decode the JSON string for featured image
        $featured_image_array = json_decode($featured_image, true);

        // Extract the URL from the array
        $featured_image_url = isset($featured_image_array[0]) ? $featured_image_array[0] : '';

        // Create post data
        $post_data = array(
            'post_title'      => $title,
            'post_content'    => $description,
            'post_type'       => 'project',
            'post_status'     => 'publish',
            'post_author'     => get_current_user_id(),
        );

        // Insert the post and get the post ID
        $post_id = wp_insert_post($post_data);

        if(!$post_id) {
            return;
        }

        // Set the featured image
        if ($featured_image_url) {
            $attachment_id = attachment_url_to_postid($featured_image_url);
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        // Update meta fields
        foreach($meta_fields as $field_key => $field_value) {
            
            if (!update_field($field_key, $field_value, $post_id)) {
                // Handle error (e.g., log, notify, etc.)
            }
        
        }

        // Update taxonomies
        foreach ($taxonomies as $taxonomy => $term_name) {
            if ($term_name) {
                $term = get_term_by('name', $term_name, $taxonomy);
                $term_id = $term ? $term->term_id : 0;

                if (!$term_id) {
                    // Term doesn't exist, let's create it
                    $term_info = wp_insert_term($term_name, $taxonomy);

                    if (!is_wp_error($term_info)) {
                        // Term created successfully, get the term ID
                        $term_id = $term_info['term_id'];

                        

                    } else {
                        // Handle error (e.g., log, notify, etc.)
                        continue; // Skip to the next iteration
                    }
                }

                // Set the post terms
                wp_set_post_terms($post_id, [$term_id], $taxonomy, false);
                
            }
        }

        // Get the first term for 'funding-agency'
        $funding_agency_terms = wp_get_post_terms($post_id, 'funding-agency', array('fields' => 'ids'));
        $funding_agency_term = !empty($funding_agency_terms) ? $funding_agency_terms[0] : null;

        // Get the first term for 'funding-program'
        $funding_program_terms = wp_get_post_terms($post_id, 'funding-program', array('fields' => 'ids'));
        $funding_program_term = !empty($funding_program_terms) ? $funding_program_terms[0] : null;

        // Funding Agency/Source related programs.

        // Get existing related programs
        $existing_programs = get_field('related_programs', 'funding-agency_' . $funding_agency_term);

        // Ensure $existing_programs is an array
        $existing_programs = is_array($existing_programs) ? $existing_programs : array();

        // Add the new program to the array if it doesn't already exist
        if (!in_array($funding_program_term, $existing_programs)) {
            $existing_programs[] = $funding_program_term;

            // Update the field with the modified array
            update_field('related_programs', $existing_programs, 'funding-agency_' . $funding_agency_term);
        }

        // Similarly, for related agencies
        $existing_agencies = get_field('related_agencies', 'funding-program_' . $funding_program_term);

        // Ensure $existing_agencies is an array
        $existing_agencies = is_array($existing_agencies) ? $existing_agencies : array();

        if (!in_array($funding_agency_term, $existing_agencies)) {
            $existing_agencies[] = $funding_agency_term;

            update_field('related_agencies', $existing_agencies, 'funding-program_' . $funding_program_term);
        }
    }

    public function update_project($entry, $form) {
        // Default 
        $post_id = rgar($entry, '22');
        $content = rgar($entry, '3');
        $title = rgar($entry, '2'); 
        $featured_image = json_decode(rgar($entry, '21'));

        

        if (empty($_POST['featured_image']) && empty($featured_image)) {
            if (isset($_FILES['input_21']) && !empty($_FILES['input_21']['name'])) {
                $attachment_id =$this->handle_uploaded_image($_FILES['input_21']['tmp_name'], $_FILES['input_21']['name'], '');
                if($attachment_id) {
                    set_post_thumbnail($post_id, $attachment_id);
                }
            } else {
                delete_post_thumbnail($post_id);
            }
        }

        if (isset($_POST["featured_image"])) {
            // Image has already beeen uploaded but not removed, old image do nothing
        }

        if(is_array($featured_image) && isset($featured_image[0])){
            $attachment_id = attachment_url_to_postid($featured_image[0]);
            
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        // Custom Taxonomies
        $funding_source = rgar($entry, '18');
        $funding_program = rgar($entry, '19');

        // Meta 
        $nickname = rgar($entry, '4');
        $project_number = rgar($entry, '6');
        $agency_award_number = rgar($entry, '7');
        $start_date = rgar($entry, '8');
        $end_date = rgar($entry, '10');
        $project_website = rgar($entry, '11');
        $non_psi_personel = rgar($entry, '12');
        $passthrough_entities = rgar($entry, '13');
        $funding_instrument = rgar($entry, '14');
        $pi = rgar($entry, '15');
        $collabs = json_decode(rgar($entry, '16'), true);
        $co_investigators = json_decode(rgar($entry, '17'));

  

        $post_data = array(
            'ID'           => $post_id,
            'post_content' => $content,
            'post_title'   => $title,
        );

        $meta_fields = [
            'field_65652d6d24359' => $nickname,
            'field_65652c058b351' => $project_number,
            'field_65652c7e8b352' => $agency_award_number,
            'field_65652f0a2435c' => $start_date,
            'field_65652f552435d' => $end_date,
            'field_65652f772435e' => $project_website,
            'field_656541b47d94c' => $non_psi_personel,
            'field_656541da7d94d' => $passthrough_entities,
            'field_65652c908b353' => $funding_instrument,
            'field_6565323600251' => $pi,
            'field_656541567d94b' => $collabs,
            'field_656539a3fe4ba' => $co_investigators,
        ];

        $taxonomies = array(
            'funding-agency'   => $funding_source,
            'funding-program'  => $funding_program,
        );
        
       
        // Update meta fields
        foreach($meta_fields as $field_key => $field_value) {
            
                if (!update_field($field_key, $field_value, $post_id)) {
                    // Handle error (e.g., log, notify, etc.)
                }
            
        }

        // Update taxonomies
        foreach ($taxonomies as $taxonomy => $term_name) {
            if ($term_name) {
                $term = get_term_by('name', $term_name, $taxonomy);
                $term_id = $term ? $term->term_id : 0;

                if (!$term_id) {
                    // Term doesn't exist, let's create it
                    $term_info = wp_insert_term($term_name, $taxonomy);

                    if (!is_wp_error($term_info)) {
                        // Term created successfully, get the term ID
                        $term_id = $term_info['term_id'];

                        

                    } else {
                        // Handle error (e.g., log, notify, etc.)
                        continue; // Skip to the next iteration
                    }
                }

                // Set the post terms
                wp_set_post_terms($post_id, [$term_id], $taxonomy, false);
                
            }
        }

        // Get the first term for 'funding-agency'
        $funding_agency_terms = wp_get_post_terms($post_id, 'funding-agency', array('fields' => 'ids'));
        $funding_agency_term = !empty($funding_agency_terms) ? $funding_agency_terms[0] : null;

        // Get the first term for 'funding-program'
        $funding_program_terms = wp_get_post_terms($post_id, 'funding-program', array('fields' => 'ids'));
        $funding_program_term = !empty($funding_program_terms) ? $funding_program_terms[0] : null;

        // Funding Agency/Source related programs.

        // Get existing related programs
        $existing_programs = get_field('related_programs', 'funding-agency_' . $funding_agency_term);

        // Ensure $existing_programs is an array
        $existing_programs = is_array($existing_programs) ? $existing_programs : array();

        // Add the new program to the array if it doesn't already exist
        if (!in_array($funding_program_term, $existing_programs)) {
            $existing_programs[] = $funding_program_term;

            // Update the field with the modified array
            update_field('related_programs', $existing_programs, 'funding-agency_' . $funding_agency_term);
        }

        // Similarly, for related agencies
        $existing_agencies = get_field('related_agencies', 'funding-program_' . $funding_program_term);

        // Ensure $existing_agencies is an array
        $existing_agencies = is_array($existing_agencies) ? $existing_agencies : array();

        if (!in_array($funding_agency_term, $existing_agencies)) {
            $existing_agencies[] = $funding_agency_term;

            update_field('related_agencies', $existing_agencies, 'funding-program_' . $funding_program_term);
        }

        // Update the post with the new content
        wp_update_post($post_data);
    }

    /**
     * Create staff page after user registration via Gravity Forms.
     *
     * @param array $entry The form entry data.
     * @param array $form The form data.
     */
    public function create_staff_page($entry, $form) {
         
        $user_email = rgar($entry, '6'); 
        // Get the user ID based on the user email
        $user = get_user_by('email', $user_email);

        $first_name = rgar($entry, '4.3');
        $last_name = rgar($entry, '4.6');
        $position = rgar($entry, '10');
        $state = rgar($entry, '7.4');
        $country = rgar($entry, '7.6');
    
        $primary_picture = json_decode(rgar($entry, '30'));
      
        $primary_picture_array = is_array($primary_picture) ? $primary_picture : [''];
        $primary_picture_attatchment_id = isset($primary_picture_array[0]) ? attachment_url_to_postid($primary_picture_array[0]) : 0;

        $user_slug = sanitize_title($first_name . '-' . $last_name);
        $address = $state . ' '. $country;
        
        if ($user) {

            $user_meta = array(
                'field_652f53162879f' => $position,
                'field_652f5316338be' => $address,
                'field_656d4ea2c5454' => $user_slug,
            );

            // Field group ID for the primary pictures
            $profile_pictures_group_subfields = array(
                'field_65821a416331d' => $primary_picture_attatchment_id,
                'field_65821a686331e' => 0,
                'field_65821aa06331f' => 0,
                'field_65821aaf63320' => 0      
            );

            // profile pictures
            update_field('field_658219e86331c', $profile_pictures_group_subfields, 'user_' . $user->data->ID);

            foreach ($user_meta as $field => $value) {
                update_field($field, $value, 'user_' . $user->data->ID);
            }    

            
        }
    }

    /**
     * Update staff page after form submission via Gravity Forms. These values are not meant to be updated by a staff member
     *
     * @param array $entry The form entry data.
     * @param array $form The form data.
     */
    public function update_staff_page($entry, $form) {
        $user_id = rgar($entry, '8');

        if(!$user_id) {
            error_log('No user ID found');
        }

        $email = rgar($entry, '3');
        $name = rgar($entry, '9');
        $position = rgar($entry, '4');
        $address = rgar($entry, '7');

        // Extract prefix, first name, and last name from the name string
        $name_parts = explode(' ', $name, 3);

        // Assign variables based on the count of name parts
        $prefix = (count($name_parts) > 2) ? $name_parts[0] : '';
        $first_name = (count($name_parts) > 2) ? $name_parts[1] : $name_parts[0];
        $last_name = (count($name_parts) > 2) ? $name_parts[2] :  $name_parts[1];

        // Display name without prefix
        $display_name = $first_name . ' ' . $last_name;

        // Update user data as needed
        wp_update_user(array(
            'ID' => $user_id,
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name,
            // Add other fields you want to update
        ));

        update_user_meta($user_id, 'nickname', $name);
        update_field('position', $position, 'user_' . $user_id);
        update_field('location', $address, 'user_' . $user_id);
    }

    public function handle_uploaded_image($file_tmp, $file_name, $caption) {
        // Assuming you have a directory to store the uploaded files
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'];
    
        // Move the uploaded file to the destination directory
        $file_path = $upload_path . '/' . $file_name;
    
        if (move_uploaded_file($file_tmp, $file_path)) {
            // File uploaded successfully
            
            // ...
    
            // Insert the image into the media library and return the attachment ID
            $attachment_id = wp_insert_attachment(array(
                'post_title'     => $file_name,
                'post_mime_type' => mime_content_type($file_path),
                'post_status'    => 'inherit',
                'post_excerpt'   => $caption,
            ), $file_path);
    
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attach_data);
    
            return $attachment_id;
        } else {
            // File upload failed
            return false;
        }
    }
}

// Instantiate the Gravity_Forms_Manager class
new Gravity_Forms_Handler();
