<?php


class GetResponse
{
    public static function addContact($email, $name, $ip): object|array
    {
        return tiny::http()->post('https://api.getresponse.com/v3/contacts', [
            'json' => [
                'email' => $email,
                'name' => $name,
                'campaign' => [
                    'campaignId' => @$_SERVER['APP_GET_RESPONSE_LIST_ID'],
                ],
                // 'customFieldValues' => [
                //     [
                //         'customFieldId' => 'SZlo4i', // uid
                //         'value' => [$userId]
                //     ],
                //     [
                //         'CUSTOMFIELDID' => 'SZlLdN', // plan
                //         'value' => ['free']
                //     ]
                // ],
                'ipAddress' => $ip,
                'dayOfCycle' => 0,
            ],
            'headers' => ['X-Auth-Token: api-key ' . @$_SERVER['APP_GET_RESPONSE_API_KEY']]
        ]);
    }
}

tiny::registerHelper('getresponse', function() {
    return new GetResponse();
});

// Usage
// tiny::getresponse()->addContact('test@test.com', 'test', '123.123.123.123');
