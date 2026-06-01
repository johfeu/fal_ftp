<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$finder = (new Symfony\Component\Finder\Finder())
    ->in(__DIR__)
    ->ignoreDotFiles(false)
    ->ignoreVCS(true)
    ->exclude([
        '.build',
        '.ddev',
        'Build',
        'vendor',
    ])
    ->name('/\.php$/')
;

$revertedSymfonyRules = [
    'cast_spaces' => [ // revert @Symfony
        'space' => 'none',
    ],
    'concat_space' => [ // revert @Symfony
        'spacing' => 'one',
    ],
    'increment_style' => false, // revert @Symfony
    'phpdoc_align' => false, // revert @Symfony
    'phpdoc_to_comment' => false, // revert @Symfony
    'single_line_comment_style' => true, // revert @Symfony
    'single_line_throw' => false, // revert @Symfony
    'yoda_style' => false, // revert @Symfony
];

$revertedPHP81Rules = [
    'octal_notation' => false,
];

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRiskyAllowed(false)
    ->setCacheFile(__DIR__ . '/.build/.php-cs-fixer.cache')
    ->setRules(array_merge_recursive([
        '@DoctrineAnnotation' => true,
        '@PHP8x2Migration' => true,
        '@Symfony' => true,
        'multiline_whitespace_before_semicolons' => [ // @PhpCsFixer
            'strategy' => 'new_line_for_chained_calls',
        ],
        'no_superfluous_elseif' => true, // @PhpCsFixer
        'no_useless_else' => true, // @PhpCsFixer
        'phpdoc_no_empty_return' => true, // @PhpCsFixer
        'nullable_type_declaration' => [
            'syntax' => 'union',
        ],
        'ordered_types' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'none',
        ],
    ], $revertedPHP81Rules, $revertedSymfonyRules))
    ->setFinder($finder)
;
