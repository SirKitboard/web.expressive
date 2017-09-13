<?php

namespace Dms\Web\Expressive\Http;

use Dms\Core\Exception\InvalidOperationException;
use Dms\Core\ICms;
use Dms\Core\Module\IModule;
use Dms\Web\Expressive\Error\DmsError;
use Dms\Web\Expressive\Http\Controllers\Package\Module\ModuleController;
use Dms\Web\Expressive\Http\Controllers\Package\Module\Action\FieldRendererController;
use Dms\Web\Expressive\Http\Controllers\Package\Module\Action\FormRendererController;
use Dms\Web\Expressive\Http\Controllers\Package\Module\Action\FormStageController;
use Dms\Web\Expressive\Http\Controllers\Package\Module\Action\RunController;
use Dms\Web\Expressive\Http\Controllers\Package\Module\Action\ShowResultController;
use Dms\Web\Expressive\Http\Controllers\Package\Module\Action\ShowFormController;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\RouteGroup;
// use Illuminate\Routing\Router;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * The module request router
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ModuleRequestRouter
{
    /**
     * @var $moduleContext []
     */
    protected $currentModuleContextStack = [];

    /**
     * @var Router
     */
    protected $router;

    public function __construct()
    {
        $this->router = app(RouterInterface::class);
    }

    /**
     * @return ModuleContext
     */
    public static function currentModuleContext() : ModuleContext
    {
        return app(__CLASS__)->getCurrentModuleContext();
    }

    /**
     * @return RouterInterface
     */
    public function getRouter() : RouterInterface
    {
        return $this->router;
    }

    /**
     * @return ModuleContext
     * @throws InvalidOperationException
     */
    public function getCurrentModuleContext() : ModuleContext
    {
        if (empty($this->currentModuleContextStack)) {
            throw InvalidOperationException::format('Not in a valid module context');
        }

        return end($this->currentModuleContextStack);
    }

    /**
     * @param ModuleContext $moduleContext
     * @param Request       $request
     *
     * @return Response
     */
    public function dispatch(ModuleContext $moduleContext, ServerRequestInterface $request) : Response
    {
        $this->currentModuleContextStack[] = $moduleContext;

        $originalMiddlewareFlag = app()->bound('middleware.disable') ? app()->make('middleware.disable') : false;
        $originalRequest        = app()->bound('request') ? app()->make('request') : null;
        $originalModuleContext  = app()->bound(ModuleContext::class) ? app()->make(ModuleContext::class) : null;

        app()->instance('middleware.disable', true);
        app()->instance('request', $request);
        app()->instance(ModuleContext::class, $moduleContext);

        $response = $this->router->dispatch($request);

        app()->instance('middleware.disable', $originalMiddlewareFlag);
        app()->instance('request', $originalRequest);

        if ($originalModuleContext) {
            \app()->instance(ModuleContext::class, $originalModuleContext);
        } else {
            \app()->offsetUnset(ModuleContext::class);
        }

        array_pop($this->currentModuleContextStack);

        return $response;
    }

    public function getRootContextFromModule(IModule $module) : ModuleContext
    {
        return $this->getRootContext($module->getPackageName(), $module->getName(), function () use ($module) {
            return $module;
        });
    }

    public function getRootContext(string $packageName, string $moduleName, callable $moduleLoaderCallback) : ModuleContext
    {
        $moduleContext                   = ModuleContext::rootContext($this->router, $packageName, $moduleName, $moduleLoaderCallback);
        $this->currentModuleContextStack = [$moduleContext];

        return $moduleContext;
    }

    /**
     * @param RouterInterface $router
     *
     * @return void
     */
    public function registerOnMainRouter(RouterInterface $router)
    {
        // $router->group(['prefix' => '/package/{package}/{module}', 'as' => 'package.module.'], function () use ($router) {
        //     $groupStack   = $router->getGroupStack();
        //     $currentGroup = end($groupStack);
        //
        //     foreach ($this->router->getRoutes()->getRoutes() as $route) {
        //         /** @var Route $route */
        //         $newRoute = clone $route;
        //         if ($newRoute->uri() === '/') {
        //             $newRoute->setUri($currentGroup['prefix']);
        //         } else {
        //             $newRoute->setUri($currentGroup['prefix'] . '/' . rtrim($newRoute->uri(), '/'));
        //         }
        //         $newRoute->setAction(RouteGroup::merge($newRoute->getAction(), $currentGroup));
        //         $router->getRoutes()->add($newRoute);
        //     }
        // });
        //
        // $router->bind('module', function ($value, Route $route) {
        //     /** @var ICms $cms */
        //     $cms = app(ICms::class);
        //
        //     $packageName = $route->parameter('package');
        //     $moduleName  = $route->parameter('module');
        //     $route->forgetParameter('package');
        //
        //     if (!$cms->hasPackage($packageName)) {
        //         DmsError::abort($request, 404);
        //     }
        //
        //     $package = $cms->loadPackage($packageName);
        //
        //     if (!$package->hasModule($moduleName)) {
        //         DmsError::abort($request, 404);
        //     }
        //
        //     return $this->getRootContext($packageName, $moduleName, function () use ($package, $moduleName) {
        //         return $package->loadModule($moduleName);
        //     });
        // });
    }
}
