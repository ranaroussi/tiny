<?php


use myPHPnotes\Microsoft\Auth;
use myPHPnotes\Microsoft\Handlers\Session;
use myPHPnotes\Microsoft\Models\User;

/* Creating an array of providers based env */

class OAuth
{
    private $provider;
    private $config;

    public function __construct($provider, $config)
    {
        $this->provider = $provider;
        $this->config = $config;
    }

    public function getUserProfile()
    {
        if ($this->provider == 'microsoft') {
            return $this->getUserProfileMicrosoft();
        }

        $hybridauth = new Hybridauth\Hybridauth($this->config);
        $adapter = $hybridauth->authenticate($this->provider);
        $profile = (array)$adapter->getUserProfile();

        $name = tiny::parseName($profile['displayName'] . '');
        $profile['firstname'] = @$name['firstname'];
        $profile['lastname'] = @$name['lastname'];
        return $profile;
    }

    private function getUserProfileMicrosoft()
    {
        $config = $this->config['providers']['microsoft']['keys'];

        if (!@$_REQUEST['code'] || !@$_REQUEST['state']) {
            $microsoft = new Auth(
                'common',
                $config['client_id'],
                $config['client_secret_id'],
                $this->config['callback'],
                $config['scopes']
            );
            $url = $microsoft->getAuthUrl();
            tiny::header("Location: $url");
            tiny::die();
        }

        $microsoft = new Auth(
            'common',
            $config['client_id'],
            $config['client_secret_value'],
            $this->config['callback'],
            $config['scopes']
        );
        $tokens = $microsoft->getToken($_REQUEST['code'], Session::get('state'));
        $accessToken = $tokens->access_token;
        $microsoft->setAccessToken($accessToken);
        $user = (new User()); // User get pulled only if refresh token was generated for scope User.Read

        $name = tiny::parseName($user->data->getDisplayName() . '');

        return [
            'provider' => $this->provider,
            'identifier' => str_replace("'", '', $user->data->getId()),
            'email' => mb_strtolower($user->data->getMail()),
            'emailVerified' => mb_strtolower($user->data->getMail()),
            'displayName' => $user->data->getDisplayName(),
            'firstName' => $name['firstname'],
            'lastName' => $name['lastname'],
            'language' => @$user->data->getPreferredLanguage(),
            'description' => null,
            'webSiteURL' => null,
            'photoURL' => null,
            'gender' => null,
            'age' => null,
            'birthDay' => null,
            'birthMonth' => null,
            'birthYear' => null,
            'address' => null,
            'country' => null,
            'region' => null,
            'city' => null,
            'zip' => null,
        ];
    }
}

tiny::registerHelper('oauth', function () {
    return new class {
        public function adapter(string $provider, array $config)
        {
            return new OAuth($provider, $config);
        }

        public function getConfig(?string $item = null)
        {
            if (defined('OAUTH_CONFIG')) {
                if ($item) {
                    return OAUTH_CONFIG[$item];
                }
                return OAUTH_CONFIG;
            }

            $order = [
                'google',
                'github',
                'microsoft',
                'apple',
                'amazon',
                'facebook',
                'twitter',
                'linkedin',
                'dropbox',
                'gitlab',
                'bitbucket',
                'discord',
                'slack',
                'wechat',
                'openid',
            ];

            $branding = [
                'apple' => [
                    'name' => 'Apple',
                    'color' => '#000000',
                    'text' => '#ffffff',
                ],
                'amazon' => [
                    'name' => 'Amazon',
                    'color' => '#EE9400',
                    'text' => '#000000',
                ],
                'bitbucket' => [
                    'name' => 'BitBucket',
                    'color' => '#0052CC',
                    'text' => '#000000',
                ],
                'discord' => [
                    'name' => 'Discord',
                    'color' => '#5667E3',
                    'text' => '#000000',
                ],
                'dropbox' => [
                    'name' => 'Dropbox',
                    'color' => '#0161FE',
                    'text' => '#000000',
                ],
                'facebook' => [
                    'name' => 'Facebook',
                    'color' => '#4661B1',
                    'text' => '#000000',
                ],
                'github' => [
                    'name' => 'GitHub',
                    'color' => '#010101',
                    'text' => '#ffffff',
                ],
                'gitlab' => [
                    'name' => 'GitLab',
                    'color' => '#FC6D27',
                    'text' => '#000000',
                ],
                'google' => [
                    'name' => 'Google',
                    'color' => '#ffffff',
                    'text' => '#000000',
                    // 'color' => '#4285F5',
                    // 'text' => '#ffffff'
                ],
                'linkedin' => [
                    'name' => 'LinkedIn',
                    'color' => '#0A66C3',
                    'text' => '#000000',
                ],
                'openid' => [
                    'name' => 'OpenID',
                    'color' => '#808080',
                    'text' => '#000000',
                ],
                'slack' => [
                    'name' => 'Slack',
                    'color' => '#611F69',
                    'text' => '#000000',
                ],
                'twitter' => [
                    'name' => 'Twitter',
                    'color' => '#1F9BF0',
                    'text' => '#000000',
                ],
                'wechat' => [
                    'name' => 'WeChat',
                    'color' => '#2CBC00',
                    'text' => '#000000',
                ],
                'microsoft' => [
                    'name' => 'Microsoft',
                    'color' => '#ffffff',
                    'text' => '#000000',
                ],
            ];

            $provides = [];
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'OAUTH_') && $value) {
                    $slug = explode('_', str_replace('OAUTH_', '', $key))[0];
                    $provides[mb_strtolower($slug)] = [];
                }
            }

            foreach ($provides as $key => $value) {
                $env_key = 'OAUTH_' . mb_strtoupper($key);
                $provides[$key] = [
                    'enabled' => @$_SERVER[$env_key . '_ENABLED'] ? $_SERVER[$env_key . '_ENABLED'] : true,
                ];

                if ($key == 'apple') {
                    $provides[$key]['keys'] = [
                        'id' => $_SERVER['APP_OAUTH_APPLE_ID'],
                        'team_id' => $_SERVER['APP_OAUTH_APPLE_TEAM_ID'],
                        'key_id' => $_SERVER['APP_OAUTH_APPLE_KEY_ID'],
                        // 'key_content' => $_SERVER['APP_OAUTH_APPLE_KEY_CONTENT'],
                        // 'key_file' => $_SERVER['APP_OAUTH_APPLE_KEY_FILE'],
                    ];
                    if (isset($_SERVER['APP_OAUTH_APPLE_KEY_CONTENT'])) {
                        $provides[$key]['keys']['key_content'] = str_replace('\\n', "\n", $_SERVER['APP_OAUTH_APPLE_KEY_CONTENT']);
                    } elseif (isset($_SERVER['APP_OAUTH_APPLE_KEY_FILE'])) {
                        $provides[$key]['keys']['key_file'] = $_SERVER['APP_OAUTH_APPLE_KEY_FILE'];
                    } else {
                        throw new Exception('Missing apple key content or file');
                    }

                    $provides[$key]['scope'] = 'name email';
                    $provides[$key]['verifyTokenSignature'] = false;
                } elseif ($key == 'microsoft') {
                    $provides[$key]['keys'] = [
                        'tenant' => $_SERVER['APP_OAUTH_MICROSOFT_TENANT'] ?? 'common',
                        'client_id' => $_SERVER['APP_OAUTH_MICROSOFT_ID'],
                        'client_secret_id' => $_SERVER['APP_OAUTH_MICROSOFT_SECRET_ID'],
                        'client_secret_value' => $_SERVER['APP_OAUTH_MICROSOFT_SECRET_VALUE'],
                        'scopes' => ['user.read'],
                    ];
                } else {
                    $provides[$key]['keys'] = [
                        'id' => @$_SERVER[$env_key . '_ID'],
                        'secret' => @$_SERVER[$env_key . '_SECRET'],
                    ];
                }
                $provides[$key]['settings'] = array_merge($branding[$key], [
                    'icon' => tiny::getStaticURL('/oauth/' . $key . '.svg', true),
                    'link' => tiny::getHomeURL('auth/oauth/' . $key, true, $_SERVER['APP_ENV'] == 'prod' ? 'https' : 'http'),
                ]);
            }

            $provides = array_replace(array_flip($order), $provides);
            foreach ($provides as $key => $value) {
                if (!is_array($value)) {
                    unset($provides[$key]);
                }
            }

            define('OAUTH_CONFIG', [
                'callback' => tiny::getHomeURL('auth/oauth', true, $_SERVER['APP_ENV'] == 'prod' || @$_SERVER['APP_HTTPS'] == 'on' ? 'https' : 'http'),
                'providers' => $provides,
            ]);

            // tiny::debug(OAUTH_CONFIG);
            if ($item) {
                return OAUTH_CONFIG[$item];
            }
            return OAUTH_CONFIG;
        }
    };
});
