<?php

class GetResponse
{
    public static function addContact($email, $name, $ip): object|array
    {
        return tiny::requests()->post('https://api.getresponse.com/v3/contacts', json_encode([
            'email' => $email,
            'name' => $name,
            'campaign' => [
                'campaignId' => @$_SERVER['GET_RESPONSE_LIST_ID'],
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
        ]), true, ['X-Auth-Token: api-key ' . @$_SERVER['GET_RESPONSE_API_KEY']]);
    }
}
