<?php

namespace Arbory\Base\Admin\Traits;

use Arbory\Base\Admin\Form;
use Arbory\Base\Admin\Grid;
use Arbory\Base\Admin\Grid\ExportBuilder;
use Arbory\Base\Admin\Layout;
use Arbory\Base\Admin\Layout\LayoutInterface;
use Arbory\Base\Admin\Module;
use Arbory\Base\Admin\Page;
use Arbory\Base\Admin\Tools\ToolboxMenu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Arbory\Base\Admin\Exports\DataSetExport;
use Arbory\Base\Admin\Exports\ExportInterface;
use Arbory\Base\Admin\Exports\Type\ExcelExport;
use Arbory\Base\Admin\Exports\Type\JsonExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait Crudify
{
    /**
     * @var array
     */
    protected static $exportTypes = [
        'xls' => ExcelExport::class,
        'json' => JsonExport::class,
    ];

    /**
     * @var Module
     */
    protected $module;

    /**
     * @return Model|Builder
     */
    public function resource()
    {
        $class = $this->resource;

        return new $class;
    }

    /**
     * @return Module
     */
    protected function module()
    {
        if ($this->module === null) {
            $this->module = \Admin::modules()->findModuleByControllerClass(get_class($this));
        }

        return $this->module;
    }

    /**
     * @param Form                 $form
     * @param LayoutInterface|null $layout
     *
     * @return Form
     */
    protected function form(Form $form, ?LayoutInterface $layout = null)
    {
        return $form;
    }

    /**
     * @param Model                       $model
     * @param LayoutInterface|null $layout
     *
     * @return Form
     */
    protected function buildForm(Model $model, ?LayoutInterface $layout = null)
    {
        $form = new Form($model);
        $form->setModule($this->module());
        $form->setRenderer(new Form\Builder($form));

        if($layout) {
            $layout->setForm($form);
        }

        return $this->form($form, $layout) ?: $form;
    }

    /**
     * @param Grid $grid
     * @return Grid
     */
    public function grid(Grid $grid)
    {
        return $grid;
    }

    /**
     * @param Model $model
     * @return Grid
     */
    protected function buildGrid(Model $model)
    {
        $grid = new Grid($model);
        $grid->setModule($this->module());
        $grid->setRenderer(new Grid\Builder($grid));

        return $this->grid($grid) ?: $grid;
    }

    /**
     * @return Layout
     */
    public function index()
    {
        $layout = $this->layout('grid');

        $layout->setGrid($this->buildGrid($this->resource()));

        $page = new Page();

        $page->setBreadcrumbs($this->module()->breadcrumbs());
        $page->use($layout);

        $page->bodyClass('controller-' . str_slug($this->module()->name()) . ' view-index');

        return $page;
    }

    /**
     * @param $resourceId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show($resourceId)
    {
        return redirect($this->module()->url('edit', $resourceId));
    }

    /**
     * @return Layout
     */
    public function create()
    {
        $layout = $this->layout('form');
        $layout->setForm($this->buildForm($this->resource(), $layout));

        $page = new Page();
        $page->use($layout);

        $page->bodyClass('controller-' . str_slug($this->module()->name()) . ' view-edit');

        return $page;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        $form = $this->buildForm($this->resource());

        $request->request->add(['fields' => $form->fields()]);

        $form->validate();

        if ($request->ajax()) {
            return response()->json(['ok']);
        }

        $form->store($request);

        return $this->getAfterEditResponse($request);
    }

    /**
     * @param                      $resourceId
     * @param Layout\LayoutManager $manager
     *
     * @return Layout
     */
    public function edit($resourceId, Layout\LayoutManager $manager)
    {
        $resource = $this->findOrNew($resourceId);
        $layout = $this->layout('form');
        $page = $manager->make(Page::class, 'page');

        $layout->setForm($this->buildForm($resource, $layout));

        $page->use($layout);

        $page->bodyClass('controller-' . str_slug($this->module()->name()) . ' view-edit');
        
        return $page;
    }

    /**
     * @param Request $request
     * @param $resourceId
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request, $resourceId)
    {
        $resource = $this->findOrNew($resourceId);
        $layout   = $this->layout('form');
        $form     = $this->buildForm($resource, $layout);

        $layout->setForm($form);


        $request->request->add(['fields' => $form->fields()]);

        $form->validate();

        if ($request->ajax()) {
            return response()->json(['ok']);
        }

        $form->update($request);

        return $this->getAfterEditResponse($request);
    }

    /**
     * @param $resourceId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($resourceId)
    {
        $resource = $this->resource()->findOrFail($resourceId);

        $this->buildForm($resource)->destroy();

        return redirect($this->module()->url('index'));
    }

    /**
     * @param Request $request
     * @param string $name
     * @return mixed
     */
    public function dialog(Request $request, string $name)
    {
        $method = camel_case($name) . 'Dialog';

        if (!$name || !method_exists($this, $method)) {
            app()->abort(Response::HTTP_NOT_FOUND);

            return null;
        }

        return $this->{$method}($request);
    }


    /**
     * @param string $type
     * @return BinaryFileResponse
     * @throws \Exception
     */
    public function export(string $type): BinaryFileResponse
    {
        $grid = $this->buildGrid($this->resource());
        $grid->setRenderer(new ExportBuilder($grid));
        $grid->paginate(false);

        /** @var DataSetExport $dataSet */
        $dataSet = $grid->render();

        $exporter = $this->getExporter($type, $dataSet);

        return $exporter->download($this->module()->name());
    }

    /**
     * @param string $type
     * @param DataSetExport $dataSet
     * @return ExportInterface
     * @throws \Exception
     */
    protected function getExporter(string $type, DataSetExport $dataSet): ExportInterface
    {
        if (! isset(self::$exportTypes[$type])) {
            throw new \Exception('Export Type not found - ' . $type);
        }

        return new self::$exportTypes[$type]($dataSet);
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function toolboxDialog(Request $request): string
    {
        $node = $this->findOrNew($request->get('id'));

        $toolbox = new ToolboxMenu($node);

        $this->toolbox($toolbox);

        return $toolbox->render();
    }

    /**
     * @param \Arbory\Base\Admin\Tools\ToolboxMenu $tools
     */
    protected function toolbox(ToolboxMenu $tools)
    {
        $model = $tools->model();

        $tools->add('edit', $this->url('edit', $model->getKey()));
        $tools->add('delete',
            $this->url('dialog', ['dialog' => 'confirm_delete', 'id' => $model->getKey()])
        )->dialog()->danger();
    }

    /**
     * @param Request $request
     * @return \Illuminate\View\View
     */
    protected function confirmDeleteDialog(Request $request)
    {
        $resourceId = $request->get('id');
        $model = $this->resource()->find($resourceId);

        return view('arbory::dialogs.confirm_delete', [
            'form_target' => $this->url('destroy', [$resourceId]),
            'list_url' => $this->url('index'),
            'object_name' => (string)$model,
        ]);
    }

    /**
     * @param Request $request
     * @param string $name
     * @return null
     */
    public function api(Request $request, string $name)
    {
        $method = camel_case($name) . 'Api';

        if (!$name || !method_exists($this, $method)) {
            app()->abort(Response::HTTP_NOT_FOUND);

            return null;
        }

        return $this->{$method}($request);
    }

    /**
     * @param string $route
     * @param array $parameters
     * @return string
     */
    public function url(string $route, $parameters = [])
    {
        return $this->module()->url($route, $parameters);
    }

    /**
     * @param mixed $resourceId
     * @return Model
     */
    protected function findOrNew($resourceId): Model
    {
        /**
         * @var Model $resource
         */
        $resource = $this->resource();

        if (method_exists($resource, 'bootSoftDeletes')) {
            $resource = $resource->withTrashed();
        }

        $resource = $resource->findOrNew($resourceId);
        $resource->setAttribute($resource->getKeyName(), $resourceId);

        return $resource;
    }

    /**
     * @param Request $request
     * @return array|Request|string
     */
    public function slugGeneratorApi(Request $request)
    {
        /** @var \Illuminate\Database\Query\Builder $query */
        $slug = str_slug($request->input('from'));
        $column = $request->input('column_name');

        $query = \DB::table($request->input('model_table'))->where($column, $slug);

        if ($locale = $request->input('locale')) {
            $query->where('locale', $locale);
        }

        if ($objectId = $request->input('object_id')) {
            $query->where('id', '<>', $objectId);
        }

        if ($column && $query->exists()) {
            $slug .= '-' . random_int(0, 9999);
        }

        return $slug;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    protected function getAfterEditResponse(Request $request)
    {
        return redirect($request->has('save_and_return') ? $this->module()->url('index') : $request->url());
    }

    /**
     * @param $component
     *
     * @return LayoutInterface
     */
    protected function layout($component)
    {
        $layouts =  [
            'grid' => Grid\Layout::class,
            'form' => Form\Layout::class
        ];

        if(property_exists($this, 'layouts')) {
            $layouts = $this->layouts;
        }

        $class = $layouts[$component] ?? null;

        if(!class_exists($class)) {
            throw new \RuntimeException("Layout class '{$class}' for '{$component}' does not exist");
        }

        return app()->make($class);
    }
}
