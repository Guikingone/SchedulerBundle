<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();

return $config->setRules([
    '@PSR1' => true,
    '@PSR2' => true,
    '@PSR12' => true,
    '@PHP74Migration' => true,
    'array_indentation' => true,
    'array_syntax' => [
        'syntax' => 'short',
    ],
    'array_push' => true,
    'date_time_immutable' => true,
    'declare_strict_types' => true,
    'fully_qualified_strict_types' => true,
    'global_namespace_import' => [
        'import_classes' => true,
        'import_constants' => true,
        'import_functions' => true,
    ],
    'implode_call' => true,
    'no_multiline_whitespace_around_double_arrow' => true,
    'no_trailing_comma_in_singleline_array' => true,
    'no_unused_imports' => true,
    'no_useless_return' => true,
    'return_assignment' => true,
    'short_scalar_cast' => true,
    'simplified_null_return' => true,
    'single_line_throw' => true,
    'strict_comparison' => true,
    'strict_param' => true,
    'use_arrow_functions' => true,
    'void_return' => true,
])->setFinder($finder);
