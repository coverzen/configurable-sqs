<?php declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

/**
 * Finder.
 *
 * @see https://symfony.com/doc/4.4/components/finder.html
 *
 * @var Finder $finder
 */
$finder = Finder::create()
                ->ignoreUnreadableDirs()
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->name('*.php')
                ->in(__DIR__)
                ->exclude('.ddev')
                ->exclude('node_modules')
                ->exclude('vendor')
                ->notPath('_ide_helper.php')
                ->sortByModifiedTime();

/**
 * Configuration.
 *
 * @see https://cs.symfony.com/doc/ruleSets/
 * @see https://mlocati.github.io/php-cs-fixer-configurator
 *
 * @var Config $config
 */
$config = new Config();

return $config->setUsingCache(true)
              ->setRiskyAllowed(true)
              ->setLineEnding("\n")
              ->setFinder($finder)
              ->setRules(
                  [
                      '@PSR12' => true,
                      '@PSR12:risky' => true,
                      '@Symfony' => true,
                      '@Symfony:risky' => true,
                      '@PHPUnit100Migration:risky' => true,
                      'blank_line_after_opening_tag' => false,
                      'combine_consecutive_unsets' => true,
                      'concat_space' => [
                          'spacing' => 'one',
                      ],
                      'linebreak_after_opening_tag' => false,
                      'mb_str_functions' => true,
                      'native_function_invocation' => false,
                      'no_superfluous_phpdoc_tags' => false,
                      'no_useless_else' => true,
                      'no_useless_return' => true,
                      'ordered_imports' => [
                          'imports_order' => [
                              'class', 'function', 'const',
                          ],
                          'sort_algorithm' => 'alpha',
                      ],
                      'php_unit_strict' => true,
                      'phpdoc_align' => false,
                      'phpdoc_add_missing_param_annotation' => [
                          'only_untyped' => false,
                      ],
                      'phpdoc_no_empty_return' => false,
                      'phpdoc_order' => true,
                      'phpdoc_separation' => false,
                      'phpdoc_to_comment' => false,
                      'phpdoc_var_without_name' => false,
                      'self_accessor' => false,
                      'single_import_per_statement' => false,
                      'single_trait_insert_per_statement' => false,
                      'strict_comparison' => true,
                      'strict_param' => true,
                      'yoda_style' => false,
                      'php_unit_test_annotation' => [
                          'style' => 'annotation',
                      ],
                      'global_namespace_import' => [
                          'import_classes' => true,
                          'import_constants' => true,
                          'import_functions' => true,
                      ],
                      'binary_operator_spaces' => [
                          'operators' => [
                              '|' => null,
                          ],
                      ],
                      'php_unit_method_casing' => false,
                      'phpdoc_no_alias_tag' => [
                          'replacements' => [
                              'type' => 'var',
                          ],
                      ],
                      'align_multiline_comment' => [
                          'comment_type' => 'phpdocs_like',
                      ],
                      'blank_line_between_import_groups' => false,
                      'use_arrow_functions' => true,
                      'nullable_type_declaration_for_default_null_value' => false,
                      'operator_linebreak' => [
                          'only_booleans' => true,
                          'position' => 'end',
                      ],
                      'native_constant_invocation' => [
                          'fix_built_in' => false,
                      ],
                  ]
              );
