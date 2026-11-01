<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Google extends BaseConfig
{
    public string $clientId = '';

    public string $clientSecret = '';

    /** Must be listed in Google Cloud Console > Credentials > Authorized redirect URIs. */
    public string $redirectUri = '';

    /** Only accounts of this email domain can sign in. */
    public string $allowedDomain = 'bounty.com.ph';

    public function __construct()
    {
        parent::__construct();
        // Dynamic base_url() para siguradong malinis at WALANG index.php
        $this->redirectUri = base_url('login/google/callback');
    }
}