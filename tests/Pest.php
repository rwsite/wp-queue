<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses()
    ->beforeEach(function () {
        \Brain\Monkey\setUp();
    })
    ->afterEach(function () {
        \Brain\Monkey\tearDown();
    })
    ->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function fakeWpOption(string $key, mixed $value): void
{
    \Brain\Monkey\Functions\expect('get_option')
        ->with($key, \Mockery::any())
        ->andReturn($value);
}

function fakeWpTransient(string $key, mixed $value): void
{
    \Brain\Monkey\Functions\expect('get_transient')
        ->with($key)
        ->andReturn($value);
}
