<?php

declare(strict_types=1);

const SORTCODES = [
    '300083' => 'AL RAYAN BANK',
    '400328' => 'HSBC BANK',
    '404218' => 'HSBC UK BANK',
    '309542' => 'LLOYDS BANK',
    '560005' => 'NATIONAL WESTMINSTER BANK',
    '089249' => 'THE CO-OPERATIVE BANK',
    '301393' => 'ARBUTHNOT LATHAM AND CO LTD',
    '400515' => 'HSBC BANK',
    '404246' => 'HSBC UK BANK',
    '309574' => 'LLOYDS BANK',
    '560006' => 'NATIONAL WESTMINSTER BANK',
    '089250' => 'THE CO-OPERATIVE BANK',
    '165050' => 'BANK OF AMERICA, NA',
    '400530' => 'HSBC BANK',
    '404758' => 'HSBC UK BANK',
    '309589' => 'LLOYDS BANK',
    '560009' => 'NATIONAL WESTMINSTER BANK',
    '089286' => 'THE CO-OPERATIVE BANK',
    '100000' => 'BANK OF ENGLAND',
    '402225' => 'HSBC BANK',
    '404759' => 'HSBC UK BANK',
    '309626' => 'LLOYDS BANK',
    '560013' => 'NATIONAL WESTMINSTER BANK',
    '089299' => 'THE CO-OPERATIVE BANK',
    '120103' => 'BANK OF SCOTLAND',
    '402534' => 'HSBC BANK',
    '404760' => 'HSBC UK BANK',
    '309634' => 'LLOYDS BANK',
    '560027' => 'NATIONAL WESTMINSTER BANK',
    '089300' => 'THE CO-OPERATIVE BANK',
    '121103' => 'BANK OF SCOTLAND',
    '403124' => 'HSBC BANK',
    '404761' => 'HSBC UK BANK',
    '309635' => 'LLOYDS BANK',
    '560033' => 'NATIONAL WESTMINSTER BANK',
    '161028' => 'THE ROYAL BANK OF SCOTLAND INTERNATIONAL LTD',
    '122026' => 'BANK OF SCOTLAND',
    '406162' => 'HSBC BANK',
    '404762' => 'HSBC UK BANK',
    '309696' => 'LLOYDS BANK',
    '560036' => 'NATIONAL WESTMINSTER BANK',
    '162029' => 'THE ROYAL BANK OF SCOTLAND INTERNATIONAL LTD',
    '122029' => 'BANK OF SCOTLAND',
    '400200' => 'HSBC UK BANK',
    '404763' => 'HSBC UK BANK',
    '309713' => 'LLOYDS BANK',
    '560049' => 'NATIONAL WESTMINSTER BANK',
    '601203' => 'THE ROYAL BANK OF SCOTLAND INTERNATIONAL LTD',
    '122481' => 'BANK OF SCOTLAND',
    '400300' => 'HSBC UK BANK',
    '404765' => 'HSBC UK BANK',
    '309751' => 'LLOYDS BANK',
    '560055' => 'NATIONAL WESTMINSTER BANK',
    '151000' => 'THE ROYAL BANK OF SCOTLAND',
    '122482' => 'BANK OF SCOTLAND',
    '400500' => 'HSBC UK BANK',
    '404773' => 'HSBC UK BANK',
    '309790' => 'LLOYDS BANK',
    '560068' => 'NATIONAL WESTMINSTER BANK',
    '158000' => 'THE ROYAL BANK OF SCOTLAND',
    '801180' => 'BANK OF SCOTLAND',
    '400621' => 'HSBC UK BANK',
    '404775' => 'HSBC UK BANK',
    '309871' => 'LLOYDS BANK',
    '600001' => 'NATIONAL WESTMINSTER BANK',
    '160015' => 'THE ROYAL BANK OF SCOTLAND',
    '802000' => 'BANK OF SCOTLAND',
    '400900' => 'HSBC UK BANK',
    '404780' => 'HSBC UK BANK',
    '309874' => 'LLOYDS BANK',
    '600846' => 'NATIONAL WESTMINSTER BANK',
    '160018' => 'THE ROYAL BANK OF SCOTLAND',
    '802045' => 'BANK OF SCOTLAND',
    '401000' => 'HSBC UK BANK',
    '404782' => 'HSBC UK BANK',
    '309897' => 'LLOYDS BANK',
    '601010' => 'NATIONAL WESTMINSTER BANK',
    '160038' => 'THE ROYAL BANK OF SCOTLAND',
    '802260' => 'BANK OF SCOTLAND',
    '401100' => 'HSBC UK BANK',
    '404783' => 'HSBC UK BANK',
    '770439' => 'LLOYDS BANK',
    '601319' => 'NATIONAL WESTMINSTER BANK',
    '160400' => 'THE ROYAL BANK OF SCOTLAND',
    '804635' => 'BANK OF SCOTLAND',
    '401118' => 'HSBC UK BANK',
    '404784' => 'HSBC UK BANK',
    '770440' => 'LLOYDS BANK',
    '601455' => 'NATIONAL WESTMINSTER BANK',
    '161623' => 'THE ROYAL BANK OF SCOTLAND',
    '203253' => 'BARCLAYS BANK',
    '401156' => 'HSBC UK BANK',
    '404786' => 'HSBC UK BANK',
    '774042' => 'LLOYDS BANK',
    '601531' => 'NATIONAL WESTMINSTER BANK',
    '162211' => 'THE ROYAL BANK OF SCOTLAND',
    '159900' => 'C HOARE & CO',
    '401158' => 'HSBC UK BANK',
    '404787' => 'HSBC UK BANK',
    '774926' => 'LLOYDS BANK',
    '601721' => 'NATIONAL WESTMINSTER BANK',
    '162337' => 'THE ROYAL BANK OF SCOTLAND',
    '040610' => 'CB PAYMENTS LTD',
    '401160' => 'HSBC UK BANK',
    '559100' => 'IOM BANK',
    '779110' => 'LLOYDS BANK',
    '602477' => 'NATIONAL WESTMINSTER BANK',
    '167001' => 'THE ROYAL BANK OF SCOTLAND',
    '185008' => 'CITIBANK NA',
    '401184' => 'HSBC UK BANK',
    '609242' => 'JPMORGAN CHASE BANK, N.A.',
    '230580' => 'METRO BANK',
    '603006' => 'NATIONAL WESTMINSTER BANK',
    '830425' => 'THE ROYAL BANK OF SCOTLAND',
    '041307' => 'CLEAR JUNCTION LIMITED',
    '401191' => 'HSBC UK BANK',
    '086119' => 'LEEDS BUILDING SOCIETY',
    '010502' => 'NATIONAL WESTMINSTER BANK',
    '603030' => 'NATIONAL WESTMINSTER BANK',
    '830706' => 'THE ROYAL BANK OF SCOTLAND',
    '040676' => 'CLEARBANK LIMITED',
    '401192' => 'HSBC UK BANK',
    '301641' => 'LLOYDS BANK INTERNATIONAL',
    '010838' => 'NATIONAL WESTMINSTER BANK',
    '604005' => 'NATIONAL WESTMINSTER BANK',
    '835100' => 'THE ROYAL BANK OF SCOTLAND',
    '608384' => 'CONTIS FINANCIAL SERVICES LIMITED',
    '401193' => 'HSBC UK BANK',
    '301663' => 'LLOYDS BANK INTERNATIONAL',
    '010917' => 'NATIONAL WESTMINSTER BANK',
    '606005' => 'NATIONAL WESTMINSTER BANK',
    '165810' => 'TRIODOS BANK UK LTD',
    '165221' => 'CUMBERLAND BUILDING SOCIETY',
    '401199' => 'HSBC UK BANK',
    '300002' => 'LLOYDS BANK',
    '011001' => 'NATIONAL WESTMINSTER BANK',
    '606040' => 'NATIONAL WESTMINSTER BANK',
    '826138' => 'VIRGIN MONEY',
    '406377' => 'CYNERGY BANK LIMITED',
    '401255' => 'HSBC UK BANK',
    '300005' => 'LLOYDS BANK',
    '013099' => 'NATIONAL WESTMINSTER BANK',
    '607080' => 'NATIONAL WESTMINSTER BANK',
    '826837' => 'VIRGIN MONEY',
    '110001' => 'HALIFAX',
    '401276' => 'HSBC UK BANK',
    '300009' => 'LLOYDS BANK',
    '016714' => 'NATIONAL WESTMINSTER BANK',
    '608009' => 'NATIONAL WESTMINSTER BANK',
    '826839' => 'VIRGIN MONEY',
    '826842' => 'VIRGIN MONEY',
    '111626' => 'HALIFAX',
    '401315' => 'HSBC UK BANK',
    '301553' => 'LLOYDS BANK',
    '500000' => 'NATIONAL WESTMINSTER BANK',
    '950121' => 'NORTHERN BANK LIMITED T/A DANSKE BANK',
    '827018' => 'VIRGIN MONEY',
    '111811' => 'HALIFAX',
    '401413' => 'HSBC UK BANK',
    '301599' => 'LLOYDS BANK',
    '503010' => 'NATIONAL WESTMINSTER BANK',
    '950679' => 'NORTHERN BANK LIMITED T/A DANSKE BANK',
    '086064' => 'VIRGIN MONEY',
    '116414' => 'HALIFAX',
    '401608' => 'HSBC UK BANK',
    '302580' => 'LLOYDS BANK',
    '504101' => 'NATIONAL WESTMINSTER BANK',
    '231486' => 'PAYONEER EUROPE LIMITED',
    '406425' => 'VIRGIN MONEY',
    '116435' => 'HALIFAX',
    '401817' => 'HSBC UK BANK',
    '308012' => 'LLOYDS BANK',
    '504237' => 'NATIONAL WESTMINSTER BANK',
    '166050' => 'RBS ONE ACCOUNT',
    '050005' => 'VIRGIN MONEY',
    '116459' => 'HALIFAX',
    '401841' => 'HSBC UK BANK',
    '309089' => 'LLOYDS BANK',
    '515003' => 'NATIONAL WESTMINSTER BANK',
    '166051' => 'RBS ONE ACCOUNT',
    '050020' => 'VIRGIN MONEY',
    '117180' => 'HALIFAX',
    '401915' => 'HSBC UK BANK',
    '309191' => 'LLOYDS BANK',
    '515014' => 'NATIONAL WESTMINSTER BANK',
    '720000' => 'SANTANDER UK',
    '070030' => 'NATIONWIDE BUILDING SOCIETY',
    '119100' => 'HALIFAX',
    '402226' => 'HSBC UK BANK',
    '309374' => 'LLOYDS BANK',
    '536107' => 'NATIONAL WESTMINSTER BANK',
    '621000' => 'SILICON VALLEY BANK UK LIMITED',
    '070093' => 'NATIONWIDE BUILDING SOCIETY',
    '119109' => 'HALIFAX',
    '402715' => 'HSBC UK BANK',
    '309442' => 'LLOYDS BANK',
    '557013' => 'NATIONAL WESTMINSTER BANK',
    '608371' => 'STARLING BANK LIMITED',
    '070116' => 'NATIONWIDE BUILDING SOCIETY',
    '405162' => 'HANDELSBANKEN',
    '403418' => 'HSBC UK BANK',
    '309455' => 'LLOYDS BANK',
    '558126' => 'NATIONAL WESTMINSTER BANK',
    '700225' => 'THE BANK OF NEW YORK MELLON',
    '070246' => 'NATIONWIDE BUILDING SOCIETY',
    '400250' => 'HSBC BANK',
    '403804' => 'HSBC UK BANK',
    '309497' => 'LLOYDS BANK',
    '560003' => 'NATIONAL WESTMINSTER BANK',
    '089066' => 'THE CO-OPERATIVE BANK',
    '071040' => 'NATIONWIDE BUILDING SOCIETY'
];

function swiftLookup(string $swift): array
{
    $url = "https://bicsearch.com/bicsearchapi/search_bic.php?bic={$swift}&btn_submit=";
    $output = file_get_contents($url);

    if ($output === false) {
        return [];
    }

    $text = end(explode('}', strip_tags($output)));
    $text = preg_replace('/\s+/', ' ', trim($text));
    $parts = explode("\n", $text);

    $result = [];
    foreach ($parts as $item) {
        [$key, $value] = array_pad(explode(':', $item, 2), 2, '');
        $result[strtolower($key)] = str_replace(', ,', ', ', trim($value));
    }

    unset($result['type']);
    return $result;
}

function getBankInfo(string $type, string $identifier): array
{
    $bankInfo = match ($type) {
        'swift' => getSwiftBankInfo($identifier),
        'iban' => getIbanBankInfo($identifier),
        'routing' => getRoutingBankInfo($identifier),
        'sortcode' => getSortcodeBankInfo($identifier),
        default => [
            'identifier' => $identifier,
            'bank' => '',
            'address' => '',
        ],
    };

    return $bankInfo ?: [];
}

function getSwiftBankInfo(string $swift): array
{
    $res = tiny::http()->post('https://bank.codes/swift-code-checker/', [
        'json' => ['swift' => $swift]
    ]);
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($res->text)));

    preg_match('/Bank\s+(.*?)\s+Address\s+(.*?)$/i', $text, $matches);

    return [
        'swift' => $swift,
        'bank' => $matches[1] ?? '',
        'address' => str_replace(' ,', ',', $matches[2] ?? ''),
    ];
}

function getIbanBankInfo(string $iban): array
{
    $res = tiny::http()->post('https://bank.codes/iban/validate/', [
        'json' => ['iban' => $iban]
    ]);
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($res->text)));

    preg_match('/IBAN &amp; Bank Details(.*?)USE WISE TO/is', $text, $matches);
    $info = explode("\n", trim($matches[1] ?? ''));

    return [
        'iban' => trim($info[1] ?? ''),
        'bank' => trim($info[20] ?? ''),
        'address' => trim(($info[24] ?? '') . ($info[5] ?? '')),
    ];
}

function getRoutingBankInfo(string $routing): array
{
    $res = tiny::http()->post('https://bank.codes/us-routing-number-checker/', [
        'json' => ['routing' => $routing]
    ]);
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($res->text)));

    preg_match("/Detail Information of Routing Number $routing(.*?)$/is", $text, $matches);
    $info = array_slice(explode("\n", trim($matches[1] ?? '')), 3, 5);

    return [
        'routing' => $routing,
        'bank' => substr($info[0] ?? '', 4),
        'address' => implode(' ', array_map(fn($line) => substr($line, strpos($line, ':') + 1), array_slice($info, 1))),
    ];
}

function getSortcodeBankInfo(string $sortcode): array
{
    $sortcode = preg_replace('/[^0-9]/', '', $sortcode);
    return [
        'sortcode' => $sortcode,
        'bank' => SORTCODES[$sortcode] ?? '',
        'address' => '',
    ];
}
