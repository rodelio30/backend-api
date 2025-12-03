<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use App\Models\ClientModel; // Include the model namespace

abstract class BaseResourceController extends ResourceController
{
    /**
     * @var \App\Models\ClientModel
     */
    protected $clientModel;

    protected $chatCWModel;
    protected $userCWModel;
    protected $messageCWModel;
    protected $keywordResponseCWModel;

    protected $chatFileModel;
    protected $userRoleModel;

    /**
     * @var \CodeIgniter\Session\Session
     */
    protected $session;
    
    /**
     * Initializer for the ResourceController.
     * We mimic the model loading from BaseController here
     * because ResourceController does not extend Controller.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Call the parent (ResourceController) initialization first
        parent::initController($request, $response, $logger);
        
        // Initialize the model here
        $this->clientModel = new ClientModel();

        // Initialize models (for Chat Widget Controllers)
        $this->chatCWModel = new \App\Models\ChatCWModel();
        $this->userCWModel = new \App\Models\UserCWModel();
        $this->messageCWModel = new \App\Models\MessageCWModel();
        $this->keywordResponseCWModel = new \App\Models\KeywordResponseCWModel();

        // This 2 file are the file in FN and BO projects
        $this->chatFileModel = new \App\Models\ChatFileModel();
        $this->userRoleModel = new \App\Models\UserRoleModel();

        // NEW: Initialize the Session Service here
        $this->session = service('session');
    }
}