<?php
declare(strict_types=1);

namespace Example;

use Google\Client;
use RuntimeException;

final class GoogleAuth
{
    public static function client(): Client
    {
        $clientId = \env_value('GOOGLE_CLIENT_ID');
        $secret = \env_value('GOOGLE_CLIENT_SECRET');
        $redirectUri = \env_value('GOOGLE_REDIRECT_URI');
        if ($clientId === '' || $secret === '' || filter_var($redirectUri, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Google OAuth no está configurado.');
        }

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($secret);
        $client->setRedirectUri($redirectUri);
        $client->setScopes(['openid', 'email', 'profile']);
        $client->setAccessType('online');
        return $client;
    }
}
