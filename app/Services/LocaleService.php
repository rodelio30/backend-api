<?php

namespace App\Services;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Session\Session;
use Config\App;

class LocaleService
{
    private const SESSION_KEY = 'app_locale';
    private const USER_PREF_KEY = 'user_preferred_locale';

    /**
     * @var list<string>
     */
    private array $supportedLocales;

    private string $defaultLocale;

    public function __construct(?array $supportedLocales = null, ?string $defaultLocale = null)
    {
        $config = config(App::class);
        $this->supportedLocales = $supportedLocales ?? ($config->supportedLocales ?? [$config->defaultLocale]);
        $this->defaultLocale = $defaultLocale ?? $config->defaultLocale;
    }

    /**
     * Determine and apply locale to the current request/session.
     */
    public function applyLocale(RequestInterface $request, ?Session $session = null): string
    {
        $session ??= service('session');
        $locale = $this->determineLocale($request, $session);

        // Ensure locale is applied to the current request
        if ($request instanceof IncomingRequest) {
            $request->setLocale($locale);
        } else {
            $request->setLocale($locale);
        }

        if ($session) {
            $session->set(self::SESSION_KEY, $locale);
        }

        return $locale;
    }

    /**
     * Determine locale considering request, session, and defaults.
     */
    public function determineLocale(RequestInterface $request, ?Session $session = null): string
    {
        // 1. Explicit request override (?lang=xx)
        $override = $request->getGet('lang');
        if ($override && ($normalized = $this->normalizeLocale($override))) {
            if ($session) {
                $session->set(self::SESSION_KEY, $normalized);
            }

            return $normalized;
        }

        // 2. Session locale
        if ($session && $session->has(self::SESSION_KEY)) {
            $sessionLocale = $session->get(self::SESSION_KEY);
            if (is_string($sessionLocale) && ($normalized = $this->normalizeLocale($sessionLocale))) {
                return $normalized;
            }
        }

        // 3. Stored user preference in session
        if ($session && $session->has(self::USER_PREF_KEY)) {
            $userLocale = $session->get(self::USER_PREF_KEY);
            if (is_string($userLocale) && ($normalized = $this->normalizeLocale($userLocale))) {
                $session->set(self::SESSION_KEY, $normalized);

                return $normalized;
            }
        }

        // 4. Negotiated locale from headers (if supported)
        $negotiated = $request->negotiate('language', $this->supportedLocales);
        if ($negotiated && ($normalized = $this->normalizeLocale($negotiated))) {
            return $normalized;
        }

        return $this->defaultLocale;
    }

    /**
     * Persist locale choice for the active user/session.
     */
    public function rememberLocale(Session $session, string $locale): void
    {
        $session->set(self::SESSION_KEY, $locale);
    }

    /**
     * Persist the authenticated user's preferred locale.
     */
    public function rememberUserPreferredLocale(Session $session, string $locale): void
    {
        $session->set(self::USER_PREF_KEY, $locale);
        $session->set(self::SESSION_KEY, $locale);
    }

    /**
     * Validate and normalize locale codes.
     */
    public function normalizeLocale(?string $locale): ?string
    {
        if (!$locale) {
            return null;
        }

        $normalized = strtolower(str_replace('_', '-', $locale));

        foreach ($this->supportedLocales as $supported) {
            $supportedLower = strtolower($supported);
            if ($normalized === $supportedLower) {
                return $supported;
            }

            if (str_starts_with($normalized, $supportedLower . '-')) {
                return $supported;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }
}

