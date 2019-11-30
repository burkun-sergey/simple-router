<?php
namespace BSer\SimpleRouter;

use BSer\SimpleRouter;

abstract class BaseController
{
    const URL_OPTIONS_PATTERN = '/{([\w]+)}/';
    
    private $handlers = [];
    
    private $prefix = '/';
    
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
    
    /**
     * Метод заполнения массива обработчиков контроллера (через вызовы методов get(), post() и т.д.)
     */
    public abstract function initEndpoints();
    
    /**
     * Поиск соответствующего запросу обработчика
     * @param Request $request
     * @return bool
     */
    public function hasEndpointHandler(Request $request)
    {
        return $this->getEndpointIndex($request) ? true : false;
    }
    
    /**
     * Метод для переопределения при необходимости обработчиков роутера
     * @param SimpleRouter $router
     */
    public abstract function setRouterHandlers(SimpleRouter $router);
    
    protected function get($url, Callable $callable)
    {
        $this->addRoute('GET', $url, $callable);
    }
    
    protected function post($url, Callable $callable)
    {
        $this->addRoute('POST', $url, $callable);
    }
    
    private function addRoute($method, $url, Callable $callable)
    {
        $url = rtrim($this->prefix, '/') . $url;
        
        $this->handlers[$method][$url] = $callable;
    }
    
    /**
     * Возвращает найденный обработчик для запроса и добавляет к запросу подставляемые параметры пути, если обработчик найден
     * @param Request $request
     * @return NULL|callable
     */
    public function getEndpointHandler(Request $request)
    {
        $handler = null;
        $endpoint = $this->getEndpointIndex($request);
        
        if ($endpoint) {
            $handler = $this->handlers[$request->getMethod()][$endpoint];
            $this->addRequestParamsFromRoute($endpoint, $request);
        }
        
        return $handler;
    }
    
    /**
     * Возвращает endpoint из массива (ключ)
     * @param Request $request
     * @return NULL|string
     */
    private function getEndpointIndex(Request $request)
    {
        $key = null;
        $handlers = $this->handlers[$request->getMethod()] ?? [];
        
        foreach($handlers as $endpoint => $handler) {
            
            $pattern = $this->prepareEndpointRegExp($endpoint);
            
            if (preg_match('~^'.$pattern.'$~', $request->getPathInfo())) {
                $key = $endpoint;
                break;
            }
        }
        
        return $key;
    }
    
    /**
     * Добавляет в запрос значения подстановочных слов (placeholders) для дальнейшего возможного их извлечения обработчиком
     * @param string $endpoint
     * @param Request $request
     */
    private function addRequestParamsFromRoute($endpoint, Request $request)
    {
        $urlParamsNames = $this->getUrlParamsNames($endpoint);
        $pattern = $this->prepareEndpointRegExp($endpoint, $request);
        
        $urlParamsValues = [];
        preg_match('~'.$pattern.'~', $request->getPathInfo(), $urlParamsValues);
        array_shift($urlParamsValues);
        
        if (count($urlParamsNames) != count($urlParamsValues)) {
            return;
        }
        
        $params = array_combine($urlParamsNames, $urlParamsValues);
        
        $request->addParams($params);
    }
    
    /**
     * Формирует регулярное выражение, заменяя подстановочные слова (placeholders) в пути (например, {id}) на регулярки
     * @param string $endpoint
     * @param Request $request
     * @return string
     */
    private function prepareEndpointRegExp($endpoint)
    {
        $pattern = $endpoint;
        
        $urlParamsNames = $this->getUrlParamsNames($endpoint);
        
        if ($urlParamsNames) {
            $pattern = preg_replace(self::URL_OPTIONS_PATTERN, '([^\/\s]*)', $endpoint);
        }
        
        return $pattern;
    }
    
    /**
     * Возвращает список подстановочных слов (placeholders) из endpoint
     * @param string $endpoint
     * @return array
     */
    private function getUrlParamsNames($endpoint)
    {
        $urlParamsNames = [];
        preg_match_all(self::URL_OPTIONS_PATTERN, $endpoint, $urlParamsNames);
        $urlParamsNames = $urlParamsNames[1];
        
        return $urlParamsNames;
    }
}

