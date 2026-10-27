<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Tests never need the compiled assets; without this every page that uses
        // @vite fails when public/build has not been built (npm run build).
        $this->withoutVite();
    }
}
