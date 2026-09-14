<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nessun test parla con servizi veri: una chiamata non simulata e' un errore.
        Http::preventStrayRequests();
    }
}
