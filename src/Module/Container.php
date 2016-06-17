<?php

/*
 * This file is part of the Arachne
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Codeception\Module;

use Codeception\Module;
use Codeception\TestInterface;
use Nette\Caching\Storages\IJournal;
use Nette\Caching\Storages\SQLiteJournal;
use Nette\Configurator;
use Nette\DI\MissingServiceException;
use Nette\Http\Session;
use Nette\Utils\FileSystem;
use ReflectionProperty;

class Container extends Module
{
    protected $config = [
        'configFiles' => [],
        'logDir' => null,
        'debugMode' => null,
        'configurator' => Configurator::class,
    ];

    protected $requiredFields = [
        'tempDir',
    ];

    /**
     * @var string
     */
    private $path;

    /**
     * @var Container
     */
    private $container;

    public function _beforeSuite($settings = [])
    {
        $this->path = $settings['path'];
    }

    public function _before(TestInterface $test)
    {
        $tempDir = $this->path.'/'.$this->config['tempDir'];
        FileSystem::delete($tempDir);
        FileSystem::createDir($tempDir);
        $this->container = null;
    }

    public function _after(TestInterface $test)
    {
        if ($this->container) {
            try {
                $this->container->getByType(Session::class)->close();
            } catch (MissingServiceException $e) {
            }

            try {
                $journal = $this->container->getByType(IJournal::class);
                if ($journal instanceof SQLiteJournal) {
                    $property = new ReflectionProperty(SQLiteJournal::class, 'pdo');
                    $property->setAccessible(true);
                    $property->setValue($journal, null);
                }
            } catch (MissingServiceException $e) {
            }

            FileSystem::delete($this->container->getParameters()['tempDir']);
        }
    }

    public function createContainer(array $configFiles = null)
    {
        if ($this->container) {
            $this->fail('Can\'t create more than one container.');
        }

        $configurator = new $this->config['configurator']();

        if ($this->config['logDir']) {
            $configurator->enableDebugger($this->path.'/'.$this->config['logDir']);
        }

        $tempDir = $this->path.'/'.$this->config['tempDir'];
        FileSystem::delete($tempDir);
        FileSystem::createDir($tempDir);
        $configurator->setTempDirectory($tempDir);

        if ($this->config['debugMode'] !== null) {
            $configurator->setDebugMode($this->config['debugMode']);
        }

        $configFiles = is_array($configFiles) ? $configFiles : $this->config['configFiles'];
        foreach ($configFiles as $file) {
            $configurator->addConfig($this->path.'/'.$file, false);
        }

        $this->container = $configurator->createContainer();

        return $this->container;
    }

    /**
     * @param string $service
     *
     * @return object
     */
    public function grabService($service)
    {
        try {
            return call_user_func($this->containerAccessor)->getByType($service);
        } catch (MissingServiceException $e) {
            $this->fail($e->getMessage());
        }
    }

}
