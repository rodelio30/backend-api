<?php

namespace App\Controllers;

use App\Services\LocaleService;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseResourceController extends ResourceController
{
    /**
     * @var \App\Models\ClientModel
     */
    protected $clientModel;
    protected $agentModel;

    /**
     * @var \CodeIgniter\Session\Session
     */
    protected $session;

    /**
     * @var LocaleService
     */
    protected $localeService;
    protected $locale;
    
    /**
     * Initializer for the ResourceController.
     * We mimic the model loading from BaseController here
     * because ResourceController does not extend Controller.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Call the parent (ResourceController) initialization first
        parent::initController($request, $response, $logger);

        // Initialize session
        $this->session = \Config\Services::session();
        
        // Initialize the model here
        $this->clientModel = new \App\Models\ClientModel();
        $this->agentModel = new \App\Models\AgentModel();

        // E.g.: $this->session = service('session');
        $this->localeService = new LocaleService();
        $this->locale = $this->localeService->applyLocale($this->request, $this->session);
    }

    /**
     * Send JSON response
     */
    public function jsonResponse($data, $status = 200)
    {
        return $this->response->setJSON($data)->setStatusCode($status);
    }
    protected function generateSessionId()
    {
        return bin2hex(random_bytes(32));
    }
}