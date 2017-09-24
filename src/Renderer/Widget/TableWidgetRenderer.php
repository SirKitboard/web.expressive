<?php declare(strict_types=1);

namespace Dms\Web\Expressive\Renderer\Widget;

use Dms\Core\Widget\IWidget;
use Dms\Core\Widget\TableWidget;
use Dms\Web\Expressive\Http\ModuleContext;
use Dms\Web\Expressive\Renderer\Table\TableRenderer;

/**
 * The widget renderer for data tables
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class TableWidgetRenderer extends WidgetRenderer
{
    /**
     * @var TableRenderer
     */
    protected $tableRenderer;

    /**
     * TableWidgetRenderer constructor.
     *
     * @param TableRenderer $tableRenderer
     */
    public function __construct(TableRenderer $tableRenderer)
    {
        $this->tableRenderer = $tableRenderer;
    }

    /**
     * Returns whether this renderer can render the supplied widget.
     *
     * @param ModuleContext $moduleContext
     * @param IWidget       $widget
     *
     * @return bool
     */
    public function accepts(ModuleContext $moduleContext, IWidget $widget) : bool
    {
        return $widget instanceof TableWidget;
    }

    /**
     * Gets an array of links for the supplied widget.
     *
     * @param ModuleContext $moduleContext
     * @param IWidget $widget
     *
     * @return array
     */
    protected function getWidgetLinks(ModuleContext $moduleContext, IWidget $widget) : array
    {
        /** @var TableWidget $widget */
        $tableDisplay = $widget->getTableDisplay();

        $links = [];

        $module = $moduleContext->getModule();

        foreach ($tableDisplay->getViews() as $tableView) {
            $viewParams = ['package' => $module->getPackageName(), 'module' => $module->getName(), 'table' => $tableDisplay->getName(), 'view' => $tableView->getName()];

            $links[$moduleContext->getUrl('table.view.show', $viewParams)] = $tableView->getLabel();
        }

        if (!$links) {
            $links[$moduleContext->getUrl('table.view.show', ['package' => $module->getPackageName(), 'module' => $module->getName(), 'table' => $tableDisplay->getName(), 'view' => 'all'])] = 'All';
        }

        return $links;
    }

    /**
     * Renders the supplied widget input as a html string.
     *
     * @param ModuleContext $moduleContext
     * @param IWidget $widget
     *
     * @return string
     */
    protected function renderWidget(ModuleContext $moduleContext, IWidget $widget) : string
    {
        /** @var TableWidget $widget */
        $tableDisplay = $widget->getTableDisplay();

        return view('dms::components.widget.data-table')
            ->with([
                'dataTableContent' => $this->tableRenderer->renderTableData($moduleContext, $tableDisplay, $widget->loadData()),
            ])
            ->render();
    }
}
