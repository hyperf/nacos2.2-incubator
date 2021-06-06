<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace HyperfTest\Nacos;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Nacos\Constants;
use Hyperf\NacosSdk\Application;
use Hyperf\NacosSdk\Config;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;

class ContainerStub
{
    public static function getContainer()
    {
        $container = \Mockery::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);

        $container->shouldReceive('get')->with(Application::class)->andReturnUsing(function () {
            return new Application(new Config([
                'guzzle_config' => [
                    'handler' => new HandlerMockery(),
                    'headers' => [
                        'charset' => 'UTF-8',
                    ],
                ],
            ]));
        });

        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturn(new \Hyperf\Config\Config([
            'nacos' => [
                'host' => '127.0.0.1',
                'port' => 8848,
                'username' => null,
                'password' => null,
                'config' => [
                    'enable' => true,
                    'merge_mode' => Constants::CONFIG_MERGE_OVERWRITE,
                    'reload_interval' => 3,
                    'default_key' => 'nacos_default_config',
                    'listener_config' => [
                        'nacos_config' => [
                            'tenant' => 'tenant',
                            'data_id' => 'json',
                            'group' => 'DEFAULT_GROUP',
                            'type' => 'json',
                        ],
                        'nacos_config.data' => [
                            'data_id' => 'text',
                            'group' => 'DEFAULT_GROUP',
                        ],
                        [
                            'data_id' => 'json2',
                            'group' => 'DEFAULT_GROUP',
                            'type' => 'json',
                        ],
                    ],
                ],
            ],
        ]));

        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturnUsing(function () {
            $logger = \Mockery::mock(StdoutLoggerInterface::class);
            $logger->shouldReceive('warning')->andReturnFalse();
            return $logger;
        });

        return $container;
    }
}
