<?php

class RestClass {

    public static function register_endpoints() {
        register_rest_route('psi/v1', '/related-posts/', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'get_related_posts'),
        ));
        register_rest_route('psi/v1', '/load-more-posts/', array(
            'methods'  => 'POST',
            'callback' => array(__CLASS__, 'load_more_posts'),
        ));
        register_rest_route('psi/v1', '/projects/', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'get_projects'),
        ));
        register_rest_route('psi/v1', '/active-user-projects/', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'get_active_projects'),
        ));
        register_rest_route('psi/v1', '/past-user-projects/', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'get_past_projects'),
        ));
        register_rest_route('psi/v1', '/project/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_project'),
        ));
        register_rest_route('psi/v1', '/post/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_post'),
        ));
        register_rest_route('psi/v1', '/user/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_user'),
        ));
    }

    public static function get_active_projects($request) {
        $posts_per_page = 4;
        $page = $request->get_param('page');
        $user_slug = $request->get_param('userSlug');
        
        $response = array();
       
        if(!$user_slug) {
            return rest_ensure_response('No User given, cannot query');
        }

        if(!$page){
            $page = 0;
        }

        $user = get_users(array(
            'meta_key'   => 'user_slug',
            'meta_value' => $user_slug,
            'number'     => 1, // Limit the result to one user
        ));

        if (!isset($user[0])) {
            return rest_ensure_response('No User found for ' . $user_slug);
        }

        $user_id = $user[0]->data->ID;

        $related_posts = get_field('related_projects_and_initiatives', 'user_' . $user_id);

        if(!$related_posts){
            // Return some error which says no posts could be found
            return rest_ensure_response('No projects found for ' . $user_slug);
        }

        // Filter out past projects
        $active_related_posts = array_filter($related_posts, function($post) {
            if ($post->post_type !== 'project') {
                return false;
            }
            return PSI_Child_Theme::is_past_project($post) === false;
        });

        $initial_posts = array_slice($active_related_posts, 0, $posts_per_page);

        $start_index = ($page) * $posts_per_page;
        $posts = array_slice($active_related_posts, $start_index, $posts_per_page);
        
        
        // Check if there are more posts
        $has_more = count($active_related_posts) > $start_index + $posts_per_page; 
                
        $response['has_more'] = $has_more; 

        if (!empty($posts)) {
            ob_start();
            foreach ($posts as $post) {
                // Check if the post is of post type 'project'
                if (get_post_type($post) === 'project') {
                    get_template_part('template-parts/projects/activity-banner', '', array(
                        'page' => $page,
                        'post' => $post,
                    ));
                }
            }
            $response['html'] = ob_get_clean();
        }

        return rest_ensure_response($response);
    }

    public static function get_past_projects($request) {
        $posts_per_page = 4;
        $page = $request->get_param('page');
        $user_slug = $request->get_param('userSlug');
        
        $response = array();

        
       
        if(!$user_slug) {
            return rest_ensure_response('No User given, cannot query');
        }

        if(!$page){
            $page = 0;
        }

        $user = get_users(array(
            'meta_key'   => 'user_slug',
            'meta_value' => $user_slug,
            'number'     => 1, // Limit the result to one user
        ));

        if (!isset($user[0])) {
            return rest_ensure_response('No User found for ' . $user_slug);
        }

        $user_id = $user[0]->data->ID;

        $related_posts = get_field('related_projects_and_initiatives', 'user_' . $user_id);

        if(!$related_posts){
            // Return some error which says no posts could be found
            return rest_ensure_response('No projects found for ' . $user_slug);
        }

        // Filter out past projects
        $past_related_posts = array_filter($related_posts, function($post) {
            if ($post->post_type !== 'project') {
                return false;
            }
           
            return PSI_Child_Theme::is_past_project($post) === true;
            
        });

        

        $start_index = ($page) * $posts_per_page;
        $posts = array_slice($past_related_posts, $start_index, $posts_per_page);
        
        
        // Check if there are more posts
        $has_more = count($past_related_posts) > $start_index + $posts_per_page; 
                
        $response['has_more'] = $has_more; 

        if (!empty($posts)) {
            ob_start();
            foreach ($posts as $post) {
                // Check if the post is of post type 'project'
                if (get_post_type($post) === 'project') {
                    get_template_part('template-parts/projects/activity-banner', '', array(
                        'page' => $page,
                        'post' => $post,
                    ));
                }
            }
            $response['html'] = ob_get_clean();
            
        }

        return rest_ensure_response($response);
    }

    public static function get_related_posts($request) {
        // Get parameters from the REST API request
        $user_slug = $request['userSlug'];
        $page = $request['page'];
        $post_id = $request['postId'];
        $relationship = $request['relationship'];
        $posts_per_page = 6;
    
        $user = get_users(array(
            'meta_key'   => 'user_slug',
            'meta_value' => $user_slug,
            'number'     => 1, // Limit the result to one user
        ));

        $response = array();
    
        if (isset($user[0])) {
            $user_id = $user[0]->data->ID;
            if ($relationship == 'related_projects_and_initiatives') {
                $related_posts = get_field($relationship, 'user_' . $user_id);
                $posts_per_page = 4;
                //$related_posts = array_reverse($related_posts);
                /* usort($related_posts, function ($a, $b) {
                    $date_a = strtotime($a->post_date);
                    $date_b = strtotime($b->post_date);

                    return ($date_a < $date_b) ? 1 : -1;
                }); */
                
                $start_index = ($page - 1) * $posts_per_page;
                $posts = array_slice($related_posts, $start_index, $posts_per_page);
        
        
                // Check if there are more posts
                $has_more = count($related_posts) > $start_index + $posts_per_page; 
                
                $response['has_more'] = $has_more; 
                
                
            } else {
                $related_posts = get_field('related_posts', 'user_' . $user_id);
                // Sort the related posts by date in descending order
                usort($related_posts, function ($a, $b) {
                    $date_a = strtotime($a->post_date);
                    $date_b = strtotime($b->post_date);

                    return ($date_a < $date_b) ? 1 : -1;
                });
                $start_index = ($page - 1) * $posts_per_page;
                $posts = array_slice($related_posts, $start_index, $posts_per_page);
        
                
                // Check if there are more posts
                $has_more = count($related_posts) > $start_index + $posts_per_page;
                $response['has_more'] = $has_more;
                            
            }
        } else {
            $related_posts = get_field('related_articles', $post_id);
            // Sort the related posts by date in descending order
            usort($related_posts, function ($a, $b) {
                $date_a = strtotime($a->post_date);
                $date_b = strtotime($b->post_date);

                return ($date_a < $date_b) ? 1 : -1;
            });
            $start_index = ($page - 1) * $posts_per_page;
            $posts = array_slice($related_posts, $start_index, $posts_per_page);
    
    
            // Check if there are more posts
            $has_more = count($related_posts) > $start_index + $posts_per_page;
            $response['has_more'] = $has_more;
        }
    
        if ($related_posts) {
            
            
            
    
            if (!empty($posts)) {
                ob_start();
                foreach ($posts as $post) {
                    // Check if the post is of post type 'project'
                    if (get_post_type($post) === 'project') {
                        get_template_part('template-parts/projects/activity-banner', '', array(
                            'page' => $page,
                            'post' => $post,
                        ));
                    } else {
                        get_template_part('template-parts/related-post', '', array(
                            'page' => $page,
                            'post' => $post,
                        ));
                    }
                }
                $response['html'] = ob_get_clean();
            }
    
            return rest_ensure_response($response);
        }
    
        return rest_ensure_response(array());
    }
    

    public static function load_more_posts($data) {
        $post_type = isset($data['post_type']) ? sanitize_text_field($data['post_type']) : 'post';
        $posts_per_page = isset($data['posts_per_page']) ? intval($data['posts_per_page']) : 6;
        $category = isset($data['category']) ? sanitize_text_field($data['category']) : '';
    
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => $posts_per_page,
            'category_name'  => $category,
            'paged'          => isset($data['page']) ? intval($data['page']) + 1 : 2,
        );
    
        $query = new WP_Query($args);
    
        $response = array();
        $response['has_more'] = $query->max_num_pages > $args['paged'];
        $page_number = isset($data['page']) ? intval($data['page']) + 1 : 2; // Incrementing page number
    
        ob_start();
        if ($query->have_posts()) :
            while ($query->have_posts()) : $query->the_post();
            $post_id = get_the_ID();
            ?>
            <div class="load-more-item">
            <div class="load-more-item-container<?php if (!has_post_thumbnail()) { echo ' no-img'; } ?>" style="background-image: <?php if (has_post_thumbnail()) { ?>url('<?php echo get_the_post_thumbnail_url(get_the_ID(), 'large'); ?>')<?php } ?>;">
                <?php
                $category_name = get_the_category()[0]->name;	
                if($category_name === 'Cover Story') {
                    $cat_link = 'https://www.psi.edu/blog/category/cover-story/';
                    $cat = 'cs';
                } elseif($category_name === 'Press Release') {
                    $cat = 'pr';
                    $cat_link = 'https://www.psi.edu/blog/category/press-release/';
                }
                ?>
                <?php if (has_category()) : ?>
                    <p class="gb-headline dynamic-term-class gb-headline-text load-more-category <?php echo $cat; ?> ">
                        <a class="load-more-category__link" href="<?php echo $cat_link; ?>"><?php echo $category_name ?></a>
                    </p>
                <?php endif; ?>
                <div class="load-more-post-content-container">
                    <div class="gb-inside-container load-more-inside-container">
                        <p class="gb-headline gb-headline-text load-more-headline-time">
                            <time class="entry-date published" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                <?php echo get_the_date(); ?>
                            </time>
                        </p>
                
                        <h2 class="gb-headline gb-headline-text load-more-title">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                        </h2>
                
                        <div class="gb-headline gb-headline-text load-more-excerpt">
                            <?php echo wp_trim_words(get_the_content(), 10, ' ...'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php echo do_shortcode("[related_users ID={$post_id} page={$page_number}]"); ?>
            </div>
            <?php
            endwhile;
        endif;
        wp_reset_postdata();
        $response['html'] = ob_get_clean();
    
        return rest_ensure_response($response);
    }
    
    public static function get_projects($request) {
        // Get parameters from the REST API request
        $program_id = $request['program_id'];

        // Get the program title
        $program = get_term($program_id, 'funding-agency');
        $program_title = $program ? $program->name : '';
        
        // Perform a custom query to retrieve projects based on $program_id
        $args = array(
            'post_type' => 'project', // Replace 'projects' with your actual post type
            'tax_query' => array(
                array(
                    'taxonomy' => 'funding-agency',
                    'field' => 'term_id',
                    'terms' => $program_id,
                ),
            ),
        );
    
        $query = new WP_Query($args);

        $projects_html = '';  // Initialize an empty string to store HTML markup
    
        if ($query->have_posts()) {
            $projects_html .= '<h3>' . esc_html($program_title) . '</h3>';

            while ($query->have_posts()) {
                $query->the_post();
    
                // Get the current post data
                $post = $query->post;
    
                ob_start();
                get_template_part('template-parts/projects/activity-banner', '', [
                    'post' => $post,
                ]);
                $projects_html .= ob_get_clean();
            }
            wp_reset_postdata();
        }
    
        $response['html'] = $projects_html;
    
        return rest_ensure_response($response);
    }

    public static function get_project($data) {
        // Get the project ID from the request
        $project_id = $data['id'];
    
        // Check if the project post type exists
        $project_post = get_post($project_id);
    
        if (!$project_post || $project_post->post_type !== 'project') {
            // Project not found or not of the correct post type
            $response = array(
                'error' => 'Project not found',
            );
            return rest_ensure_response($response);
        }
    
        // Get meta data for the project
        $meta_data = get_post_meta($project_id);
    
        // Get the featured image URL
        $featured_image_id = get_post_thumbnail_id($project_id);

        // Initialize variables to store metadata
        $featured_image_url = null;
        $featured_image_title = null;
        $filesize = null;
    
        if ($featured_image_id){
            // Get featured image URL
            $featured_image_url = wp_get_attachment_url($featured_image_id, 'thumbnail');
    
            // Get featured image metadata
            $featured_image_metadata = wp_get_attachment_metadata($featured_image_id);
    
            // Check if metadata exists and contains 'file' property
            if (is_array($featured_image_metadata) && isset($featured_image_metadata['file'])) {
                // Get the title of the image to use as the filename
                $featured_image_title = get_the_title($featured_image_id);
    
                // Format the filesize into a human-readable format
                $filesize = isset($featured_image_metadata['filesize'])
                    ? self::formatBytes($featured_image_metadata['filesize'])
                    : 'N/A';
            }
        }
    
        // Prepare the response
        $response = array(
            'message'         => 'Meta data for Project ID ' . $project_id,
            'meta_data'       => $meta_data,
            'featured_image' => array(
                'ID' => $featured_image_id,
                'url' => $featured_image_url,
                'title' => $featured_image_title ? sanitize_title($featured_image_title) : null,
                'filename' => isset($featured_image_metadata['file']) ? $featured_image_metadata['file'] : null,
                'filesize' => $filesize,
                // Add more fields as needed
            ),
        );
    
        return rest_ensure_response($response);
    }
    

    public static function get_post($request) {
        $post_id = $request['id'];
    
        // Check if the post exists
        $post = get_post($post_id);
    
        if (empty($post) || is_wp_error($post)) {
            return new WP_Error('not_found', 'Post not found', array('status' => 404));
        }
    
        // Get featured image ID
        $featured_image_id = get_post_thumbnail_id($post->ID);
    
        // Get post date, time, and status
        $post_date = date('m/d/Y', strtotime(get_the_date('Y-m-d', $post->ID)));
        $post_time = get_the_time('h:i A', $post->ID); // Format as hh:mm am/pm
        $post_status = $post->post_status;
    
        // Initialize variables to store metadata
        $featured_image_url = null;
        $featured_image_title = null;
        $featured_image_caption = null;
        $filesize = null;
    
        // Check if featured image ID is valid
        if ($featured_image_id) {
            // Get featured image URL
            $featured_image_url = wp_get_attachment_url($featured_image_id, 'thumbnail');
    
            // Get featured image metadata
            $featured_image_metadata = wp_get_attachment_metadata($featured_image_id);
    
            // Check if metadata exists and contains 'file' property
            if (is_array($featured_image_metadata) && isset($featured_image_metadata['file'])) {
                // Get the title of the image to use as the filename
                $featured_image_title = get_the_title($featured_image_id);
                $featured_image_caption = get_post_field('post_excerpt', $featured_image_id);
    
                // Format the filesize into a human-readable format
                $filesize = isset($featured_image_metadata['filesize'])
                    ? self::formatBytes($featured_image_metadata['filesize'])
                    : 'N/A';
            }
        }

        $additional_images = get_field('additional_images', $post_id);

        $attachment_images_data = [];

        if($additional_images) {
            foreach ($additional_images as $image_id) {
                $attachment_metadata = wp_get_attachment_metadata($image_id);
    
                $attachment_images_data[] = array(
                    'ID'        => $image_id,
                    'url'       => wp_get_attachment_url($image_id),
                    'title'     => get_the_title($image_id),
                    'caption'   => wp_get_attachment_caption($image_id), // Use wp_get_attachment_caption to get the caption
                    'filename'  => wp_basename(wp_get_attachment_url($image_id)),
                    'filesize'  => isset($attachment_metadata['filesize'])
                        ? self::formatBytes($attachment_metadata['filesize'])
                        : 'N/A',
                    // Add more fields as needed
                );
            }
        }
        

        // Get post meta data for related staff
        $related_staff = get_field('field_65021ce5287c6', $post_id);

        // Get post meta data for related projects
        $related_projects = get_field('field_65a6fa6acef28', $post_id);
    
        $response = array(
            'ID' => $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_date' => $post_date,
            'post_time' => $post_time,
            'post_status' => $post_status,
            'featured_image' => array(
                'ID' => $featured_image_id,
                'url' => $featured_image_url,
                'title' => $featured_image_title ? sanitize_title($featured_image_title) : null,
                'caption' => $featured_image_caption ? $featured_image_caption : null,
                'filename' => isset($featured_image_metadata['file']) ? $featured_image_metadata['file'] : null,
                'filesize' => $filesize,
                // Add more fields as needed
            ),
            'related_staff' => $related_staff,
            'related_projects' => $related_projects,
            'additional_images' => $attachment_images_data,
            // Add more fields as needed
        );
    
        return rest_ensure_response($response);
    }

    public static function get_user($request) {
        $user_id = $request['id'];

        // Check if user ID is provided
        if (empty($user_id)) {
            return new WP_Error('missing_user_id', 'User ID is required.', array('status' => 400));
        }

        $user = get_user_by('ID', $user_id);

        if (!$user) {
            return new WP_Error('user_not_found', 'User not found.', array('status' => 404));
        }

        $nickname = get_user_meta($user_id, 'nickname', true);
        $address = get_user_meta($user_id, 'location', true);

        // Prepare the response
        $response = array(
            'nickname' => $nickname,
            'address' => $address,
        );

        return rest_ensure_response($response);
    }
    
    // Function to format file size in a human-readable format
    private static function formatBytes($bytes, $decimals = 2) {
        $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
    }
    
     
}
