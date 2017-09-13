<?php declare(strict_types=1);

namespace Dms\Web\Expressive\Http\Controllers\Package;

use Dms\Core\Auth\IAuthSystem;
use Dms\Core\ICms;
use Dms\Core\Package\IPackage;
use Dms\Web\Expressive\Error\DmsError;
use Dms\Web\Expressive\Http\Controllers\DmsController;
use Dms\Web\Expressive\Renderer\Package\PackageRendererCollection;
use Dms\Web\Expressive\Util\StringHumanizer;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Helper\UrlHelper;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\HtmlResponse;

/**
 * The packages controller.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class PackageController extends DmsController implements ServerMiddlewareInterface
{
    /**
     * @var IPackage
     */
    protected $package;

    /**
     * @var PackageRendererCollection
     */
    protected $packageRenderers;

    protected $template;

    protected $urlHelper;

    /**
     * PackageController constructor.
     *
     * @param ICms                      $cms
     * @param PackageRendererCollection $packageRenderers
     */
    public function __construct(
        ICms $cms,
        IAuthSystem $auth,
        PackageRendererCollection $packageRenderers,
        TemplateRendererInterface $template,
        UrlHelper $urlHelper
    ) {
        parent::__construct($cms, $auth);
        $this->template = $template;
        $this->urlHelper = $urlHelper;
        $this->packageRenderers = $packageRenderers;
    }


    // public function showDashboard(ServerRequestInterface $request)
    // {
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $this->loadPackage($request);

        if (!$this->package->hasDashboard() || !$this->package->loadDashboard()->getAuthorizedWidgets()) {
            $moduleNames = $this->package->getModuleNames();
            $firstModule = reset($moduleNames);

            $to = $this->urlHelper->generate('dms::package.module.dashboard', [
                'package' => $this->package->getName(),
                'module'  => $firstModule,
            ], [
                '__no_template' => 1,
            ]);

            $response = new Response('php://memory', 302);
            return $response->withHeader('Location', $to);
            // return redirect()
            //     ->route('dms::package.module.dashboard', [
            //         'package' => $this->package->getName(),
            //         'module'  => $firstModule,
            //     ] + array_filter($request->only('__content_only', '__no_template')));
        }

        $packageName = $this->package->getName();

        $this->loadSharedViewVariables($request);

        return new HtmlResponse(
            $this->template->render(
                'dms::package.dashboard',
                [
                    'assetGroups'      => ['tables', 'charts', 'forms'],
                    'pageTitle'        => StringHumanizer::title($packageName) . ' :: Dashboard',
                    'breadcrumbs'      => [
                        route('dms::index') => 'Home',
                    ],
                    'finalBreadcrumb'  => StringHumanizer::title($packageName),
                    'packageRenderers' => $this->packageRenderers,
                    'package'          => $this->package,
                ]
            )
        );
    }

    protected function loadPackage(ServerRequestInterface $request)
    {
        $packageName = $request->getAttribute('package');

        if (!$this->cms->hasPackage($packageName)) {
            DmsError::abort($request, 404, 'Unrecognized package name');
        }

        $this->package = $this->cms->loadPackage($packageName);
    }
}
