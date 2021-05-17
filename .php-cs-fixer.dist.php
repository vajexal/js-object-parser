<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12:risky'                           => true,
        'strict_param'                           => true,
        'array_syntax'                           => ['syntax' => 'short'],
        'cast_spaces'                            => ['space' => 'single'],
        'braces'                                 => ['allow_single_line_closure' => true,],
        'combine_consecutive_unsets'             => true,
        'function_to_constant'                   => true,
        'native_function_invocation'             => true,
        'multiline_whitespace_before_semicolons' => true,
        'no_unused_imports'                      => true,
        'no_useless_else'                        => true,
        'no_useless_return'                      => true,
        'no_whitespace_before_comma_in_array'    => true,
        'no_whitespace_in_blank_line'            => true,
        'non_printable_character'                => true,
        'php_unit_dedicate_assert'               => true,
        'php_unit_fqcn_annotation'               => true,
        'return_type_declaration'                => ['space_before' => 'none'],
        'short_scalar_cast'                      => true,
        'single_blank_line_before_namespace'     => true,
        'line_ending'                            => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
