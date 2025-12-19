<?php

namespace App\Controllers\Clientzone;

use App\Controllers\General;

class WidgetLanguageController extends General
{
    /**
     * Widget loads language + phrases
     * Used by frontend widget
     */
    public function getWidgetConfig()
    {
        // Validate token data exists
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Token data missing (filter did not pass data)'
            ]);
        }

        $payload = (array) $tokenObject->data;

        $clientId = $payload['id'] ?? null;

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid or missing client ID in token.'
            ]);
        }

        $locale = $this->clientModel->getLocaleByClientId($clientId);

        // Fallback if language not active
        if (!$this->languageModel->languageExists($locale)) {
            $locale = $this->languageModel->getDefaultLocale();
        }

        // System default phrases
        $systemPhrases = $this->systemPhraseModel->getByLocale($locale);

        // Client overrides
        $clientPhrases = $this->clientPhraseModel->getClientPhrases($clientId, $locale);

        // Convert to key-value map
        $systemMap = [];
        foreach ($systemPhrases as $row) {
            $systemMap[$row['phrase_key']] = $row['phrase_value'];
        }

        $clientMap = [];
        foreach ($clientPhrases as $row) {
            $clientMap[$row['phrase_key']] = $row['phrase_value'];
        }

        // Client overrides system
        $finalPhrases = array_merge($systemMap, $clientMap);

        return $this->respond([
            'status'  => 1,
            'locale'  => $locale,
            'phrases' => $finalPhrases
        ]);
    }

    /**
     * Save or update client phrases (Admin)
     */
    public function saveLanguagePhrases()
    {
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Token data missing (filter did not pass data)'
            ]);
        }

        $payload = (array) $tokenObject->data;

        $clientId = $payload['id'] ?? null;

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid or missing client ID in token.'
            ]);
        }

        $data = $this->request->getJSON(true);
        $locale   = $data['locale'] ?? '';
        $phrases  = $data['phrases'] ?? [];

        if (!$locale || empty($phrases)) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Invalid Locale or phrases data'
            ], 400);
        }

        foreach ($phrases as $key => $value) {
            $this->clientPhraseModel->upsertPhrase(
                $clientId,
                $locale,
                $key,
                $value
            );
        }

        return $this->respond([
            'status'  => 1,
            'message' => 'Language phrases saved successfully'
        ]);
    }

    /**
     * List available languages
     */
    public function getLanguages()
    {
        return $this->respond([
            'status'    => 1,
            'languages' => $this->languageModel->getActiveLanguages()
        ]);
    }
}
