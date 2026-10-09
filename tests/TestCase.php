<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Los tests no cargan el CSS/JS real, así no dependen de haber corrido `npm run build`.
        $this->withoutVite();
    }
}
