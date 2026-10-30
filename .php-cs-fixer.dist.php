<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/public']);

return (new PhpCsFixer\Config())
    // The parallel runner hangs inside the Docker container; the codebase is small enough for sequential runs.
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::sequential())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'strict_comparison' => true,
    ])
    ->setFinder($finder);
