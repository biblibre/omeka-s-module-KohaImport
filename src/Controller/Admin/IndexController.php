<?php

namespace KohaImport\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Stdlib\Message;
use KohaImport\Form\ConfigForm;
use KohaImport\Form\MappingForm;

class IndexController extends AbstractActionController
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function configAction()
    {
        $form = $this->getForm(ConfigForm::class);

        $view = new ViewModel();
        $view->setVariable('form', $form);

        $config = $this->config;
        if (!$config['url'] || !$config['client_id'] || !$config['client_secret']) {
            $this->messenger()->addError('KohaImport module is not correctly configured. Please read the README.md file provided with the module.'); // @translate
        }

        return $view;
    }

    public function mapAction()
    {
        $view = new ViewModel;
        $request = $this->getRequest();

        if (!$request->isPost()) {
            return $this->redirect()->toRoute('admin/koha-import');
        }

        $form = $this->getForm(ConfigForm::class);
        $form->setData($request->getPost()->toArray());
        if (!$form->isValid()) {
            $this->messenger()->addFormErrors($form);
            return $this->redirect()->toRoute('admin/koha-import');
        }

        $data = $form->getData();
        unset($data['configform_csrf']);
        $mappingForm = $this->getForm(MappingForm::class, ['config-data' => $data]);

        $view->setVariable('form', $mappingForm);
        $view->setVariable('resourceType', $data['resource-type']);

        return $view;
    }

    public function importAction()
    {
        $request = $this->getRequest();

        if (!$request->isPost()) {
            return $this->redirect()->toRoute('admin/koha-import');
        }
        $post = $request->getPost()->toArray();
        unset($post['csrf']);
        $args = $post;

        $dispatcher = $this->jobDispatcher();
        $job = $dispatcher->dispatch('KohaImport\Job\ImportJob', $args);

        $message = new Message(
            'Importing in background (%sjob #%d%s)', // @translate
                sprintf(
                    '<a href="%s">',
                    htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
            $job->getId(),
            '</a>'
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toRoute('admin/koha-import/past-imports');
    }

    public function pastImportsAction()
    {
        $view = new ViewModel;
        $page = $this->params()->fromQuery('page', 1);
        $query = $this->params()->fromQuery() + [
            'page' => $page,
            'sort_by' => $this->params()->fromQuery('sort_by', 'id'),
            'sort_order' => $this->params()->fromQuery('sort_order', 'desc'),
        ];
        $response = $this->api()->search('koha_import_import', $query);
        $this->paginator($response->getTotalResults(), $page);
        $view->setVariable('imports', $response->getContent());
        return $view;
    }
}
