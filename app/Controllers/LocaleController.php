<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class LocaleController extends BaseController
{
    public function switch(): RedirectResponse
    {
        $requestedLocale = $this->request->getPost('locale');
        $redirectTo = $this->request->getPost('redirect_to') ?: $this->request->getServer('HTTP_REFERER');
        $redirectTo = $redirectTo ?: base_url();

        $normalizedLocale = $this->localeService->normalizeLocale($requestedLocale);

        if (!$normalizedLocale) {
            return redirect()->to($redirectTo)->with('error', trans('General.language_switch_invalid', 'Unsupported language selection.'));
        }

        // Persist user preference if authenticated
        $userType = $this->session->get('user_type');
        if ($userType === 'client' && $this->session->has('client_user_id')) {
            $this->localeService->rememberUserPreferredLocale($this->session, $normalizedLocale);
            $clientId = (int) $this->session->get('client_user_id');
            $this->clientModel->update($clientId, ['preferred_locale' => $normalizedLocale]);
        } elseif ($userType === 'agent' && $this->session->has('agent_user_id')) {
            $this->localeService->rememberUserPreferredLocale($this->session, $normalizedLocale);
            $agentId = (int) $this->session->get('agent_user_id');
            $this->agentModel->update($agentId, ['preferred_locale' => $normalizedLocale]);
        } else {
            $this->localeService->rememberLocale($this->session, $normalizedLocale);
        }

        $message = trans('General.language_switch_success', 'Language updated.', [], $normalizedLocale);

        return redirect()->to($redirectTo)->with('success', $message);
    }
}

