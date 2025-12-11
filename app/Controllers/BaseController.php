<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use App\Models\ClientModel; // Include the model namespace

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    // protected $clientModel;

    protected $chatCWModel;
    protected $userCWModel;
    protected $messageCWModel;
    protected $keywordResponseCWModel;

    protected $chatFileModel;
    protected $userRoleModel;


    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        $this->session = service('session');

        // Preload any models, libraries, etc, here.
        // $this->clientModel = new \App\Models\ClientModel();
        
        // Initialize models (for Chat Widget Controllers)
        $this->chatCWModel = new \App\Models\ChatCWModel();
        $this->userCWModel = new \App\Models\UserCWModel();
        $this->messageCWModel = new \App\Models\MessageCWModel();
        $this->keywordResponseCWModel = new \App\Models\KeywordResponseCWModel();

        // This 2 file are the file in FN and BO projects
        $this->chatFileModel = new \App\Models\ChatFileModel();
        $this->userRoleModel = new \App\Models\UserRoleModel();
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
