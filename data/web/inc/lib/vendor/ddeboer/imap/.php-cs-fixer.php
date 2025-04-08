<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@DoctrineAnnotation'                       => true,
        '@Symfony'                                  => true,
        '@Symfony:risky'                            => true,
        '@PHPUnit75Migration:risky'                 => true,
        '@PHP71Migration'                           => true,
        '@PHP70Migration:risky'                     => true, // @TODO with next major version
        'align_multiline_comment'                   => ['comment_type' => 'all_multiline'],
        'array_indentation'                         => true,
        'array_syntax'                              => ['syntax' => 'short'],
        'binary_operator_spaces'                    => ['default' => 'align_single_space'],
        'blank_line_before_statement'               => true,
        'class_definition'                          => ['single_item_single_line' => true],
        'compact_nullable_typehint'                 => true,
        'concat_space'                              => ['spacing' => 'one'],
        'echo_tag_syntax'                           => ['format' => 'long'],
        'error_suppression'                         => false,
        'escape_implicit_backslashes'               => true,
        'explicit_indirect_variable'                => true,
        'explicit_string_variable'                  => true,
        'fully_qualified_strict_types'              => true,
        'heredoc_to_nowdoc'                         => true,
        'list_syntax'                               => ['syntax' => 'long'],
        'method_argument_space'                     => ['on_multiline' => 'ensure_fully_multiline'],
        'method_chaining_indentation'               => true,
        'multiline_comment_opening_closing'         => true,
        'multiline_whitespace_before_semicolons'    => ['strategy' => 'new_line_for_chained_calls'],
        'native_constant_invocation'                => true,
        'native_function_invocation'                => ['include' => ['@internal']],
        'no_alternative_syntax'                     => true,
        'no_break_comment'                          => true,
        'no_extra_blank_lines'                      => ['tokens' => ['break', 'continue', 'extra', 'return', 'throw', 'use', 'parenthesis_brace_block', 'square_brace_block', 'curly_brace_block']],
        'no_null_property_initialization'           => true,
        'no_php4_constructor'                       => true,
        'no_superfluous_elseif'                     => true,
        'no_unneeded_curly_braces'                  => true,
        'no_unneeded_final_method'                  => true,
        'no_unreachable_default_argument_value'     => true,
        'no_useless_else'                           => true,
        'no_useless_return'                         => true,
        'ordered_imports'                           => true,
        'php_unit_method_casing'                    => true,
        'php_unit_set_up_tear_down_visibility'      => true,
        'php_unit_strict'                           => true,
        'php_unit_test_annotation'                  => true,
        'php_unit_test_case_static_method_calls'    => true,
        'php_unit_test_class_requires_covers'       => false,
        'phpdoc_add_missing_param_annotation'       => true,
        'phpdoc_order'                              => true,
        'phpdoc_order_by_value'                     => true,
        'phpdoc_types_order'                        => true,
        'random_api_migration'                      => true,
        'semicolon_after_instruction'               => true,
        'simplified_null_return'                    => true,
        'single_line_comment_style'                 => true,
        'single_line_throw'                         => false,
        'space_after_semicolon'                     => true,
        'static_lambda'                             => true,
        'strict_comparison'                         => true,
        'string_line_ending'                        => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/src')
            ->in(__DIR__ . '/tests')
    )
;
