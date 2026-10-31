<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth\Enum;

/**
 * OAuth token endpoint client authentication methods.
 */
enum TokenEndpointAuthMethod: string
{
    case None = 'none';
    case ClientSecretBasic = 'client_secret_basic';
    case ClientSecretPost = 'client_secret_post';
}
