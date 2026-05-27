<?php

declare(strict_types=1);

use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/*
|--------------------------------------------------------------------------
| "No `new XxxData`" guardrail (AST-level)
|--------------------------------------------------------------------------
| Input DTOs (App\Data) must be built only through their named constructors
| (fromRequest / fromValues), which validate. Direct instantiation would
| bypass validation. The Pest arch DSL cannot distinguish `new` from a mere
| type reference, so we walk the AST with php-parser instead.
*/

it('forbids constructing *Data DTOs via new — use fromRequest/fromValues', function () {
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $finder = new NodeFinder;
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();

        // The Data classes themselves are allowed to reference their own type.
        if (str_contains($path, DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $ast = $parser->parse((string) file_get_contents($path)) ?? [];

        foreach ($finder->findInstanceOf($ast, New_::class) as $new) {
            if ($new->class instanceof Name && str_ends_with($new->class->toString(), 'Data')) {
                $offenders[] = str_replace(app_path(), 'app', $path).' → new '.$new->class->toString();
            }
        }
    }

    expect($offenders)->toBe([]);
});
