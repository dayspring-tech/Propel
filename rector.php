<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\OptionalParametersAfterRequiredRector;
use Rector\Config\RectorConfig;
use Rector\Php73\Rector\FuncCall\StringifyStrNeedlesRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Transform\Rector\ClassMethod\ReturnTypeWillChangeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictScalarReturnExprRector;



return RectorConfig::configure()
    ->withRules([
        ReturnTypeFromStrictNativeCallRector::class,
        ReturnTypeFromStrictScalarReturnExprRector::class,
        StringifyStrNeedlesRector::class,
        NullToStrictStringFuncCallArgRector::class,
//        ReturnTypeWillChangeRector::class,
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/test/fixtures/bookstore-packaged/build/',
        __DIR__ . '/test/fixtures/bookstore/build/',
        __DIR__ . '/test/fixtures/namespaced/build/',
        __DIR__ . '/test/fixtures/nestedset/build/',
        __DIR__ . '/test/fixtures/schemas/build/',
        __DIR__ . '/test/fixtures/treetest/build/',
        __DIR__ . '/generator/pear/build/',
        __DIR__ . '/runtime/pear/build/',
    ])
    ->withPaths([__DIR__]);
