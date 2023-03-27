<?php
//https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/5aab03c77b1cb512787ff20ff80b9758280dc0e0/doc/config.rst
$config = new PhpCsFixer\Config();
return $config
    ->setRiskyAllowed(true)
    ->setRules(
        [
            '@PSR2' => true,
            'psr_autoloading' => true,
            'align_multiline_comment' => true,
            'array_syntax' => [
                'syntax' => 'short',
            ],
            'binary_operator_spaces' => [
                'align_double_arrow' => false,
                'align_equals' => false,
            ],
            'binary_operator_spaces' => true,
            'blank_line_after_opening_tag' => true,
            'blank_line_before_statement' => [
                'statements' => [
                    'return',
                    'throw',
                ],
            ],
            'cast_spaces' => [
                'space' => 'single',
            ],
            'concat_space' => [
                'spacing' => 'one',
            ],
            'declare_equal_normalize' => [
                'space' => 'single',
            ],
            'method_argument_space' => [
                'keep_multiple_spaces_after_comma' => false,
                'on_multiline' => 'ensure_fully_multiline',
            ],
            'method_chaining_indentation' => true,
            'modernize_types_casting' => true,
            'function_typehint_space' => true,
            'multiline_comment_opening_closing' => false,
            'multiline_whitespace_before_semicolons' => true,
            'general_phpdoc_annotation_remove' => true,
            'include' => true,
            'indentation_type' => true,
            'line_ending' => true,
            'linebreak_after_opening_tag' => true,
            'lowercase_cast' => true,
            'mb_str_functions' => true,
            'magic_method_casing' => true,
            'magic_constant_casing' => true,
            'lowercase_static_reference' => true,
            'lowercase_keywords' => true,
            'phpdoc_separation' => true,
            'modernize_types_casting' => true,
            'native_function_casing' => true,
            'no_blank_lines_after_class_opening' => true,
            'no_blank_lines_after_phpdoc' => true,
            'no_closing_tag' => true,
            'no_empty_comment' => true,
            'no_empty_phpdoc' => true,
            'no_empty_statement' => true,
            'no_extra_blank_lines' => true,
            'no_leading_import_slash' => true,
            'no_mixed_echo_print' => [
                'use' => 'echo',
            ],
            'multiline_whitespace_before_semicolons' => true,
            'no_php4_constructor' => true,
            'no_short_bool_cast' => true,
            'no_trailing_comma_in_singleline_array' => true,
            'no_trailing_whitespace' => true,
            'no_unused_imports' => true,
            'no_whitespace_before_comma_in_array' => true,
            'no_whitespace_in_blank_line' => true,
            'object_operator_without_whitespace' => true,
            'ordered_imports' => true,
            'phpdoc_add_missing_param_annotation' => [
                'only_untyped' => false,
            ],
            'phpdoc_indent' => true,
            'general_phpdoc_tag_rename' => true,
            'phpdoc_inline_tag_normalizer' => true,
            'phpdoc_tag_type' => true,
            'phpdoc_no_alias_tag' => true,
            'phpdoc_no_empty_return' => true,
            'phpdoc_no_package' => true,
            'phpdoc_no_useless_inheritdoc' => true,
            'phpdoc_order' => true,
            'phpdoc_scalar' => true,
            'phpdoc_separation' => true,
            'phpdoc_trim' => true,
            'phpdoc_types' => true,
            'phpdoc_var_without_name' => true,
            'random_api_migration' => true,
            'return_type_declaration' => true,
            'short_scalar_cast' => true,
            'single_blank_line_before_namespace' => true,
            'single_line_comment_style' => [
                'comment_types' => [
                    'asterisk',
                    'hash',
                ],
            ],
            'single_quote' => true,
            'space_after_semicolon' => true,
            'standardize_not_equals' => true,
            'switch_case_semicolon_to_colon' => true,
            'ternary_operator_spaces' => true,
            'ternary_to_null_coalescing' => true,
            'trailing_comma_in_multiline' => true,
            'trim_array_spaces' => true,
            'unary_operator_spaces' => true,
            'yoda_style' => false,
        ]
    );
