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
namespace Hyperf\Nacos\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Nacos\Client;
use Hyperf\Nacos\Config\ConfigManager;
use Hyperf\Nacos\Exception\RequestException;
use Hyperf\Nacos\Instance;
use Hyperf\Nacos\Service\IPReaderInterface;
use Hyperf\NacosSdk\Application;
use Hyperf\Server\Event\MainCoroutineServerStart;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use Psr\Container\ContainerInterface;

class MainWorkerStartListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    public function listen(): array
    {
        return [
            MainWorkerStart::class,
            MainCoroutineServerStart::class,
        ];
    }

    public function process(object $event)
    {
        $config = $this->container->get(ConfigInterface::class);

        if (! $config->get('nacos')) {
            return;
        }
        if (! $config->get('nacos.enable', true)) {
            return;
        }

        $serviceConfig = $config->get('nacos.service', []);
        if (! $serviceConfig) {
            return;
        }

        $serviceName = $serviceConfig['service_name'];
        $groupName = $serviceConfig['group_name'] ?? null;
        $namespaceId = $serviceConfig['namespace_id'] ?? null;
        $protectThreshold = $serviceConfig['protect_threshold'] ?? null;
        $metadata = $serviceConfig['metadata'] ?? null;
        $selector = $serviceConfig['selector'] ?? null;

        try {
            $client = $this->container->get(Application::class);

            // Register Service to Nacos.
            $response = $client->service->detail($serviceName, $groupName, $namespaceId);
            $optional = [
                'groupName' => $groupName,
                'namespaceId' => $namespaceId,
                'protectThreshold' => $protectThreshold,
                'metadata' => $metadata,
                'selector' => $selector,
            ];
            switch ($response->getStatusCode()) {
                case 404:
                    $response = $client->service->create($serviceName, $optional);
                    if ($response->getStatusCode() !== 200 || (string) $response->getBody() !== 'ok') {
                        throw new RequestException(sprintf('Failed to create nacos service %s!', $serviceName));
                    }

                    $this->logger->info(sprintf('Nacos service %s was created successfully!', $serviceName));
                    break;
                case 200:
                    $response = $client->service->update($serviceName, $optional);
                    if ($response->getStatusCode() !== 200 || (string) $response->getBody() !== 'ok') {
                        throw new RequestException(sprintf('Failed to update nacos service %s!', $serviceName));
                    }

                    $this->logger->info(sprintf('Nacos service %s was updated successfully!', $serviceName));
                    break;
                default:
                    throw new RequestException((string) $response->getBody(), $response->getStatusCode());
            }

            // Register Instance to Nacos.
            $instanceConfig = $serviceConfig['instance'] ?? [];
            $ephemeral = $instanceConfig['ephemeral'] ?? null;
            $cluster = $instanceConfig['cluster'] ?? null;
            $weight = $instanceConfig['weight'] ?? null;
            $metadata = $instanceConfig['metadata'] ?? null;

            /** @var IPReaderInterface $ipReader */
            $ipReader = $this->container->get($instanceConfig['ip']);
            $ip = $ipReader->read();
            $optional = [
                'groupName' => $groupName,
                'namespaceId' => $namespaceId,
                'ephemeral' => $ephemeral,
            ];

            $optionalData = array_merge($optional, [
                'clusterName' => $cluster,
                'weight' => $weight,
                'metadata' => $metadata,
                'enabled' => true,
            ]);

            $ports = $config->get('server.servers', []);
            foreach ($ports as $portServer) {
                $port = (int) $portServer['port'];
                $response = $client->instance->detail($ip, $port, $serviceName, array_merge($optional, [
                    'cluster' => $cluster,
                ]));

                switch ($response->getStatusCode()) {
                    case 404:
                        $response = $client->instance->register($ip, $port, $serviceName, $optionalData);
                        if ($response->getStatusCode() !== 200 || (string) $response->getBody() !== 'ok') {
                            throw new RequestException(sprintf('Failed to create nacos instance %s:%d!', $ip, $port));
                        }
                        $this->logger->info(sprintf('Nacos instance %s:%d was created successfully!', $ip, $port));
                        break;
                    case 200:
                        $response = $client->instance->update($ip, $port, $serviceName, $optionalData);
                        if ($response->getStatusCode() !== 200 || (string) $response->getBody() !== 'ok') {
                            throw new RequestException(sprintf('Failed to update nacos instance %s:%d!', $ip, $port));
                        }
                        $this->logger->info(sprintf('Nacos instance %s:%d was updated successfully!', $ip, $port));
                        break;
                }
            }

            $this->refreshConfig();

            if ($event instanceof MainCoroutineServerStart) {
                $interval = (int) $config->get('nacos.config.reload_interval', 3);
                Coroutine::create(function () use ($interval) {
                    sleep($interval);
                    retry(INF, function () use ($interval) {
                        $prevConfig = [];
                        while (true) {
                            $coordinator = CoordinatorManager::until(\Hyperf\Utils\Coordinator\Constants::WORKER_EXIT);
                            $workerExited = $coordinator->yield($interval);
                            if ($workerExited) {
                                break;
                            }
                            $prevConfig = $this->refreshConfig($prevConfig);
                        }
                    }, $interval * 1000);
                });
            }
        } catch (\Throwable $exception) {
            $this->logger->critical((string) $exception);
        }
    }

    protected function refreshConfig(array $prevConfig = []): array
    {
        $client = $this->container->get(Client::class);
        $manager = $this->container->get(ConfigManager::class);

        $result = $client->pull();
        if ($result === $prevConfig) {
            return $result;
        }

        $manager->merge($result);
        return $result;
    }
}
