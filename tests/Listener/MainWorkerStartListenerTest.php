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
namespace HyperfTest\Nacos\Listener;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Nacos\Listener\MainWorkerStartListener;
use HyperfTest\Nacos\ContainerStub;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 * @coversNothing
 */
class MainWorkerStartListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testRegisterServiceAndInstance()
    {
        $handler = function (RequestInterface $request) {
            $uri = $request->getUri()->getPath();
            $method = $request->getMethod();
            var_dump($uri, $method);
            $data = '{}';
            switch ($uri) {
                case '/nacos/v1/ns/service':
                    if ($method === 'PUT') {
                        $data = 'ok';
                    }
                    break;
                case '/nacos/v1/ns/instance':
                    if ($method === 'PUT') {
                        $data = 'ok';
                    }
                    break;
            }

            return new FulfilledPromise(new Psr7\Response(
                200,
                [],
                $data
            ));
        };
        $container = ContainerStub::getContainer($handler);

        $listener = new MainWorkerStartListener($container);

        $listener->process(new MainWorkerStart(new \stdClass(), 1));

        $this->assertTrue(true);
    }
}
