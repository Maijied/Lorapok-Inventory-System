<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stops @vite() resolving the built manifest. These tests render Blade
        // but assert nothing about compiled assets, and requiring a frontend
        // build to run them would fail CI for the wrong reason. The build is
        // verified separately by the `assets` CI job.
        $this->withoutVite();
    }
}
