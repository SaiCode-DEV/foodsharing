<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('docker')
    ->exclude('lang')
    ->exclude('fonts')
    ->exclude('websocket')
    ->exclude('images')
    ->exclude('light')
    ->exclude('scripts')
    ->exclude('js')
    ->exclude('vendor')
    ->notPath('tmp')
    ->notPath('lib/font')
    ->notPath('tests/Support/_generated')
    ->notPath('c3.php')
    ->notPath('src/Lib/Flourish')
    ->notPath('cache')
    ->notPath('client')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
$config->setRules([
    '@Symfony' => true,
    'concat_space' => ['spacing' => 'one'],
    'cast_spaces' => ['space' => 'none'],
    'phpdoc_align' => ['tags' => []],
    'trailing_comma_in_multiline' => false,
    'yoda_style' => [
        'equal' => null,
        'identical' => null,
    ],
    'single_line_comment_spacing' => false,
    'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
    'global_namespace_import' => false,
    'phpdoc_separation' => false,
    'nullable_type_declaration_for_default_null_value' => false,
    'operator_linebreak' => false,
    'array_indentation' => false,
])
    ->setFinder($finder);
return $config;
