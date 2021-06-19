# Nacos

一个 `Nacos` 的 `PHP` 协程客户端，与 `Hyperf` 的配置中心、微服务治理完美结合。

## 安装

```shell
composer require hyperf/nacos2.2-incubator
```

### 发布配置文件

```shell
php bin/hyperf.php vendor:publish hyperf/nacos
```

## 服务与实例

`MainWorkerStartListener.php` 将在系统启动完成时自动完成 `实例注册`，`服务注册`

如果需要在服务下线时自动注销服务，请增加如下配置，以监听 `Shutdown` 事件

```php
// config/autoload/server.php

return [
    // ...other
    'callbacks' => [
        // ...other
        Event::ON_SHUTDOWN => [Hyperf\Framework\Bootstrap\ShutdownCallback::class, 'onShutdown']
    ]
];
```

### 获取一个服务的最优节点

```php
use Hyperf\Utils\ApplicationContext;
use Hyperf\Nacos\Api\NacosInstance;
use Hyperf\Nacos\Model\ServiceModel;

$container = ApplicationContext::getContainer();
$instance = $container->get(NacosInstance::class);

$service = new ServiceModel([
    'service_name' => 'hyperf',
    'group_name' => 'api',
    'namespace_id' => '5ce9d1c1-6732-4ccc-ae1f-5139af86a845'
]);

$optimal = $instance->getOptimal($service);

```

## 配置中心

`MainWorkerStartListener.php` 系统启动时将拉取远程配置, 并合入`hyperf` 的 `Config`

`FetchConfigProcess.php` 自定义进程将监听配置, 若有更新将发送 `PipeMessage` 到各服务`worker` 进程, 并合入当前进程的 `Config`

如果服务如下配置

```php
// config/autoload/nacos.php

return [
    'host' => '127.0.0.1',
    'port' => 8848,
    'username' => null,
    'password' => null,
    'config' => [
        // 是否开启配置中心
        'enable' => true,
        // 合并模式
        'merge_mode' => Constants::CONFIG_MERGE_OVERWRITE,
        // 配置读取间隔
        'reload_interval' => 3,
        // 默认的配置 KEY 值
        'default_key' => 'nacos_config',
        'listener_config' => [
            // $key => $config
            // 不设置 key 时，则使用 default_key 配置的 key
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
        ],
    ],
];
```

系统将自动监听`listener_config` 中的配置，并将其合并入`hyperf Config` 对象的指定(`config_append_node`) 节点，可以用`config('nacos_config.***')`
获取，若没有配置 `config_append_node` 项，将会并入 `Config` 对象根节点。

> 所有配置的 `键(key)` 在实际发起 API 请求时会自动从下划线风格转换为驼峰风格。
