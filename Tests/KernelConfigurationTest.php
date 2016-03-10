<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class KernelConfigurationTest extends AbstractConfigTest
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\DependencyInjection\ContainerBuilder')) {
            $this->markTestSkipped("This test can only run with symfony/dependency-injection component alongside");
        }
    }

    private function getContainer()
    {
        // Code inspired by the SncRedisBundle, all credits to its authors.
        return new ContainerBuilder(new ParameterBag([
            'kernel.debug'        => false,
            'kernel.bundles'      => [],
            'kernel.cache_dir'    => sys_get_temp_dir(),
            'kernel.environment'  => 'test',
            'kernel.root_dir'     => __DIR__ . '/../../',
        ]));
    }

    public function testBasicConfiguration()
    {
        $this->getContainer();
    }

    public function testInjection()
    {
        $this->getContainer();
    }
}
