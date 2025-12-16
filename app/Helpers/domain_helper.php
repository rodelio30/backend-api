<?php

/**
 * Domain Helper Functions
 * 
 * Helper functions for handling domain-based routing and URL generation
 */

if (!function_exists('getCurrentDomain')) {
    /**
     * Get the current domain
     * 
     * @return string
     */
    function getCurrentDomain(): string
    {
        return $_SERVER['HTTP_HOST'] ?? '';
    }
}

if (!function_exists('isAdminDomain')) {
    /**
     * Check if current domain is the admin domain
     * 
     * @return bool
     */
    function isAdminDomain(): bool
    {
        return getCurrentDomain() === 'kiosk-admin.kopisugar.cc';
    }
}

if (!function_exists('isClientDomain')) {
    /**
     * Check if current domain is the client domain
     * 
     * @return bool
     */
    function isClientDomain(): bool
    {
        return getCurrentDomain() === 'kiosk-chat.kopisugar.cc';
    }
}

if (!function_exists('getAdminDomain')) {
    /**
     * Get the admin domain URL
     * 
     * @return string
     */
    function getAdminDomain(): string
    {
        return 'https://clientzone.taapin.com';
    }
}

if (!function_exists('getClientDomain')) {
    /**
     * Get the client domain URL
     * 
     * @return string
     */
    function getClientDomain(): string
    {
        return 'https://clientzone.taapin.com';
    }
}

if (!function_exists('getDomainSpecificUrl')) {
    /**
     * Generate domain-specific URL
     * 
     * @param string $path - The path to append
     * @param string $domain_type - 'admin' or 'client'
     * @return string
     */
    function getDomainSpecificUrl(string $path, string $domain_type = ''): string
    {
        // Remove leading slash if present
        $path = ltrim($path, '/');
        
        // Auto-detect domain type if not specified
        if (empty($domain_type)) {
            $domain_type = isAdminDomain() ? 'admin' : 'client';
        }
        
        $baseUrl = ($domain_type === 'admin') ? getAdminDomain() : getClientDomain();
        
        return $baseUrl . '/' . $path;
    }
}

if (!function_exists('redirectToDomain')) {
    /**
     * Redirect to specific domain with logout
     * 
     * @param string $domain_type - 'admin' or 'client'
     * @param string $path - Optional path to redirect to
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    function redirectToDomain(string $domain_type, string $path = ''): \CodeIgniter\HTTP\RedirectResponse
    {
        // Destroy current session
        session()->destroy();
        
        // Set flash message
        session()->setFlashdata('error', 'Please login using the correct domain for your account type.');
        
        // Generate URL
        $url = getDomainSpecificUrl($path ?: 'login', $domain_type);
        
        return redirect()->to($url);
    }
}

if (!function_exists('validateDomainAccess')) {
    /**
     * Validate if current domain is appropriate for user type
     * 
     * @param string $required_domain_type - 'admin' or 'client'
     * @return bool
     */
    function validateDomainAccess(string $required_domain_type): bool
    {
        if ($required_domain_type === 'admin') {
            return isAdminDomain();
        } elseif ($required_domain_type === 'client') {
            return isClientDomain();
        }
        
        return false;
    }
}
