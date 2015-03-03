<?php

namespace Psy\Test\Plugin;

use Psy\Plugin\PluginManager;

class ManagerTest extends \PHPUnit_Framework_TestCase
{
    public function setup()
    {
        $prop = new \ReflectionProperty('Psy\Plugin\PluginManager', 'plugins');
        $prop->setAccessible(true);
        $prop->setValue('Psy\Plugin\Manager', array());
    }

    public function testRegisterMultiplePlugins()
    {
        $mockedPlugin = $this->getMock('Psy\Plugin\AbstractPlugin');
        PluginManager::register($mockedPlugin, 'mock1');
        PluginManager::register($mockedPlugin, 'mock2');

        $prop = new \ReflectionProperty('Psy\Plugin\PluginManager', 'plugins');
        $prop->setAccessible(true);
        $plugins = $prop->getValue('Psy\Plugin\PluginManager');
        $this->assertArrayHasKey('mock1', $plugins);
        $this->assertArrayHasKey('mock2', $plugins);
    }

    public function testConfigurationWithSinglePlugin()
    {
        $commands = array(
            'cmd1', 'cmd2',
        );

        $presenters = array(
            'presenter1', 'presenter2',
        );

        $matchers = array(
            'matcher1', 'matcher2',
        );

        $stub = new PluginStub();
        $stub->setCommands($commands);
        $stub->setPresenters($presenters);
        $stub->setMatchers($matchers);

        PluginManager::register($stub, 'mock');

        $config = PluginManager::getConfiguration();
        $this->assertArraySubset($commands, $config['commands']);
        $this->assertArraySubset($presenters, $config['presenters']);
        $this->assertArraySubset($matchers, $config['matchers']);
    }

    public function testConfigurationWithMultiplePlugins()
    {
        $commands1 = array(
            'cmd1', 'cmd2',
        );

        $presenters1 = array(
            'presenter1', 'presenter2',
        );

        $matchers1 = array(
            'matcher1', 'matcher2',
        );

        $stub1 = new PluginStub();
        $stub1->setCommands($commands1);
        $stub1->setPresenters($presenters1);
        $stub1->setMatchers($matchers1);

        PluginManager::register($stub1, 'mock1');

        $commands2 = array(
            'cmd3', 'cmd4',
        );

        $presenters2 = array(
            'presenter3', 'presenter4',
        );

        $matchers2 = array(
            'matcher3', 'matcher4',
        );

        $stub2 = new PluginStub();
        $stub2->setCommands($commands2);
        $stub2->setPresenters($presenters2);
        $stub2->setMatchers($matchers2);

        PluginManager::register($stub2, 'mock2');

        $config = PluginManager::getConfiguration();
        $this->assertArraySubset($commands1, $config['commands']);
        $this->assertArraySubset($presenters1, $config['presenters']);
        $this->assertArraySubset($matchers1, $config['matchers']);
    }
}
