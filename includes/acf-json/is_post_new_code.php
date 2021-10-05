<?php
exit;
//this is just for reference purpose
if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_609a881d82e8f',
        'title' => 'hidden_fields',
        'fields' => array(
            array(
                'key' => 'field_609a883e62def',
                'label' => 'is_post_new',
                'name' => 'is_post_new',
                'type' => 'text',
                'instructions' => 'do not use this, only for internal programmatic usage.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => 'not_new',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
                'maxlength' => '',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'post',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'side',
        'style' => 'seamless',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => array(
            0 => 'permalink',
            1 => 'the_content',
            2 => 'excerpt',
            3 => 'discussion',
            4 => 'comments',
            5 => 'revisions',
            6 => 'slug',
            7 => 'author',
            8 => 'format',
            9 => 'page_attributes',
            10 => 'featured_image',
            11 => 'categories',
            12 => 'tags',
            13 => 'send-trackbacks',
        ),
        'active' => true,
        'description' => '',
    ));

endif;
