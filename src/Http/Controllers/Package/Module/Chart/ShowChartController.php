<?php declare(strict_types=1);

namespace Dms\Web\Expressive\Http\Controllers\Package\Module\Chart;

use Dms\Core\Exception\InvalidArgumentException;
use Dms\Core\ICms;
use Dms\Core\Auth\IAuthSystem;
use Dms\Core\Model\Criteria\Condition\ConditionOperator;
use Dms\Core\Model\Criteria\OrderingDirection;
use Dms\Core\Module\IChartDisplay;
use Dms\Core\Module\IChartView;
use Dms\Core\Module\IModule;
use Dms\Core\Table\Chart\Criteria\ChartCriteria;
use Dms\Core\Table\Chart\IChartStructure;
use Dms\Web\Expressive\Error\DmsError;
use Dms\Web\Expressive\Http\Controllers\DmsController;
use Dms\Web\Expressive\Http\ModuleContext;
use Dms\Web\Expressive\Renderer\Chart\ChartControlRenderer;
use Dms\Web\Expressive\Util\StringHumanizer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\JsonResponse;

/**
 * The chart controller
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ShowChartController extends DmsController implements ServerMiddlewareInterface
{
    /**
     * @var ChartControlRenderer
     */
    protected $chartRenderer;

    public function __construct(
        ICms $cms,
        IAuthSystem $auth,
        TemplateRendererInterface $template,
        RouterInterface $router,
        ChartControlRenderer $chartRenderer
     ) {
        parent::__construct($cms, $auth, $template, $router);
        $this->chartRenderer = $chartRenderer;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $chartName = $request->getAttribute('chart');
        $viewName = $request->getAttribute('view');
        $packageName = $request->getAttribute('package');
        $moduleName = $request->getAttribute('module');
        $package = $this->cms->loadPackage($packageName);
        $moduleContext = ModuleContext::rootContext($this->router, $packageName, $moduleName, function () use ($package, $moduleName) {
            return $package->loadModule($moduleName);
        });
        $module = $moduleContext->getModule();
        $chart  = $this->loadChart($module, $chartName);

        $this->loadChartView($chart, $viewName);

        $this->loadSharedViewVariables($request);

        return new HtmlResponse(
            $this->template->render(
                'dms::package.module.chart',
                [
                    'assetGroups'     => ['charts'],
                    'pageTitle'       => implode(' :: ', array_merge($moduleContext->getTitles(), [StringHumanizer::title($chartName)])),
                    'pageSubTitle'    => $viewName,
                    'breadcrumbs'     => $moduleContext->getBreadcrumbs(),
                    'finalBreadcrumb' => StringHumanizer::title($chartName),
                    'chartContent'    => $this->chartRenderer->renderChartControl($moduleContext, $chart, $viewName),
                ]
            )
        );
    }

    protected function filterCriteriaFromRequest(ServerRequestInterface $request, IChartStructure $structure, ChartCriteria $criteria)
    {
        $axisNames = [];

        foreach ($structure->getAxes() as $axis) {
            $axisNames[] = $axis->getName();
        }

        // $this->validate($request, [
        //     'conditions.*.axis'     => 'required|in:' . implode(',', $axisNames),
        //     'conditions.*.operator' => 'required|in:' . implode(',', ConditionOperator::getAll()),
        //     'conditions.*.value'    => 'required',
        //     'orderings.*.component' => 'required|in:' . implode(',', $axisNames),
        //     'orderings.*.direction' => 'required|in' . implode(',', OrderingDirection::getAll()),
        // ]);

        if ($request->has('conditions')) {
            foreach ($request->input('conditions') as $condition) {
                $criteria->where($condition['axis'], $condition['operator'], $condition['value']);
            }
        }

        if ($request->has('orderings')) {
            foreach ($request->input('orderings') as $ordering) {
                $criteria->orderBy($ordering['component'], $ordering['direction']);
            }
        }
    }

    /**
     * @param IChartDisplay $chart
     * @param string        $chartView
     *
     * @return IChartView
     */
    protected function loadChartView(IChartDisplay $chart, string $chartView) : IChartView
    {
        try {
            return $chart->hasView($chartView) ? $chart->getView($chartView) : $chart->getDefaultView();
        } catch (InvalidArgumentException $e) {
            return DmsError::abort($request, 404);
        }
    }

    /**
     * @param IModule $module
     * @param string  $chartName
     *
     * @return IChartDisplay
     */
    protected function loadChart(IModule $module, string $chartName) : IChartDisplay
    {
        try {
            $action = $module->getChart($chartName);

            return $action;
        } catch (InvalidArgumentException $e) {
            $response = new JsonResponse([
                'message' => 'Invalid chart name',
            ], 404);
        }

        return $response;
    }
}
