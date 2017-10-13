<?php

namespace Dms\Web\Expressive\Renderer\Module;

use Dms\Core\Common\Crud\Action\Object\IObjectAction;
use Dms\Core\Common\Crud\ICrudModule;
use Dms\Core\Common\Crud\IReadModule;
use Dms\Core\Module\ITableView;
use Dms\Web\Expressive\Http\ModuleContext;
use Dms\Web\Expressive\Renderer\Table\TableRenderer;
use Dms\Web\Expressive\Renderer\Widget\WidgetRendererCollection;
use Zend\Expressive\Session\SessionInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

/**
 * The read module renderer.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ReadModuleRenderer extends ModuleRenderer
{
    /**
     * @var TableRenderer
     */
    protected $tableRenderer;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * ReadModuleRenderer constructor.
     *
     * @param WidgetRendererCollection  $widgetRenderers
     * @param TemplateRendererInterface $template
     * @param TableRenderer             $tableRenderer
     * @param SessionInterface             $session
     */
    public function __construct(
        WidgetRendererCollection $widgetRenderers,
        TemplateRendererInterface $template,
        TableRenderer $tableRenderer,
        SessionInterface $session
    ) {
        parent::__construct($widgetRenderers, $template);
        $this->tableRenderer = $tableRenderer;
        $this->session = $session;
    }

    /**
     * Returns whether this renderer can render the supplied module.
     *
     * @param ModuleContext $moduleContext
     *
     * @return bool
     */
    public function accepts(ModuleContext $moduleContext) : bool
    {
        return $moduleContext->getModule() instanceof IReadModule;
    }

    /**
     * Renders the supplied module dashboard as a html string.
     *
     * @param ModuleContext $moduleContext
     *
     * @return string
     */
    protected function renderDashboard(ModuleContext $moduleContext) : string
    {
        /** @var IReadModule $module */
        $module       = $moduleContext->getModule();
        $summaryTable = $module->getSummaryTable();

        /** @var ITableView[] $views */
        $views = $summaryTable->getViews() ?: [$summaryTable->getDefaultView()];

        $createActionName = null;
        if ($module instanceof ICrudModule) {
            /** @var ICrudModule $module */
            if ($module->allowsCreate() && $module->getCreateAction()->isAuthorized()) {
                $createActionName = $module->getCreateAction()->getName();
            }
        }

        $generalActions = [];
        foreach ($module->getActions() as $action) {
            if (!($action instanceof IObjectAction) && $action->isAuthorized() && $action->getName() !== $createActionName) {
                $generalActions[] = $action;
            }
        }

        $activeViewName = $this->session->get('initial-view-name') && $summaryTable->hasView($this->session->get('initial-view-name'))
            ? $this->session->get('initial-view-name')
            : $summaryTable->getDefaultView()->getName();

        return $this->template->render(
            'dms::package.module.dashboard.summary-table',
            [
                'moduleContext'     => $moduleContext,
                'tableRenderer'     => $this->tableRenderer,
                'module'            => $module,
                'summaryTable'      => $summaryTable,
                'summaryTableViews' => $views,
                'activeViewName'    => $activeViewName,
                'generalActions'    => $generalActions,
                'createActionName'  => $createActionName,
            ]
        );
    }
}
