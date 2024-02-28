<?php

class ACF_Form_Handler {
    
    public function __construct() {
        add_action('acf/save_post', array($this, 'update_user_fields'), 30);
    }

    public function update_user_fields($post_id) {
        if (is_page('edit-user')) {
    
            $user_id = str_replace('user_', '', $post_id);

            $field_groups = array(
                'field_658219e86331c', // profile pictures group field key
                'field_652f531642964', // professional interests group field key
                'field_65318b433fabd', // professional history group field key
                'field_65318bc05ad17' // honors and awards group field key
            );

            $profile_pictures_group_field = 'field_658219e86331c';
            $profile_pictures_group_subfields = array(
                'primary_picture' => ($_POST['acf']['field_65821a416331d']),
                'professional_history_picture' => ($_POST['acf']['field_65821a686331e']),
                'honors_and_awards_picture' => ($_POST['acf']['field_65821aa06331f']),
                'icon_picture' => ($_POST['acf']['field_65821aaf63320']),
            );

            $professional_interest_group_field = 'field_652f531642964';
            $professional_interest_group_subfields = array(
                'professional_interests_text' => ($_POST['acf']['field_6531887586121']),
                'professional_interests_images' => ($_POST['acf']['field_6553c9ab418c0']),
                'professional_interests_image_caption' => ($_POST['acf']['field_653188ad86124']),
            );

            $professional_history_group_field = 'field_65318b433fabd';
            $professional_history_group_subfields = array(
                'professional_history_text' => ($_POST['acf']['field_65318b433fabe']),
                'professional_history_images' => ($_POST['acf']['field_6553c9eac423f']),
                'professional_history_image_caption' => ($_POST['acf']['field_65318b433fac1']),
            );

            $honors_and_awards_group_field = 'field_65318bc05ad17';
            $honors_and_awards_group_subfields = array(
                'honors_and_awards_text' => ($_POST['acf']['field_65318bc05ad18']),
                'honors_and_awards_images' => ($_POST['acf']['field_6553ca6855149']),
                'honors_and_awards_image_caption' => ($_POST['acf']['field_65318bc05ad1b']),
            );
    
            $meta_fields = array(
                'location' => sanitize_text_field($_POST['acf']['field_652f5316338be']),
                'cv' => sanitize_text_field($_POST['acf']['field_652f53163ade2']),
                'publications_link' => sanitize_text_field($_POST['acf']['field_6579fb74c2164']),
                'publications_url' => sanitize_text_field($_POST['acf']['field_65c29cc948514']),
                'personal_page' => sanitize_text_field($_POST['acf']['field_6531839aff808']),
                'display_in_directory' => sanitize_text_field($_POST['acf']['field_653fddd65f0b3']),
                'targets_of_interests' => sanitize_text_field($_POST['acf']['field_6594dae77bc2e']),
                'disciplines_techniques' => sanitize_text_field($_POST['acf']['field_6594dafa7bc2f']),
                'missions' => sanitize_text_field($_POST['acf']['field_6594db127bc30']),
                'mission_roles' => sanitize_text_field($_POST['acf']['field_6594db247bc31']),
                'instruments' => sanitize_text_field($_POST['acf']['field_6594db2c7bc32']),
                'facilities' => sanitize_text_field($_POST['acf']['field_6594db387bc33']),
                'twitter_x' => sanitize_text_field($_POST['acf']['field_65ca58de9e649']),
                'linkedin' => sanitize_text_field($_POST['acf']['field_65ca58f69e64a']),
                'youtube' => sanitize_text_field($_POST['acf']['field_65ca59179e64c']),
                'facebook' => sanitize_text_field($_POST['acf']['field_65ca59099e64b']),
                'instagram' => sanitize_text_field($_POST['acf']['field_65ca59209e64d']),
                'github' => sanitize_text_field($_POST['acf']['field_65d905aa86ece']),
                'orchid' => sanitize_text_field($_POST['acf']['field_65d932851c5fa']),
                'gscholar' => sanitize_text_field($_POST['acf']['field_65d932951c5fb']),
            );
    
            foreach ($meta_fields as $meta_key => $meta_value) {
                update_field($meta_key, $meta_value, 'user_'.$user_id);
            }

            update_field($profile_pictures_group_field, $profile_pictures_group_subfields, 'user_'.$user_id);
            update_field($professional_interest_group_field, $professional_interest_group_subfields, 'user_'.$user_id);
            update_field($professional_history_group_field, $professional_history_group_subfields, 'user_'.$user_id);
            update_field($honors_and_awards_group_field, $honors_and_awards_group_subfields, 'user_'.$user_id);
        }
    }


}

new ACF_Form_Handler();