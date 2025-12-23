<?php

namespace App\Controllers;

// use App\Services\LocaleService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use CodeIgniter\RESTful\ResourceController;

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
abstract class BaseController extends ResourceController
{
    protected $format = 'json';
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
    // protected $helpers = [];
    protected $helpers = ['url', 'form', 'domain', 'i18n'];

    // protected $clientModel;

    protected $chatCWModel;
    protected $userCWModel;
    protected $messageCWModel;
    protected $keywordResponseCWModel;

    protected $chatFileModel;
    protected $userRoleModel;

    // Models came from BO livechat
    protected $chatModel;
    protected $userModel;
    protected $messageModel;
    protected $clientModel;
    protected $agentModel;
    protected $apiKeyModel;

    protected $cannedResponseModel;
    // protected $chatFileModel; // commented because already declared above
    protected $chatAnalyticsModel;
    protected $customerModel;
    protected $keywordResponseModel;
    // protected $userRoleModel; // commented because already declared above
    protected $clientApiConfigModel;
    protected $clientWidgetSettingModel;
    protected $clientPaymentModel;
    protected $addonModel;
    protected $addonTranslationModel;
    protected $clientAddonModel;
    // Models came from BO livechat


    // Models for Widget Language Management
    protected $languageModel;
    protected $systemPhraseModel;
    protected $clientPhraseModel;
    // Models for Widget Language Management

    //Model for Eye Chatcher
    protected $eyeCatcherModel;
    //Model for Eye Chatcher

    /**
     * @var LocaleService
     */
    // protected $localeService;

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

        // $this->session = service('session');

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

        // Initialize models (from BO livechat)
        $this->chatModel = new \App\Models\ChatModel();
        $this->userModel = new \App\Models\UserModel();
        $this->messageModel = new \App\Models\MessageModel();
        $this->clientModel = new \App\Models\ClientModel();
        $this->agentModel = new \App\Models\AgentModel();
        $this->apiKeyModel = new \App\Models\ApiKeyModel();
        // $this->chatFileModel = new \App\Models\ChatFileModel(); // commented because already declared above
        $this->cannedResponseModel = new \App\Models\CannedResponseModel();
        $this->keywordResponseModel = new \App\Models\KeywordResponseModel();
        // $this->userRoleModel = new \App\Models\UserRoleModel(); // commented because already declared above
        $this->clientApiConfigModel = new \App\Models\ClientApiConfigModel();
        $this->clientWidgetSettingModel = new \App\Models\ClientWidgetSettingModel();
        $this->clientPaymentModel = new \App\Models\PaymentModel();
        $this->addonModel = new \App\Models\AddonModel();
        $this->addonTranslationModel = new \App\Models\AddonTranslationModel();
        $this->clientAddonModel = new \App\Models\ClientAddonModel();

        // $this->localeService = new LocaleService();
        // $this->locale = $this->localeService->applyLocale($this->request, $this->session);

        // Initialize models for Widget Language Management
        $this->languageModel      = new \App\Models\ClientWidgetLanguageModel();
        $this->systemPhraseModel = new \App\Models\ClientWidgetPhraseModel();
        $this->clientPhraseModel = new \App\Models\ClientWidgetLanguagePhraseModel();
        // Initialize models for Widget Language Management

        // Initialize modles for Eye Catcher 
        $this->eyeCatcherModel = new \App\Models\ClientWidgetEyeCatcherModel();
        // Initialize modles for Eye Catcher 
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
