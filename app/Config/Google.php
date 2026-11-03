<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Google Workspace login settings, kept in a Config file so the app works WITHOUT a .env
 * (for hosts like InfinityFree where .env gives problems).
 *
 * A .env on your own PC can still override these values (BaseConfig reads  google.clientId = ...  from .env automatically),
 * so local and online can use different values without editing code.
 *
 * Put the real values below on the SERVER copy only. Do not share this file or upload it to a public repository.
 */
class Google extends BaseConfig
{
    public string $clientId = '505102303666-lfsmh77p2dmaj98o2qhhsl2bh85j4i3o.apps.googleusercontent.com';

    public string $clientSecret = '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';

    /** Must be listed in Google Cloud Console > Credentials > Authorized redirect URIs. */
    public string $redirectUri = 'http://localhost:8080/auth/googleCallback';

    /** Only accounts of this email domain can sign in. */
    public string $allowedDomain = 'bounty.com.ph';
}