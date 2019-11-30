# Simple-Router

## Описание работы

Имеет похожий на Silex алгоритм работы

## Пример использования


```php
require_once __DIR__ . '/../vendor/autoload.php';

use BSer\SimpleRouter;
use BSer\SimpleRouter\Request;
use BSer\SimpleRouter\BaseController;

/**
 * Класс, возвращающий ответы через обработчик, определенный в роутере
 */
class ApiController1 extends BaseController
{
    public function setRouterHandlers(SimpleRouter $router)
    {
        $router->before(function($request) {
            // код, выполняемый перед вызовом найденного обработчика
            // здесь можно предотвратить вызов обработчика, кинув любое исключение
            // метод полезен для авторизации запроса
        });
    }

    public function initEndpoints()
    {
        $this->get('/test', function (Request $request) {
            return [
                'path' => 'simple-router/test',
            ];
        });
            
        // пример url запроса: /test2/sup/get/234?var=value
        $this->get('/test2/{supplier}/get/{id}', function (Request $request) {
            return [
                'path'          => 'simple-router/test2',
                'supplier'      => $request->get('supplier'),
                'id'            => $request->get('id'),
                'get param var' => $request->get('var'),
            ];
        });
                
                
        $this->get('/test3', function (Request $request) {
            return [
                'path' => 'simple-router/test3',
            ];
        });
                    
        $this->post('/post', function (Request $request) {
            return [
                'path' => 'simple-router/post',
                'post body' => $request->getContent(),
            ];
        });
    }

}

/**
 * Класс со своим собственным обработчиком вывода ответа
 * Подключается к роутеру с префиксом /v2/
 */
class ApiController2 extends BaseController
{
    public function setRouterHandlers(SimpleRouter $router)
    {
        $router->before(function($request) {
            // ничего не делаем перед вызовом обработчика запроса
            // может быть, например, код для авторизации
        });
        
        // Зададим особый обработчик для формирования ответа - чистый ответ (без преобразования в json)
        $router->view(function($response, Request $request) {
            return $response;
        });
    }
    
    public function initEndpoints()
    {
        $this->get('/test', function (Request $request) {
            return 'simple-router/test';
        });
    }
}

/**
 * Класс, возвращающий ответы через обработчик, определенный в роутере
 * Прикрепим его к роутеру с префиксом, что и у первого ApiController1
 */
class ApiController3 extends BaseController
{
    public function setRouterHandlers(SimpleRouter $router)
    {
		// не переопределяем обработчики ответа, установленные в роутере
    }
    
    public function initEndpoints()
    {
        $this->get('/test4', function (Request $request) {
            return [
                'path' => 'simple-router/test4',
            ];
        });
    }
    
}


$router = new SimpleRouter();

$controller1 = new ApiController1();
$controller2 = new ApiController2();
$controller3 = new ApiController3();

$router->mount('/v1/', $controller1);
$router->mount('/v2/', $controller2);
// на один префикс можно прикрепить несколько контроллеров
$router->mount('/v1/', $controller3);

$router->view(function ($response, Request $request) {
    header('Content-Type: application/json');
    return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
});
    
$router->error(function (\Exception $exception, Request $request, $httpCode) {
    header('Content-Type: application/json');
    return json_encode(["code" => $httpCode, "text" => $exception->getMessage()]);
});

$router->run();
```
## Тестирование

```bash
httperf --server simple-router.raketa.dev --port 80 --uri /v1/test --rate 2000 --num-conn=4000 --num-call=1 --timeout 2
```