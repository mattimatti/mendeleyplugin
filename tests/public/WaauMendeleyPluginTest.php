<?php
use PHPUnit\Framework\TestCase;

final class WaauMendeleyPluginTest extends \WP_Mock\Tools\TestCase
{

    public function setUp()
    {
        \WP_Mock::setUp();
    }

    public function tearDown()
    {
        \WP_Mock::tearDown();
    }

    public function testInstance(): void
    {
        $instance = new WaauMendeleyPlugin();
        $this->assertNotNull($instance);
    }

}
