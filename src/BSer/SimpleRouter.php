<?php
namespace BSer;

use BSer\SimpleRouter\Request;
use BSer\SimpleRouter\Exception\ControllerNotFoundException;
use BSer\SimpleRouter\Exception\OperationNotFoundException;
use BSer\SimpleRouter\BaseController;

/**
 * Роутер, группирующий обработчики endpoint-ов в контроллерах
 * Контроллеры могут быть подключены к роутеру с указанным префиксом (даже несколько контроллеров на один префикс) 
 * При поиске обработчика по переданному запросу сначала по префиксу url выбираются подходящие контроллеры
 * Затем, в процессе обхода подходящих контроллеров у них заполняется массив соответствия endpoint-ов обработчикам
 * После нахождения первого подходящего обработчика поиск прекращается, вызывается метод beforeExec(), который может быть переопределен контроллером, и обработчик вызывается
 * Если обработка прошла без исключений, формируется ответ через обработчик viewExec() (который также может быть переопределен контроллером)
 * При любом исключении, формируется ответ в обработчике errorExec() (который также может быть переопределен контроллером)
 */
class SimpleRouter
{
    /**
     * Список контроллеров ([ 'префикс' => BaseController[] ])
     * @var array
     */
    private $controllers;
    
    /**
     * Функция преобразования ответа, полученного от обработчика endpoint-а (если не указана, возвращается то, что вернул обработчик)
     * @var callable
     */
    private $viewCallable;
    
    /**
     * Функция, вызываемая для формирования ответа при возникновении исключения
     * @var callable
     */
    private $errorCallable;
    
    /**
     * Функция, вызываемая перед непосредственным запуском найденного обработчика
     * @var callable
     */
    private $beforeCallable;
    
    /**
     * Полезно для тестирования, чтобы не возвращать реальный ответ
     * Для тестирования скорости работы самого роутера с обработчиком, но без формирования ответа
     * @var bool
     */
    private $silentMode = false;
    
    public function mount($prefix, BaseController $controller)
    {
        $prefix = '/'. ltrim($prefix, '/');
        $this->controllers[$prefix][] = $controller;
    }
    
    /**
     * Задает функцию, вызываемую перед выполнением обработчика соответствующего маршрута (полезно для журналирования)
     * Функции передается параметр Request
     * @param callable $callable
     */
    public function before(callable $callable)
    {
        $this->beforeCallable = $callable;
    }
    
    /**
     * Задает функцию, вызываемую для возвращения результата запроса
     * Функции передаются параметры: $response, Request
     * Если не задавать функцию, то возвратится результат обработчика как есть
     * @param callable $callable
     */
    public function view(callable $callable)
    {
        $this->viewCallable = $callable;
    }
    
    /**
     * Задает функцию, вызываемую при возникновении исключения
     * Функции передаются параметры: $exception, Request, $httpСode
     * @param callable $callable
     */
    public function error(callable $callable)
    {
        $this->errorCallable = $callable;
    }
    
    /**
     * Полезно для отладки\тестирования, когда нам не надо выводить реальные данные, сгенерированные обработчиком endpoint-а
     */
    public function setSilentMode()
    {
        $this->silentMode = true;
    }
    
    /**
     * Главный метод, определяющий обработчик запроса и выполняющая его
     */
    public function run()
    {
        $request = Request::createFromGlobals();
        
        $controllers = $this->getMatchedControllers($request);
        
        if (!$controllers) {
            $this->errorExec(new ControllerNotFoundException('controller not found'), $request, 404);
        }
        
        $handler = $this->getHandler($controllers, $request);
        
        if (is_callable($handler)) {
            
            try {
                $this->beforeExec($request);
                
                $response = $handler($request);
                
                $this->viewExec($response, $request);
                
            } catch (\Exception $exception) {
                $this->errorExec($exception, $request, 500);
            }
            
        } else {
            $this->errorExec(new OperationNotFoundException('operation not found'), $request, 404);
        }
    }
    
    /**
     * 
     * @param Request $request
     * @return array[][]
     */
    protected function getMatchedControllers(Request $request)
    {
        $matchedControllers = [];
        
        foreach($this->controllers as $prefix => $controllers) {
            if (strpos($request->getPathInfo(), $prefix) == 0) {
                $matchedControllers[$prefix] = $controllers;
            }
        }
        
        return $matchedControllers;
    }
    
    /**
     * Из переданного списка контроллеров находит тот, который содержит обработчик и возвращает этот обработчик
     * @param array $controllers
     * @param Request $request
     * @return NULL|callable
     */
    protected function getHandler($controllers, Request $request)
    {
        $handler = null;
        
        /** @var BaseController $prefixController */
        foreach($controllers as $prefix => $prefixControllers) {
            
            foreach($prefixControllers as $prefixController) {
                $prefixController->setPrefix($prefix);
                $prefixController->initEndpoints();
                
                if ($prefixController->hasEndpointHandler($request)) {
                    $handler = $prefixController->getEndpointHandler($request);
                    $prefixController->setRouterHandlers($this);
                    break 2;
                }
            }
        }
        
        return $handler;
    }
    
    protected function viewExec($response, Request $request)
    {
        if ($this->silentMode) {
            return;
        }
        
        if (is_callable($this->viewCallable)) {
            echo ($this->viewCallable)($response, $request);
        } else {
            echo $response;
        }
    }
    
    protected function errorExec(\Exception $exception, Request $request, $httpCode)
    {
        if ($this->silentMode) {
            return;
        }
        
        http_response_code($httpCode);
        if (is_callable($this->errorCallable)) {
            echo ($this->errorCallable)($exception, $request, $httpCode);
        } else {
            echo $exception->getMessage();
        }
    }
    
    protected function beforeExec(Request $request)
    {
        if (is_callable($this->beforeCallable)) {
            ($this->beforeCallable)($request);
        }
    }
}

