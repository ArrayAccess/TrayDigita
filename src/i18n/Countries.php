<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\i18n;

use ArrayAccess\TrayDigita\i18n\Records\Country;
use function is_numeric;
use function is_string;
use function ltrim;
use function strlen;
use function strtoupper;
use function substr;

class Countries
{
    /**
     * @var array[]
     */
    const LISTS = [
        "AN" => [
            "name" => "Netherlands Antilles",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "AN",
                "alpha3" => "ANT"
            ],
            "numeric" => "599",
            "currencies" => [
                "ANG"
            ],
            "timezones" => [
                "America/Curacao"
            ],
            "dial_code" => "+599"
        ],
        "BD" => [
            "name" => "Bangladesh",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "BD",
                "alpha3" => "BGD"
            ],
            "numeric" => "050",
            "currencies" => [
                "BDT"
            ],
            "timezones" => [
                "Asia/Dhaka"
            ],
            "dial_code" => "+880"
        ],
        "BE" => [
            "name" => "Belgium",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "BE",
                "alpha3" => "BEL"
            ],
            "numeric" => "056",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Brussels"
            ],
            "dial_code" => "+32"
        ],
        "BF" => [
            "name" => "Burkina Faso",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "BF",
                "alpha3" => "BFA"
            ],
            "numeric" => "854",
            "currencies" => [
                "XOF"
            ],
            "timezones" => [
                "Africa/Ouagadougou"
            ],
            "dial_code" => "+226"
        ],
        "BG" => [
            "name" => "Bulgaria",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "BG",
                "alpha3" => "BGR"
            ],
            "numeric" => "100",
            "currencies" => [
                "BGN"
            ],
            "timezones" => [
                "Europe/Sofia"
            ],
            "dial_code" => "+359"
        ],
        "BA" => [
            "name" => "Bosnia and Herzegovina",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "BA",
                "alpha3" => "BIH"
            ],
            "numeric" => "070",
            "currencies" => [
                "BAM"
            ],
            "timezones" => [
                "Europe/Sarajevo"
            ],
            "dial_code" => "+387"
        ],
        "BB" => [
            "name" => "Barbados",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "BB",
                "alpha3" => "BRB"
            ],
            "numeric" => "052",
            "currencies" => [
                "BBD"
            ],
            "timezones" => [
                "America/Barbados"
            ],
            "dial_code" => "+1246"
        ],
        "WF" => [
            "name" => "Wallis and Futuna",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "WF",
                "alpha3" => "WLF"
            ],
            "numeric" => "876",
            "currencies" => [
                "XPF"
            ],
            "timezones" => [
                "Pacific/Wallis"
            ],
            "dial_code" => "+681"
        ],
        "BL" => [
            "name" => "Saint Barthelemy",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "BL",
                "alpha3" => "BLM"
            ],
            "numeric" => "652",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "America/St_Barthelemy"
            ],
            "dial_code" => "+590"
        ],
        "BM" => [
            "name" => "Bermuda",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "BM",
                "alpha3" => "BMU"
            ],
            "numeric" => "060",
            "currencies" => [
                "BMD"
            ],
            "timezones" => [
                "Atlantic/Bermuda"
            ],
            "dial_code" => "+1441"
        ],
        "BN" => [
            "name" => "Brunei",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "BN",
                "alpha3" => "BRN"
            ],
            "numeric" => "096",
            "currencies" => [
                "BND",
                "SGD"
            ],
            "timezones" => [
                "Asia/Brunei"
            ],
            "dial_code" => "+673"
        ],
        "BO" => [
            "name" => "Bolivia",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "BO",
                "alpha3" => "BOL"
            ],
            "numeric" => "068",
            "currencies" => [
                "BOB"
            ],
            "timezones" => [
                "America/La_Paz"
            ],
            "dial_code" => "+591"
        ],
        "BH" => [
            "name" => "Bahrain",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "BH",
                "alpha3" => "BHR"
            ],
            "numeric" => "048",
            "currencies" => [
                "BHD"
            ],
            "timezones" => [
                "Asia/Bahrain"
            ],
            "dial_code" => "+973"
        ],
        "BI" => [
            "name" => "Burundi",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "BI",
                "alpha3" => "BDI"
            ],
            "numeric" => "108",
            "currencies" => [
                "BIF"
            ],
            "timezones" => [
                "Africa/Bujumbura"
            ],
            "dial_code" => "+257"
        ],
        "BJ" => [
            "name" => "Benin",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "BJ",
                "alpha3" => "BEN"
            ],
            "numeric" => "204",
            "currencies" => [
                "XOF"
            ],
            "timezones" => [
                "Africa/Porto-Novo"
            ],
            "dial_code" => "+229"
        ],
        "BT" => [
            "name" => "Bhutan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "BT",
                "alpha3" => "BTN"
            ],
            "numeric" => "064",
            "currencies" => [
                "BTN"
            ],
            "timezones" => [
                "Asia/Thimphu"
            ],
            "dial_code" => "+975"
        ],
        "JM" => [
            "name" => "Jamaica",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "JM",
                "alpha3" => "JAM"
            ],
            "numeric" => "388",
            "currencies" => [
                "JMD"
            ],
            "timezones" => [
                "America/Jamaica"
            ],
            "dial_code" => "+1876"
        ],
        "BV" => [
            "name" => "Bouvet Island",
            "continent" => [
                "code" => "AN",
                "name" => "Antarctica"
            ],
            "code" => [
                "alpha2" => "BV",
                "alpha3" => "BVT"
            ],
            "numeric" => "074",
            "currencies" => [
                "NOK"
            ],
            "timezones" => [
                "Europe/Oslo"
            ],
            "dial_code" => "+47"
        ],
        "BW" => [
            "name" => "Botswana",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "BW",
                "alpha3" => "BWA"
            ],
            "numeric" => "072",
            "currencies" => [
                "BWP"
            ],
            "timezones" => [
                "Africa/Gaborone"
            ],
            "dial_code" => "+267"
        ],
        "WS" => [
            "name" => "Samoa",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "WS",
                "alpha3" => "WSM"
            ],
            "numeric" => "882",
            "currencies" => [
                "WST"
            ],
            "timezones" => [
                "Pacific/Apia"
            ],
            "dial_code" => "+685"
        ],
        "BQ" => [
            "name" => "Bonaire, Saint Eustatius and Saba",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "BQ",
                "alpha3" => "BES"
            ],
            "numeric" => "535",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "America/Kralendijk"
            ],
            "dial_code" => "+599"
        ],
        "BR" => [
            "name" => "Brazil",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "BR",
                "alpha3" => "BRA"
            ],
            "numeric" => "076",
            "currencies" => [
                "BRL"
            ],
            "timezones" => [
                "America/Araguaina",
                "America/Bahia",
                "America/Belem",
                "America/Boa_Vista",
                "America/Campo_Grande",
                "America/Cuiaba",
                "America/Eirunepe",
                "America/Fortaleza",
                "America/Maceio",
                "America/Manaus",
                "America/Noronha",
                "America/Porto_Velho",
                "America/Recife",
                "America/Rio_Branco",
                "America/Santarem",
                "America/Sao_Paulo"
            ],
            "dial_code" => "+55"
        ],
        "BS" => [
            "name" => "Bahamas",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "BS",
                "alpha3" => "BHS"
            ],
            "numeric" => "044",
            "currencies" => [
                "BSD"
            ],
            "timezones" => [
                "America/Nassau"
            ],
            "dial_code" => "+1242"
        ],
        "JE" => [
            "name" => "Jersey",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "JE",
                "alpha3" => "JEY"
            ],
            "numeric" => "832",
            "currencies" => [
                "GBP"
            ],
            "timezones" => [
                "Europe/Jersey"
            ],
            "dial_code" => "+44"
        ],
        "BY" => [
            "name" => "Belarus",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "BY",
                "alpha3" => "BLR"
            ],
            "numeric" => "112",
            "currencies" => [
                "BYN"
            ],
            "timezones" => [
                "Europe/Minsk"
            ],
            "dial_code" => "+375"
        ],
        "BZ" => [
            "name" => "Belize",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "BZ",
                "alpha3" => "BLZ"
            ],
            "numeric" => "084",
            "currencies" => [
                "BZD"
            ],
            "timezones" => [
                "America/Belize"
            ],
            "dial_code" => "+501"
        ],
        "RU" => [
            "name" => "Russia",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "RU",
                "alpha3" => "RUS"
            ],
            "numeric" => "643",
            "currencies" => [
                "RUB"
            ],
            "timezones" => [
                "Asia/Anadyr",
                "Asia/Barnaul",
                "Asia/Chita",
                "Asia/Irkutsk",
                "Asia/Kamchatka",
                "Asia/Khandyga",
                "Asia/Krasnoyarsk",
                "Asia/Magadan",
                "Asia/Novokuznetsk",
                "Asia/Novosibirsk",
                "Asia/Omsk",
                "Asia/Sakhalin",
                "Asia/Srednekolymsk",
                "Asia/Tomsk",
                "Asia/Ust-Nera",
                "Asia/Vladivostok",
                "Asia/Yakutsk",
                "Asia/Yekaterinburg",
                "Europe/Astrakhan",
                "Europe/Kaliningrad",
                "Europe/Kirov",
                "Europe/Moscow",
                "Europe/Samara",
                "Europe/Saratov",
                "Europe/Ulyanovsk",
                "Europe/Volgograd"
            ],
            "dial_code" => "+7"
        ],
        "RW" => [
            "name" => "Rwanda",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "RW",
                "alpha3" => "RWA"
            ],
            "numeric" => "646",
            "currencies" => [
                "RWF"
            ],
            "timezones" => [
                "Africa/Kigali"
            ],
            "dial_code" => "+250"
        ],
        "RS" => [
            "name" => "Serbia",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "RS",
                "alpha3" => "SRB"
            ],
            "numeric" => "688",
            "currencies" => [
                "RSD"
            ],
            "timezones" => [
                "Europe/Belgrade"
            ],
            "dial_code" => "+381"
        ],
        "TL" => [
            "name" => "East Timor",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "TL",
                "alpha3" => "TLS"
            ],
            "numeric" => "626",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "Asia/Dili"
            ],
            "dial_code" => "+670"
        ],
        "RE" => [
            "name" => "Reunion",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "RE",
                "alpha3" => "REU"
            ],
            "numeric" => "638",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Indian/Reunion"
            ],
            "dial_code" => "+262"
        ],
        "TM" => [
            "name" => "Turkmenistan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "TM",
                "alpha3" => "TKM"
            ],
            "numeric" => "795",
            "currencies" => [
                "TMT"
            ],
            "timezones" => [
                "Asia/Ashgabat"
            ],
            "dial_code" => "+993"
        ],
        "TJ" => [
            "name" => "Tajikistan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "TJ",
                "alpha3" => "TJK"
            ],
            "numeric" => "762",
            "currencies" => [
                "TJS"
            ],
            "timezones" => [
                "Asia/Dushanbe"
            ],
            "dial_code" => "+992"
        ],
        "RO" => [
            "name" => "Romania",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "RO",
                "alpha3" => "ROU"
            ],
            "numeric" => "642",
            "currencies" => [
                "RON"
            ],
            "timezones" => [
                "Europe/Bucharest"
            ],
            "dial_code" => "+40"
        ],
        "TK" => [
            "name" => "Tokelau",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "TK",
                "alpha3" => "TKL"
            ],
            "numeric" => "772",
            "currencies" => [
                "NZD"
            ],
            "timezones" => [
                "Pacific/Fakaofo"
            ],
            "dial_code" => "+690"
        ],
        "GW" => [
            "name" => "Guinea-Bissau",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "GW",
                "alpha3" => "GNB"
            ],
            "numeric" => "624",
            "currencies" => [
                "XOF"
            ],
            "timezones" => [
                "Africa/Bissau"
            ],
            "dial_code" => "+245"
        ],
        "GU" => [
            "name" => "Guam",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "GU",
                "alpha3" => "GUM"
            ],
            "numeric" => "316",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "Pacific/Guam"
            ],
            "dial_code" => "+1671"
        ],
        "GT" => [
            "name" => "Guatemala",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "GT",
                "alpha3" => "GTM"
            ],
            "numeric" => "320",
            "currencies" => [
                "GTQ"
            ],
            "timezones" => [
                "America/Guatemala"
            ],
            "dial_code" => "+502"
        ],
        "GS" => [
            "name" => "South Georgia and the South Sandwich Islands",
            "continent" => [
                "code" => "AN",
                "name" => "Antarctica"
            ],
            "code" => [
                "alpha2" => "GS",
                "alpha3" => "SGS"
            ],
            "numeric" => "239",
            "currencies" => [
                "GBP"
            ],
            "timezones" => [
                "Atlantic/South_Georgia"
            ],
            "dial_code" => "+500"
        ],
        "GR" => [
            "name" => "Greece",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "GR",
                "alpha3" => "GRC"
            ],
            "numeric" => "300",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Athens"
            ],
            "dial_code" => "+30"
        ],
        "GQ" => [
            "name" => "Equatorial Guinea",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "GQ",
                "alpha3" => "GNQ"
            ],
            "numeric" => "226",
            "currencies" => [
                "XAF"
            ],
            "timezones" => [
                "Africa/Malabo"
            ],
            "dial_code" => "+240"
        ],
        "GP" => [
            "name" => "Guadeloupe",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "GP",
                "alpha3" => "GLP"
            ],
            "numeric" => "312",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "America/Guadeloupe"
            ],
            "dial_code" => "+590"
        ],
        "JP" => [
            "name" => "Japan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "JP",
                "alpha3" => "JPN"
            ],
            "numeric" => "392",
            "currencies" => [
                "JPY"
            ],
            "timezones" => [
                "Asia/Tokyo"
            ],
            "dial_code" => "+81"
        ],
        "GY" => [
            "name" => "Guyana",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "GY",
                "alpha3" => "GUY"
            ],
            "numeric" => "328",
            "currencies" => [
                "GYD"
            ],
            "timezones" => [
                "America/Guyana"
            ],
            "dial_code" => "+595"
        ],
        "GG" => [
            "name" => "Guernsey",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "GG",
                "alpha3" => "GGY"
            ],
            "numeric" => "831",
            "currencies" => [
                "GBP"
            ],
            "timezones" => [
                "Europe/Guernsey"
            ],
            "dial_code" => "+44"
        ],
        "GF" => [
            "name" => "French Guiana",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "GF",
                "alpha3" => "GUF"
            ],
            "numeric" => "254",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "America/Cayenne"
            ],
            "dial_code" => "+594"
        ],
        "GE" => [
            "name" => "Georgia",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "GE",
                "alpha3" => "GEO"
            ],
            "numeric" => "268",
            "currencies" => [
                "GEL"
            ],
            "timezones" => [
                "Asia/Tbilisi"
            ],
            "dial_code" => "+995"
        ],
        "GD" => [
            "name" => "Grenada",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "GD",
                "alpha3" => "GRD"
            ],
            "numeric" => "308",
            "currencies" => [
                "XCD"
            ],
            "timezones" => [
                "America/Grenada"
            ],
            "dial_code" => "+1473"
        ],
        "GB" => [
            "name" => "United Kingdom",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "GB",
                "alpha3" => "GBR"
            ],
            "numeric" => "826",
            "currencies" => [
                "GBP"
            ],
            "timezones" => [
                "Europe/London"
            ],
            "dial_code" => "+44"
        ],
        "GA" => [
            "name" => "Gabon",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "GA",
                "alpha3" => "GAB"
            ],
            "numeric" => "266",
            "currencies" => [
                "XAF"
            ],
            "timezones" => [
                "Africa/Libreville"
            ],
            "dial_code" => "+241"
        ],
        "SV" => [
            "name" => "El Salvador",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "SV",
                "alpha3" => "SLV"
            ],
            "numeric" => "222",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "America/El_Salvador"
            ],
            "dial_code" => "+503"
        ],
        "GN" => [
            "name" => "Guinea",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "GN",
                "alpha3" => "GIN"
            ],
            "numeric" => "324",
            "currencies" => [
                "GNF"
            ],
            "timezones" => [
                "Africa/Conakry"
            ],
            "dial_code" => "+224"
        ],
        "GM" => [
            "name" => "Gambia",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "GM",
                "alpha3" => "GMB"
            ],
            "numeric" => "270",
            "currencies" => [
                "GMD"
            ],
            "timezones" => [
                "Africa/Banjul"
            ],
            "dial_code" => "+220"
        ],
        "GL" => [
            "name" => "Greenland",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "GL",
                "alpha3" => "GRL"
            ],
            "numeric" => "304",
            "currencies" => [
                "DKK"
            ],
            "timezones" => [
                "America/Danmarkshavn",
                "America/Godthab",
                "America/Scoresbysund",
                "America/Thule"
            ],
            "dial_code" => "+299"
        ],
        "GI" => [
            "name" => "Gibraltar",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "GI",
                "alpha3" => "GIB"
            ],
            "numeric" => "292",
            "currencies" => [
                "GIP"
            ],
            "timezones" => [
                "Europe/Gibraltar"
            ],
            "dial_code" => "+350"
        ],
        "GH" => [
            "name" => "Ghana",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "GH",
                "alpha3" => "GHA"
            ],
            "numeric" => "288",
            "currencies" => [
                "GHS"
            ],
            "timezones" => [
                "Africa/Accra"
            ],
            "dial_code" => "+233"
        ],
        "OM" => [
            "name" => "Oman",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "OM",
                "alpha3" => "OMN"
            ],
            "numeric" => "512",
            "currencies" => [
                "OMR"
            ],
            "timezones" => [
                "Asia/Muscat"
            ],
            "dial_code" => "+968"
        ],
        "TN" => [
            "name" => "Tunisia",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "TN",
                "alpha3" => "TUN"
            ],
            "numeric" => "788",
            "currencies" => [
                "TND"
            ],
            "timezones" => [
                "Africa/Tunis"
            ],
            "dial_code" => "+216"
        ],
        "JO" => [
            "name" => "Jordan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "JO",
                "alpha3" => "JOR"
            ],
            "numeric" => "400",
            "currencies" => [
                "JOD"
            ],
            "timezones" => [
                "Asia/Amman"
            ],
            "dial_code" => "+962"
        ],
        "HR" => [
            "name" => "Croatia",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "HR",
                "alpha3" => "HRV"
            ],
            "numeric" => "191",
            "currencies" => [
                "HRK"
            ],
            "timezones" => [
                "Europe/Zagreb"
            ],
            "dial_code" => "+385"
        ],
        "HT" => [
            "name" => "Haiti",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "HT",
                "alpha3" => "HTI"
            ],
            "numeric" => "332",
            "currencies" => [
                "HTG"
            ],
            "timezones" => [
                "America/Port-au-Prince"
            ],
            "dial_code" => "+509"
        ],
        "HU" => [
            "name" => "Hungary",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "HU",
                "alpha3" => "HUN"
            ],
            "numeric" => "348",
            "currencies" => [
                "HUF"
            ],
            "timezones" => [
                "Europe/Budapest"
            ],
            "dial_code" => "+36"
        ],
        "HK" => [
            "name" => "Hong Kong",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "HK",
                "alpha3" => "HKG"
            ],
            "numeric" => "344",
            "currencies" => [
                "HKD"
            ],
            "timezones" => [
                "Asia/Hong_Kong"
            ],
            "dial_code" => "+852"
        ],
        "HN" => [
            "name" => "Honduras",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "HN",
                "alpha3" => "HND"
            ],
            "numeric" => "340",
            "currencies" => [
                "HNL"
            ],
            "timezones" => [
                "America/Tegucigalpa"
            ],
            "dial_code" => "+504"
        ],
        "HM" => [
            "name" => "Heard Island and McDonald Islands",
            "continent" => [
                "code" => "AN",
                "name" => "Antarctica"
            ],
            "code" => [
                "alpha2" => "HM",
                "alpha3" => "HMD"
            ],
            "numeric" => "334",
            "currencies" => [
                "AUD"
            ],
            "timezones" => [
                "Indian/Kerguelen"
            ],
            "dial_code" => "+672"
        ],
        "VE" => [
            "name" => "Venezuela",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "VE",
                "alpha3" => "VEN"
            ],
            "numeric" => "862",
            "currencies" => [
                "VEF"
            ],
            "timezones" => [
                "America/Caracas"
            ],
            "dial_code" => "+58"
        ],
        "PR" => [
            "name" => "Puerto Rico",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "PR",
                "alpha3" => "PRI"
            ],
            "numeric" => "630",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "America/Puerto_Rico"
            ],
            "dial_code" => "+1939"
        ],
        "PS" => [
            "name" => "Palestinian Territory",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "PS",
                "alpha3" => "PSE"
            ],
            "numeric" => "275",
            "currencies" => [
                "ILS"
            ],
            "timezones" => [
                "Asia/Gaza",
                "Asia/Hebron"
            ],
            "dial_code" => "+970"
        ],
        "PW" => [
            "name" => "Palau",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "PW",
                "alpha3" => "PLW"
            ],
            "numeric" => "585",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "Pacific/Palau"
            ],
            "dial_code" => "+680"
        ],
        "PT" => [
            "name" => "Portugal",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "PT",
                "alpha3" => "PRT"
            ],
            "numeric" => "620",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Atlantic/Azores",
                "Atlantic/Madeira",
                "Europe/Lisbon"
            ],
            "dial_code" => "+351"
        ],
        "SJ" => [
            "name" => "Svalbard and Jan Mayen",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "SJ",
                "alpha3" => "SJM"
            ],
            "numeric" => "744",
            "currencies" => [
                "NOK"
            ],
            "timezones" => [
                "Arctic/Longyearbyen"
            ],
            "dial_code" => "+47"
        ],
        "PY" => [
            "name" => "Paraguay",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "PY",
                "alpha3" => "PRY"
            ],
            "numeric" => "600",
            "currencies" => [
                "PYG"
            ],
            "timezones" => [
                "America/Asuncion"
            ],
            "dial_code" => "+595"
        ],
        "IQ" => [
            "name" => "Iraq",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "IQ",
                "alpha3" => "IRQ"
            ],
            "numeric" => "368",
            "currencies" => [
                "IQD"
            ],
            "timezones" => [
                "Asia/Baghdad"
            ],
            "dial_code" => "+964"
        ],
        "PA" => [
            "name" => "Panama",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "PA",
                "alpha3" => "PAN"
            ],
            "numeric" => "591",
            "currencies" => [
                "PAB"
            ],
            "timezones" => [
                "America/Panama"
            ],
            "dial_code" => "+507"
        ],
        "PF" => [
            "name" => "French Polynesia",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "PF",
                "alpha3" => "PYF"
            ],
            "numeric" => "258",
            "currencies" => [
                "XPF"
            ],
            "timezones" => [
                "Pacific/Gambier",
                "Pacific/Marquesas",
                "Pacific/Tahiti"
            ],
            "dial_code" => "+689"
        ],
        "PG" => [
            "name" => "Papua New Guinea",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "PG",
                "alpha3" => "PNG"
            ],
            "numeric" => "598",
            "currencies" => [
                "PGK"
            ],
            "timezones" => [
                "Pacific/Bougainville",
                "Pacific/Port_Moresby"
            ],
            "dial_code" => "+675"
        ],
        "PE" => [
            "name" => "Peru",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "PE",
                "alpha3" => "PER"
            ],
            "numeric" => "604",
            "currencies" => [
                "PEN"
            ],
            "timezones" => [
                "America/Lima"
            ],
            "dial_code" => "+51"
        ],
        "PK" => [
            "name" => "Pakistan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "PK",
                "alpha3" => "PAK"
            ],
            "numeric" => "586",
            "currencies" => [
                "PKR"
            ],
            "timezones" => [
                "Asia/Karachi"
            ],
            "dial_code" => "+92"
        ],
        "PH" => [
            "name" => "Philippines",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "PH",
                "alpha3" => "PHL"
            ],
            "numeric" => "608",
            "currencies" => [
                "PHP"
            ],
            "timezones" => [
                "Asia/Manila"
            ],
            "dial_code" => "+63"
        ],
        "PN" => [
            "name" => "Pitcairn",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "PN",
                "alpha3" => "PCN"
            ],
            "numeric" => "612",
            "currencies" => [
                "NZD"
            ],
            "timezones" => [
                "Pacific/Pitcairn"
            ],
            "dial_code" => "+872"
        ],
        "PL" => [
            "name" => "Poland",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "PL",
                "alpha3" => "POL"
            ],
            "numeric" => "616",
            "currencies" => [
                "PLN"
            ],
            "timezones" => [
                "Europe/Warsaw"
            ],
            "dial_code" => "+48"
        ],
        "PM" => [
            "name" => "Saint Pierre and Miquelon",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "PM",
                "alpha3" => "SPM"
            ],
            "numeric" => "666",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "America/Miquelon"
            ],
            "dial_code" => "+508"
        ],
        "ZM" => [
            "name" => "Zambia",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "ZM",
                "alpha3" => "ZMB"
            ],
            "numeric" => "894",
            "currencies" => [
                "ZMW"
            ],
            "timezones" => [
                "Africa/Lusaka"
            ],
            "dial_code" => "+260"
        ],
        "EH" => [
            "name" => "Western Sahara",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "EH",
                "alpha3" => "ESH"
            ],
            "numeric" => "732",
            "currencies" => [
                "MAD"
            ],
            "timezones" => [
                "Africa/El_Aaiun"
            ],
            "dial_code" => "+212"
        ],
        "EE" => [
            "name" => "Estonia",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "EE",
                "alpha3" => "EST"
            ],
            "numeric" => "233",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Tallinn"
            ],
            "dial_code" => "+372"
        ],
        "EG" => [
            "name" => "Egypt",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "EG",
                "alpha3" => "EGY"
            ],
            "numeric" => "818",
            "currencies" => [
                "EGP"
            ],
            "timezones" => [
                "Africa/Cairo"
            ],
            "dial_code" => "+20"
        ],
        "ZA" => [
            "name" => "South Africa",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "ZA",
                "alpha3" => "ZAF"
            ],
            "numeric" => "710",
            "currencies" => [
                "ZAR"
            ],
            "timezones" => [
                "Africa/Johannesburg"
            ],
            "dial_code" => "+27"
        ],
        "EC" => [
            "name" => "Ecuador",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "EC",
                "alpha3" => "ECU"
            ],
            "numeric" => "218",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "America/Guayaquil",
                "Pacific/Galapagos"
            ],
            "dial_code" => "+593"
        ],
        "IT" => [
            "name" => "Italy",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "IT",
                "alpha3" => "ITA"
            ],
            "numeric" => "380",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Rome"
            ],
            "dial_code" => "+39"
        ],
        "VN" => [
            "name" => "Vietnam",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "VN",
                "alpha3" => "VNM"
            ],
            "numeric" => "704",
            "currencies" => [
                "VND"
            ],
            "timezones" => [
                "Asia/Ho_Chi_Minh"
            ],
            "dial_code" => "+84"
        ],
        "SB" => [
            "name" => "Solomon Islands",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "SB",
                "alpha3" => "SLB"
            ],
            "numeric" => "090",
            "currencies" => [
                "SBD"
            ],
            "timezones" => [
                "Pacific/Guadalcanal"
            ],
            "dial_code" => "+677"
        ],
        "ET" => [
            "name" => "Ethiopia",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "ET",
                "alpha3" => "ETH"
            ],
            "numeric" => "231",
            "currencies" => [
                "ETB"
            ],
            "timezones" => [
                "Africa/Addis_Ababa"
            ],
            "dial_code" => "+251"
        ],
        "SO" => [
            "name" => "Somalia",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "SO",
                "alpha3" => "SOM"
            ],
            "numeric" => "706",
            "currencies" => [
                "SOS"
            ],
            "timezones" => [
                "Africa/Mogadishu"
            ],
            "dial_code" => "+252"
        ],
        "ZW" => [
            "name" => "Zimbabwe",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "ZW",
                "alpha3" => "ZWE"
            ],
            "numeric" => "716",
            "currencies" => [
                "BWP",
                "EUR",
                "GBP",
                "USD",
                "ZAR"
            ],
            "timezones" => [
                "Africa/Harare"
            ],
            "dial_code" => "+263"
        ],
        "SA" => [
            "name" => "Saudi Arabia",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "SA",
                "alpha3" => "SAU"
            ],
            "numeric" => "682",
            "currencies" => [
                "SAR"
            ],
            "timezones" => [
                "Asia/Riyadh"
            ],
            "dial_code" => "+966"
        ],
        "ES" => [
            "name" => "Spain",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "ES",
                "alpha3" => "ESP"
            ],
            "numeric" => "724",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Africa/Ceuta",
                "Atlantic/Canary",
                "Europe/Madrid"
            ],
            "dial_code" => "+34"
        ],
        "ER" => [
            "name" => "Eritrea",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "ER",
                "alpha3" => "ERI"
            ],
            "numeric" => "232",
            "currencies" => [
                "ERN"
            ],
            "timezones" => [
                "Africa/Asmara"
            ],
            "dial_code" => "+291"
        ],
        "ME" => [
            "name" => "Montenegro",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "ME",
                "alpha3" => "MNE"
            ],
            "numeric" => "499",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Podgorica"
            ],
            "dial_code" => "+382"
        ],
        "MD" => [
            "name" => "Moldova",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "MD",
                "alpha3" => "MDA"
            ],
            "numeric" => "498",
            "currencies" => [
                "MDL"
            ],
            "timezones" => [
                "Europe/Chisinau"
            ],
            "dial_code" => "+373"
        ],
        "MG" => [
            "name" => "Madagascar",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "MG",
                "alpha3" => "MDG"
            ],
            "numeric" => "450",
            "currencies" => [
                "MGA"
            ],
            "timezones" => [
                "Indian/Antananarivo"
            ],
            "dial_code" => "+261"
        ],
        "MF" => [
            "name" => "Saint Martin",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "MF",
                "alpha3" => "MAF"
            ],
            "numeric" => "663",
            "currencies" => [
                "EUR",
                "USD"
            ],
            "timezones" => [
                "America/Marigot"
            ],
            "dial_code" => "+590"
        ],
        "MA" => [
            "name" => "Morocco",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "MA",
                "alpha3" => "MAR"
            ],
            "numeric" => "504",
            "currencies" => [
                "MAD"
            ],
            "timezones" => [
                "Africa/Casablanca"
            ],
            "dial_code" => "+212"
        ],
        "MC" => [
            "name" => "Monaco",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "MC",
                "alpha3" => "MCO"
            ],
            "numeric" => "492",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Monaco"
            ],
            "dial_code" => "+377"
        ],
        "UZ" => [
            "name" => "Uzbekistan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "UZ",
                "alpha3" => "UZB"
            ],
            "numeric" => "860",
            "currencies" => [
                "UZS"
            ],
            "timezones" => [
                "Asia/Samarkand",
                "Asia/Tashkent"
            ],
            "dial_code" => "+998"
        ],
        "MM" => [
            "name" => "Myanmar",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "MM",
                "alpha3" => "MMR"
            ],
            "numeric" => "104",
            "currencies" => [
                "MMK"
            ],
            "timezones" => [
                "Asia/Yangon"
            ],
            "dial_code" => "+95"
        ],
        "ML" => [
            "name" => "Mali",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "ML",
                "alpha3" => "MLI"
            ],
            "numeric" => "466",
            "currencies" => [
                "XOF"
            ],
            "timezones" => [
                "Africa/Bamako"
            ],
            "dial_code" => "+223"
        ],
        "MO" => [
            "name" => "Macao",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "MO",
                "alpha3" => "MAC"
            ],
            "numeric" => "446",
            "currencies" => [
                "MOP"
            ],
            "timezones" => [
                "Asia/Macau"
            ],
            "dial_code" => "+853"
        ],
        "MN" => [
            "name" => "Mongolia",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "MN",
                "alpha3" => "MNG"
            ],
            "numeric" => "496",
            "currencies" => [
                "MNT"
            ],
            "timezones" => [
                "Asia/Choibalsan",
                "Asia/Hovd",
                "Asia/Ulaanbaatar"
            ],
            "dial_code" => "+976"
        ],
        "MH" => [
            "name" => "Marshall Islands",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "MH",
                "alpha3" => "MHL"
            ],
            "numeric" => "584",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "Pacific/Kwajalein",
                "Pacific/Majuro"
            ],
            "dial_code" => "+692"
        ],
        "MK" => [
            "name" => "Macedonia",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "MK",
                "alpha3" => "MKD"
            ],
            "numeric" => "807",
            "currencies" => [
                "MKD"
            ],
            "timezones" => [
                "Europe/Skopje"
            ],
            "dial_code" => "+389"
        ],
        "MU" => [
            "name" => "Mauritius",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "MU",
                "alpha3" => "MUS"
            ],
            "numeric" => "480",
            "currencies" => [
                "MUR"
            ],
            "timezones" => [
                "Indian/Mauritius"
            ],
            "dial_code" => "+230"
        ],
        "MT" => [
            "name" => "Malta",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "MT",
                "alpha3" => "MLT"
            ],
            "numeric" => "470",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Malta"
            ],
            "dial_code" => "+356"
        ],
        "MW" => [
            "name" => "Malawi",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "MW",
                "alpha3" => "MWI"
            ],
            "numeric" => "454",
            "currencies" => [
                "MWK"
            ],
            "timezones" => [
                "Africa/Blantyre"
            ],
            "dial_code" => "+265"
        ],
        "MV" => [
            "name" => "Maldives",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "MV",
                "alpha3" => "MDV"
            ],
            "numeric" => "462",
            "currencies" => [
                "MVR"
            ],
            "timezones" => [
                "Indian/Maldives"
            ],
            "dial_code" => "+960"
        ],
        "MQ" => [
            "name" => "Martinique",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "MQ",
                "alpha3" => "MTQ"
            ],
            "numeric" => "474",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "America/Martinique"
            ],
            "dial_code" => "+596"
        ],
        "MP" => [
            "name" => "Northern Mariana Islands",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "MP",
                "alpha3" => "MNP"
            ],
            "numeric" => "580",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "Pacific/Saipan"
            ],
            "dial_code" => "+1670"
        ],
        "MS" => [
            "name" => "Montserrat",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "MS",
                "alpha3" => "MSR"
            ],
            "numeric" => "500",
            "currencies" => [
                "XCD"
            ],
            "timezones" => [
                "America/Montserrat"
            ],
            "dial_code" => "+1664"
        ],
        "MR" => [
            "name" => "Mauritania",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "MR",
                "alpha3" => "MRT"
            ],
            "numeric" => "478",
            "currencies" => [
                "MRO"
            ],
            "timezones" => [
                "Africa/Nouakchott"
            ],
            "dial_code" => "+222"
        ],
        "IM" => [
            "name" => "Isle of Man",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "IM",
                "alpha3" => "IMN"
            ],
            "numeric" => "833",
            "currencies" => [
                "GBP"
            ],
            "timezones" => [
                "Europe/Isle_of_Man"
            ],
            "dial_code" => "+44"
        ],
        "UG" => [
            "name" => "Uganda",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "UG",
                "alpha3" => "UGA"
            ],
            "numeric" => "800",
            "currencies" => [
                "UGX"
            ],
            "timezones" => [
                "Africa/Kampala"
            ],
            "dial_code" => "+256"
        ],
        "TZ" => [
            "name" => "Tanzania",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "TZ",
                "alpha3" => "TZA"
            ],
            "numeric" => "834",
            "currencies" => [
                "TZS"
            ],
            "timezones" => [
                "Africa/Dar_es_Salaam"
            ],
            "dial_code" => "+255"
        ],
        "MY" => [
            "name" => "Malaysia",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "MY",
                "alpha3" => "MYS"
            ],
            "numeric" => "458",
            "currencies" => [
                "MYR"
            ],
            "timezones" => [
                "Asia/Kuala_Lumpur",
                "Asia/Kuching"
            ],
            "dial_code" => "+60"
        ],
        "MX" => [
            "name" => "Mexico",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "MX",
                "alpha3" => "MEX"
            ],
            "numeric" => "484",
            "currencies" => [
                "MXN"
            ],
            "timezones" => [
                "America/Bahia_Banderas",
                "America/Cancun",
                "America/Chihuahua",
                "America/Hermosillo",
                "America/Matamoros",
                "America/Mazatlan",
                "America/Merida",
                "America/Mexico_City",
                "America/Monterrey",
                "America/Ojinaga",
                "America/Tijuana"
            ],
            "dial_code" => "+52"
        ],
        "IL" => [
            "name" => "Israel",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "IL",
                "alpha3" => "ISR"
            ],
            "numeric" => "376",
            "currencies" => [
                "ILS"
            ],
            "timezones" => [
                "Asia/Jerusalem"
            ],
            "dial_code" => "+972"
        ],
        "FR" => [
            "name" => "France",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "FR",
                "alpha3" => "FRA"
            ],
            "numeric" => "250",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Paris"
            ],
            "dial_code" => "+33"
        ],
        "IO" => [
            "name" => "British Indian Ocean Territory",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "IO",
                "alpha3" => "IOT"
            ],
            "numeric" => "086",
            "currencies" => [
                "GBP"
            ],
            "timezones" => [
                "Indian/Chagos"
            ],
            "dial_code" => "+246"
        ],
        "SH" => [
            "name" => "Saint Helena",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "SH",
                "alpha3" => "SHN"
            ],
            "numeric" => "654",
            "currencies" => [
                "SHP"
            ],
            "timezones" => [
                "Atlantic/St_Helena"
            ],
            "dial_code" => "+290"
        ],
        "FI" => [
            "name" => "Finland",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "FI",
                "alpha3" => "FIN"
            ],
            "numeric" => "246",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Helsinki"
            ],
            "dial_code" => "+358"
        ],
        "FJ" => [
            "name" => "Fiji",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "FJ",
                "alpha3" => "FJI"
            ],
            "numeric" => "242",
            "currencies" => [
                "FJD"
            ],
            "timezones" => [
                "Pacific/Fiji"
            ],
            "dial_code" => "+679"
        ],
        "FK" => [
            "name" => "Falkland Islands",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "FK",
                "alpha3" => "FLK"
            ],
            "numeric" => "238",
            "currencies" => [
                "FKP"
            ],
            "timezones" => [
                "Atlantic/Stanley"
            ],
            "dial_code" => "+500"
        ],
        "FM" => [
            "name" => "Micronesia",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "FM",
                "alpha3" => "FSM"
            ],
            "numeric" => "583",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "Pacific/Chuuk",
                "Pacific/Kosrae",
                "Pacific/Pohnpei"
            ],
            "dial_code" => "+691"
        ],
        "FO" => [
            "name" => "Faroe Islands",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "FO",
                "alpha3" => "FRO"
            ],
            "numeric" => "234",
            "currencies" => [
                "DKK"
            ],
            "timezones" => [
                "Atlantic/Faroe"
            ],
            "dial_code" => "+298"
        ],
        "NI" => [
            "name" => "Nicaragua",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "NI",
                "alpha3" => "NIC"
            ],
            "numeric" => "558",
            "currencies" => [
                "NIO"
            ],
            "timezones" => [
                "America/Managua"
            ],
            "dial_code" => "+505"
        ],
        "NL" => [
            "name" => "Netherlands",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "NL",
                "alpha3" => "NLD"
            ],
            "numeric" => "528",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Amsterdam"
            ],
            "dial_code" => "+31"
        ],
        "NO" => [
            "name" => "Norway",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "NO",
                "alpha3" => "NOR"
            ],
            "numeric" => "578",
            "currencies" => [
                "NOK"
            ],
            "timezones" => [
                "Europe/Oslo"
            ],
            "dial_code" => "+47"
        ],
        "NA" => [
            "name" => "Namibia",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "NA",
                "alpha3" => "NAM"
            ],
            "numeric" => "516",
            "currencies" => [
                "NAD",
                "ZAR"
            ],
            "timezones" => [
                "Africa/Windhoek"
            ],
            "dial_code" => "+264"
        ],
        "VU" => [
            "name" => "Vanuatu",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "VU",
                "alpha3" => "VUT"
            ],
            "numeric" => "548",
            "currencies" => [
                "VUV"
            ],
            "timezones" => [
                "Pacific/Efate"
            ],
            "dial_code" => "+678"
        ],
        "NC" => [
            "name" => "New Caledonia",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "NC",
                "alpha3" => "NCL"
            ],
            "numeric" => "540",
            "currencies" => [
                "XPF"
            ],
            "timezones" => [
                "Pacific/Noumea"
            ],
            "dial_code" => "+687"
        ],
        "NE" => [
            "name" => "Niger",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "NE",
                "alpha3" => "NER"
            ],
            "numeric" => "562",
            "currencies" => [
                "XOF"
            ],
            "timezones" => [
                "Africa/Niamey"
            ],
            "dial_code" => "+227"
        ],
        "NF" => [
            "name" => "Norfolk Island",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "NF",
                "alpha3" => "NFK"
            ],
            "numeric" => "574",
            "currencies" => [
                "AUD"
            ],
            "timezones" => [
                "Pacific/Norfolk"
            ],
            "dial_code" => "+672"
        ],
        "NG" => [
            "name" => "Nigeria",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "NG",
                "alpha3" => "NGA"
            ],
            "numeric" => "566",
            "currencies" => [
                "NGN"
            ],
            "timezones" => [
                "Africa/Lagos"
            ],
            "dial_code" => "+234"
        ],
        "NZ" => [
            "name" => "New Zealand",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "NZ",
                "alpha3" => "NZL"
            ],
            "numeric" => "554",
            "currencies" => [
                "NZD"
            ],
            "timezones" => [
                "Pacific/Auckland",
                "Pacific/Chatham"
            ],
            "dial_code" => "+64"
        ],
        "NP" => [
            "name" => "Nepal",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "NP",
                "alpha3" => "NPL"
            ],
            "numeric" => "524",
            "currencies" => [
                "NPR"
            ],
            "timezones" => [
                "Asia/Kathmandu"
            ],
            "dial_code" => "+977"
        ],
        "NR" => [
            "name" => "Nauru",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "NR",
                "alpha3" => "NRU"
            ],
            "numeric" => "520",
            "currencies" => [
                "AUD"
            ],
            "timezones" => [
                "Pacific/Nauru"
            ],
            "dial_code" => "+674"
        ],
        "NU" => [
            "name" => "Niue",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "NU",
                "alpha3" => "NIU"
            ],
            "numeric" => "570",
            "currencies" => [
                "NZD"
            ],
            "timezones" => [
                "Pacific/Niue"
            ],
            "dial_code" => "+683"
        ],
        "CK" => [
            "name" => "Cook Islands",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "CK",
                "alpha3" => "COK"
            ],
            "numeric" => "184",
            "currencies" => [
                "NZD"
            ],
            "timezones" => [
                "Pacific/Rarotonga"
            ],
            "dial_code" => "+682"
        ],
        "XK" => [
            "name" => "Kosovo",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "XK",
                "alpha3" => "RKS"
            ],
            "numeric" => "383",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Belgrade"
            ],
            "dial_code" => "+383"
        ],
        "CI" => [
            "name" => "Ivory Coast",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "CI",
                "alpha3" => "CIV"
            ],
            "numeric" => "384",
            "currencies" => [
                "XOF"
            ],
            "timezones" => [
                "Africa/Abidjan"
            ],
            "dial_code" => "+225"
        ],
        "CH" => [
            "name" => "Switzerland",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "CH",
                "alpha3" => "CHE"
            ],
            "numeric" => "756",
            "currencies" => [
                "CHF"
            ],
            "timezones" => [
                "Europe/Zurich"
            ],
            "dial_code" => "+41"
        ],
        "CO" => [
            "name" => "Colombia",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "CO",
                "alpha3" => "COL"
            ],
            "numeric" => "170",
            "currencies" => [
                "COP"
            ],
            "timezones" => [
                "America/Bogota"
            ],
            "dial_code" => "+57"
        ],
        "CN" => [
            "name" => "China",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "CN",
                "alpha3" => "CHN"
            ],
            "numeric" => "156",
            "currencies" => [
                "CNY"
            ],
            "timezones" => [
                "Asia/Shanghai",
                "Asia/Urumqi"
            ],
            "dial_code" => "+86"
        ],
        "CM" => [
            "name" => "Cameroon",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "CM",
                "alpha3" => "CMR"
            ],
            "numeric" => "120",
            "currencies" => [
                "XAF"
            ],
            "timezones" => [
                "Africa/Douala"
            ],
            "dial_code" => "+237"
        ],
        "CL" => [
            "name" => "Chile",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "CL",
                "alpha3" => "CHL"
            ],
            "numeric" => "152",
            "currencies" => [
                "CLP"
            ],
            "timezones" => [
                "America/Punta_Arenas",
                "America/Santiago",
                "Pacific/Easter"
            ],
            "dial_code" => "+56"
        ],
        "CC" => [
            "name" => "Cocos Islands",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "CC",
                "alpha3" => "CCK"
            ],
            "numeric" => "166",
            "currencies" => [
                "AUD"
            ],
            "timezones" => [
                "Indian/Cocos"
            ],
            "dial_code" => "+61"
        ],
        "CA" => [
            "name" => "Canada",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "CA",
                "alpha3" => "CAN"
            ],
            "numeric" => "124",
            "currencies" => [
                "CAD"
            ],
            "timezones" => [
                "America/Atikokan",
                "America/Blanc-Sablon",
                "America/Cambridge_Bay",
                "America/Creston",
                "America/Dawson",
                "America/Dawson_Creek",
                "America/Edmonton",
                "America/Fort_Nelson",
                "America/Glace_Bay",
                "America/Goose_Bay",
                "America/Halifax",
                "America/Inuvik",
                "America/Iqaluit",
                "America/Moncton",
                "America/Nipigon",
                "America/Pangnirtung",
                "America/Rainy_River",
                "America/Rankin_Inlet",
                "America/Regina",
                "America/Resolute",
                "America/St_Johns",
                "America/Swift_Current",
                "America/Thunder_Bay",
                "America/Toronto",
                "America/Vancouver",
                "America/Whitehorse",
                "America/Winnipeg",
                "America/Yellowknife"
            ],
            "dial_code" => "+1"
        ],
        "CG" => [
            "name" => "Republic of the Congo",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "CG",
                "alpha3" => "COG"
            ],
            "numeric" => "178",
            "currencies" => [
                "XAF"
            ],
            "timezones" => [
                "Africa/Brazzaville"
            ],
            "dial_code" => "+242"
        ],
        "CF" => [
            "name" => "Central African Republic",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "CF",
                "alpha3" => "CAF"
            ],
            "numeric" => "140",
            "currencies" => [
                "XAF"
            ],
            "timezones" => [
                "Africa/Bangui"
            ],
            "dial_code" => "+236"
        ],
        "CD" => [
            "name" => "Democratic Republic of the Congo",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "CD",
                "alpha3" => "COD"
            ],
            "numeric" => "180",
            "currencies" => [
                "CDF"
            ],
            "timezones" => [
                "Africa/Kinshasa",
                "Africa/Lubumbashi"
            ],
            "dial_code" => "+243"
        ],
        "CZ" => [
            "name" => "Czech Republic",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "CZ",
                "alpha3" => "CZE"
            ],
            "numeric" => "203",
            "currencies" => [
                "CZK"
            ],
            "timezones" => [
                "Europe/Prague"
            ],
            "dial_code" => "+420"
        ],
        "CY" => [
            "name" => "Cyprus",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "CY",
                "alpha3" => "CYP"
            ],
            "numeric" => "196",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Asia/Famagusta",
                "Asia/Nicosia"
            ],
            "dial_code" => "+357"
        ],
        "CX" => [
            "name" => "Christmas Island",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "CX",
                "alpha3" => "CXR"
            ],
            "numeric" => "162",
            "currencies" => [
                "AUD"
            ],
            "timezones" => [
                "Indian/Christmas"
            ],
            "dial_code" => "+61"
        ],
        "CR" => [
            "name" => "Costa Rica",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "CR",
                "alpha3" => "CRI"
            ],
            "numeric" => "188",
            "currencies" => [
                "CRC"
            ],
            "timezones" => [
                "America/Costa_Rica"
            ],
            "dial_code" => "+506"
        ],
        "CW" => [
            "name" => "Curacao",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "CW",
                "alpha3" => "CUW"
            ],
            "numeric" => "531",
            "currencies" => [
                "ANG"
            ],
            "timezones" => [
                "America/Curacao"
            ],
            "dial_code" => "+599"
        ],
        "CV" => [
            "name" => "Cape Verde",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "CV",
                "alpha3" => "CPV"
            ],
            "numeric" => "132",
            "currencies" => [
                "CVE"
            ],
            "timezones" => [
                "Atlantic/Cape_Verde"
            ],
            "dial_code" => "+238"
        ],
        "CU" => [
            "name" => "Cuba",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "CU",
                "alpha3" => "CUB"
            ],
            "numeric" => "192",
            "currencies" => [
                "CUC",
                "CUP"
            ],
            "timezones" => [
                "America/Havana"
            ],
            "dial_code" => "+53"
        ],
        "SZ" => [
            "name" => "Swaziland",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "SZ",
                "alpha3" => "SWZ"
            ],
            "numeric" => "748",
            "currencies" => [
                "SZL",
                "ZAR"
            ],
            "timezones" => [
                "Africa/Mbabane"
            ],
            "dial_code" => "+268"
        ],
        "SY" => [
            "name" => "Syria",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "SY",
                "alpha3" => "SYR"
            ],
            "numeric" => "760",
            "currencies" => [
                "SYP"
            ],
            "timezones" => [
                "Asia/Damascus"
            ],
            "dial_code" => "+963"
        ],
        "SX" => [
            "name" => "Sint Maarten",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "SX",
                "alpha3" => "SXM"
            ],
            "numeric" => "534",
            "currencies" => [
                "ANG"
            ],
            "timezones" => [
                "America/Lower_Princes"
            ],
            "dial_code" => "+721"
        ],
        "KG" => [
            "name" => "Kyrgyzstan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "KG",
                "alpha3" => "KGZ"
            ],
            "numeric" => "417",
            "currencies" => [
                "KGS"
            ],
            "timezones" => [
                "Asia/Bishkek"
            ],
            "dial_code" => "+996"
        ],
        "KE" => [
            "name" => "Kenya",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "KE",
                "alpha3" => "KEN"
            ],
            "numeric" => "404",
            "currencies" => [
                "KES"
            ],
            "timezones" => [
                "Africa/Nairobi"
            ],
            "dial_code" => "+254"
        ],
        "SS" => [
            "name" => "South Sudan",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "SS",
                "alpha3" => "SSD"
            ],
            "numeric" => "728",
            "currencies" => [
                "SSP"
            ],
            "timezones" => [
                "Africa/Juba"
            ],
            "dial_code" => "+211"
        ],
        "SR" => [
            "name" => "Suriname",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "SR",
                "alpha3" => "SUR"
            ],
            "numeric" => "740",
            "currencies" => [
                "SRD"
            ],
            "timezones" => [
                "America/Paramaribo"
            ],
            "dial_code" => "+597"
        ],
        "KI" => [
            "name" => "Kiribati",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "KI",
                "alpha3" => "KIR"
            ],
            "numeric" => "296",
            "currencies" => [
                "AUD"
            ],
            "timezones" => [
                "Pacific/Enderbury",
                "Pacific/Kiritimati",
                "Pacific/Tarawa"
            ],
            "dial_code" => "+686"
        ],
        "KH" => [
            "name" => "Cambodia",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "KH",
                "alpha3" => "KHM"
            ],
            "numeric" => "116",
            "currencies" => [
                "KHR"
            ],
            "timezones" => [
                "Asia/Phnom_Penh"
            ],
            "dial_code" => "+855"
        ],
        "KN" => [
            "name" => "Saint Kitts and Nevis",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "KN",
                "alpha3" => "KNA"
            ],
            "numeric" => "659",
            "currencies" => [
                "XCD"
            ],
            "timezones" => [
                "America/St_Kitts"
            ],
            "dial_code" => "+1869"
        ],
        "KM" => [
            "name" => "Comoros",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "KM",
                "alpha3" => "COM"
            ],
            "numeric" => "174",
            "currencies" => [
                "KMF"
            ],
            "timezones" => [
                "Indian/Comoro"
            ],
            "dial_code" => "+269"
        ],
        "ST" => [
            "name" => "Sao Tome and Principe",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "ST",
                "alpha3" => "STP"
            ],
            "numeric" => "678",
            "currencies" => [
                "STD"
            ],
            "timezones" => [
                "Africa/Sao_Tome"
            ],
            "dial_code" => "+239"
        ],
        "SK" => [
            "name" => "Slovakia",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "SK",
                "alpha3" => "SVK"
            ],
            "numeric" => "703",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Bratislava"
            ],
            "dial_code" => "+421"
        ],
        "KR" => [
            "name" => "South Korea",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "KR",
                "alpha3" => "KOR"
            ],
            "numeric" => "410",
            "currencies" => [
                "KRW"
            ],
            "timezones" => [
                "Asia/Seoul"
            ],
            "dial_code" => "+82"
        ],
        "SI" => [
            "name" => "Slovenia",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "SI",
                "alpha3" => "SVN"
            ],
            "numeric" => "705",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Ljubljana"
            ],
            "dial_code" => "+386"
        ],
        "KP" => [
            "name" => "North Korea",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "KP",
                "alpha3" => "PRK"
            ],
            "numeric" => "408",
            "currencies" => [
                "KPW"
            ],
            "timezones" => [
                "Asia/Pyongyang"
            ],
            "dial_code" => "+850"
        ],
        "KW" => [
            "name" => "Kuwait",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "KW",
                "alpha3" => "KWT"
            ],
            "numeric" => "414",
            "currencies" => [
                "KWD"
            ],
            "timezones" => [
                "Asia/Kuwait"
            ],
            "dial_code" => "+965"
        ],
        "SN" => [
            "name" => "Senegal",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "SN",
                "alpha3" => "SEN"
            ],
            "numeric" => "686",
            "currencies" => [
                "XOF"
            ],
            "timezones" => [
                "Africa/Dakar"
            ],
            "dial_code" => "+221"
        ],
        "SM" => [
            "name" => "San Marino",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "SM",
                "alpha3" => "SMR"
            ],
            "numeric" => "674",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/San_Marino"
            ],
            "dial_code" => "+378"
        ],
        "SL" => [
            "name" => "Sierra Leone",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "SL",
                "alpha3" => "SLE"
            ],
            "numeric" => "694",
            "currencies" => [
                "SLL"
            ],
            "timezones" => [
                "Africa/Freetown"
            ],
            "dial_code" => "+232"
        ],
        "SC" => [
            "name" => "Seychelles",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "SC",
                "alpha3" => "SYC"
            ],
            "numeric" => "690",
            "currencies" => [
                "SCR"
            ],
            "timezones" => [
                "Indian/Mahe"
            ],
            "dial_code" => "+248"
        ],
        "KZ" => [
            "name" => "Kazakhstan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "KZ",
                "alpha3" => "KAZ"
            ],
            "numeric" => "398",
            "currencies" => [
                "KZT"
            ],
            "timezones" => [
                "Asia/Almaty",
                "Asia/Aqtau",
                "Asia/Aqtobe",
                "Asia/Atyrau",
                "Asia/Oral",
                "Asia/Qostanay",
                "Asia/Qyzylorda"
            ],
            "dial_code" => "+77"
        ],
        "KY" => [
            "name" => "Cayman Islands",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "KY",
                "alpha3" => "CYM"
            ],
            "numeric" => "136",
            "currencies" => [
                "KYD"
            ],
            "timezones" => [
                "America/Cayman"
            ],
            "dial_code" => "+ 345"
        ],
        "SG" => [
            "name" => "Singapore",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "SG",
                "alpha3" => "SGP"
            ],
            "numeric" => "702",
            "currencies" => [
                "SGD"
            ],
            "timezones" => [
                "Asia/Singapore"
            ],
            "dial_code" => "+65"
        ],
        "SE" => [
            "name" => "Sweden",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "SE",
                "alpha3" => "SWE"
            ],
            "numeric" => "752",
            "currencies" => [
                "SEK"
            ],
            "timezones" => [
                "Europe/Stockholm"
            ],
            "dial_code" => "+46"
        ],
        "SD" => [
            "name" => "Sudan",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "SD",
                "alpha3" => "SDN"
            ],
            "numeric" => "729",
            "currencies" => [
                "SDG"
            ],
            "timezones" => [
                "Africa/Khartoum"
            ],
            "dial_code" => "+249"
        ],
        "DO" => [
            "name" => "Dominican Republic",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "DO",
                "alpha3" => "DOM"
            ],
            "numeric" => "214",
            "currencies" => [
                "DOP"
            ],
            "timezones" => [
                "America/Santo_Domingo"
            ],
            "dial_code" => "+1849"
        ],
        "DM" => [
            "name" => "Dominica",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "DM",
                "alpha3" => "DMA"
            ],
            "numeric" => "212",
            "currencies" => [
                "XCD"
            ],
            "timezones" => [
                "America/Dominica"
            ],
            "dial_code" => "+1767"
        ],
        "DJ" => [
            "name" => "Djibouti",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "DJ",
                "alpha3" => "DJI"
            ],
            "numeric" => "262",
            "currencies" => [
                "DJF"
            ],
            "timezones" => [
                "Africa/Djibouti"
            ],
            "dial_code" => "+253"
        ],
        "DK" => [
            "name" => "Denmark",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "DK",
                "alpha3" => "DNK"
            ],
            "numeric" => "208",
            "currencies" => [
                "DKK"
            ],
            "timezones" => [
                "Europe/Copenhagen"
            ],
            "dial_code" => "+45"
        ],
        "VG" => [
            "name" => "British Virgin Islands",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "VG",
                "alpha3" => "VGB"
            ],
            "numeric" => "092",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "America/Tortola"
            ],
            "dial_code" => "+1284"
        ],
        "DE" => [
            "name" => "Germany",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "DE",
                "alpha3" => "DEU"
            ],
            "numeric" => "276",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Berlin",
                "Europe/Busingen"
            ],
            "dial_code" => "+49"
        ],
        "YE" => [
            "name" => "Yemen",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "YE",
                "alpha3" => "YEM"
            ],
            "numeric" => "887",
            "currencies" => [
                "YER"
            ],
            "timezones" => [
                "Asia/Aden"
            ],
            "dial_code" => "+967"
        ],
        "DZ" => [
            "name" => "Algeria",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "DZ",
                "alpha3" => "DZA"
            ],
            "numeric" => "012",
            "currencies" => [
                "DZD"
            ],
            "timezones" => [
                "Africa/Algiers"
            ],
            "dial_code" => "+213"
        ],
        "US" => [
            "name" => "United States",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "US",
                "alpha3" => "USA"
            ],
            "numeric" => "840",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "America/Adak",
                "America/Anchorage",
                "America/Boise",
                "America/Chicago",
                "America/Denver",
                "America/Detroit",
                "America/Indiana/Indianapolis",
                "America/Indiana/Knox",
                "America/Indiana/Marengo",
                "America/Indiana/Petersburg",
                "America/Indiana/Tell_City",
                "America/Indiana/Vevay",
                "America/Indiana/Vincennes",
                "America/Indiana/Winamac",
                "America/Juneau",
                "America/Kentucky/Louisville",
                "America/Kentucky/Monticello",
                "America/Los_Angeles",
                "America/Menominee",
                "America/Metlakatla",
                "America/New_York",
                "America/Nome",
                "America/North_Dakota/Beulah",
                "America/North_Dakota/Center",
                "America/North_Dakota/New_Salem",
                "America/Phoenix",
                "America/Sitka",
                "America/Yakutat",
                "Pacific/Honolulu"
            ],
            "dial_code" => "+1"
        ],
        "UY" => [
            "name" => "Uruguay",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "UY",
                "alpha3" => "URY"
            ],
            "numeric" => "858",
            "currencies" => [
                "UYU"
            ],
            "timezones" => [
                "America/Montevideo"
            ],
            "dial_code" => "+598"
        ],
        "YT" => [
            "name" => "Mayotte",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "YT",
                "alpha3" => "MYT"
            ],
            "numeric" => "175",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Indian/Mayotte"
            ],
            "dial_code" => "+262"
        ],
        "UM" => [
            "name" => "United States Minor Outlying Islands",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "UM",
                "alpha3" => "UMI"
            ],
            "numeric" => "581",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "Pacific/Midway",
                "Pacific/Wake"
            ],
            "dial_code" => "+1"
        ],
        "LB" => [
            "name" => "Lebanon",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "LB",
                "alpha3" => "LBN"
            ],
            "numeric" => "422",
            "currencies" => [
                "LBP"
            ],
            "timezones" => [
                "Asia/Beirut"
            ],
            "dial_code" => "+961"
        ],
        "LC" => [
            "name" => "Saint Lucia",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "LC",
                "alpha3" => "LCA"
            ],
            "numeric" => "662",
            "currencies" => [
                "XCD"
            ],
            "timezones" => [
                "America/St_Lucia"
            ],
            "dial_code" => "+1758"
        ],
        "LA" => [
            "name" => "Laos",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "LA",
                "alpha3" => "LAO"
            ],
            "numeric" => "418",
            "currencies" => [
                "LAK"
            ],
            "timezones" => [
                "Asia/Vientiane"
            ],
            "dial_code" => "+856"
        ],
        "TV" => [
            "name" => "Tuvalu",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "TV",
                "alpha3" => "TUV"
            ],
            "numeric" => "798",
            "currencies" => [
                "AUD"
            ],
            "timezones" => [
                "Pacific/Funafuti"
            ],
            "dial_code" => "+688"
        ],
        "TW" => [
            "name" => "Taiwan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "TW",
                "alpha3" => "TWN"
            ],
            "numeric" => "158",
            "currencies" => [
                "TWD"
            ],
            "timezones" => [
                "Asia/Taipei"
            ],
            "dial_code" => "+886"
        ],
        "TT" => [
            "name" => "Trinidad and Tobago",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "TT",
                "alpha3" => "TTO"
            ],
            "numeric" => "780",
            "currencies" => [
                "TTD"
            ],
            "timezones" => [
                "America/Port_of_Spain"
            ],
            "dial_code" => "+1868"
        ],
        "TR" => [
            "name" => "Turkey",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "TR",
                "alpha3" => "TUR"
            ],
            "numeric" => "792",
            "currencies" => [
                "TRY"
            ],
            "timezones" => [
                "Europe/Istanbul"
            ],
            "dial_code" => "+90"
        ],
        "LK" => [
            "name" => "Sri Lanka",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "LK",
                "alpha3" => "LKA"
            ],
            "numeric" => "144",
            "currencies" => [
                "LKR"
            ],
            "timezones" => [
                "Asia/Colombo"
            ],
            "dial_code" => "+94"
        ],
        "LI" => [
            "name" => "Liechtenstein",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "LI",
                "alpha3" => "LIE"
            ],
            "numeric" => "438",
            "currencies" => [
                "CHF"
            ],
            "timezones" => [
                "Europe/Vaduz"
            ],
            "dial_code" => "+423"
        ],
        "LV" => [
            "name" => "Latvia",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "LV",
                "alpha3" => "LVA"
            ],
            "numeric" => "428",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Riga"
            ],
            "dial_code" => "+371"
        ],
        "TO" => [
            "name" => "Tonga",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "TO",
                "alpha3" => "TON"
            ],
            "numeric" => "776",
            "currencies" => [
                "TOP"
            ],
            "timezones" => [
                "Pacific/Tongatapu"
            ],
            "dial_code" => "+676"
        ],
        "LT" => [
            "name" => "Lithuania",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "LT",
                "alpha3" => "LTU"
            ],
            "numeric" => "440",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Vilnius"
            ],
            "dial_code" => "+370"
        ],
        "LU" => [
            "name" => "Luxembourg",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "LU",
                "alpha3" => "LUX"
            ],
            "numeric" => "442",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Luxembourg"
            ],
            "dial_code" => "+352"
        ],
        "LR" => [
            "name" => "Liberia",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "LR",
                "alpha3" => "LBR"
            ],
            "numeric" => "430",
            "currencies" => [
                "LRD"
            ],
            "timezones" => [
                "Africa/Monrovia"
            ],
            "dial_code" => "+231"
        ],
        "LS" => [
            "name" => "Lesotho",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "LS",
                "alpha3" => "LSO"
            ],
            "numeric" => "426",
            "currencies" => [
                "LSL",
                "ZAR"
            ],
            "timezones" => [
                "Africa/Maseru"
            ],
            "dial_code" => "+266"
        ],
        "TH" => [
            "name" => "Thailand",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "TH",
                "alpha3" => "THA"
            ],
            "numeric" => "764",
            "currencies" => [
                "THB"
            ],
            "timezones" => [
                "Asia/Bangkok"
            ],
            "dial_code" => "+66"
        ],
        "TF" => [
            "name" => "French Southern Territories",
            "continent" => [
                "code" => "AN",
                "name" => "Antarctica"
            ],
            "code" => [
                "alpha2" => "TF",
                "alpha3" => "ATF"
            ],
            "numeric" => "260",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Indian/Kerguelen"
            ],
            "dial_code" => "+262"
        ],
        "TG" => [
            "name" => "Togo",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "TG",
                "alpha3" => "TGO"
            ],
            "numeric" => "768",
            "currencies" => [
                "XOF"
            ],
            "timezones" => [
                "Africa/Lome"
            ],
            "dial_code" => "+228"
        ],
        "TD" => [
            "name" => "Chad",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "TD",
                "alpha3" => "TCD"
            ],
            "numeric" => "148",
            "currencies" => [
                "XAF"
            ],
            "timezones" => [
                "Africa/Ndjamena"
            ],
            "dial_code" => "+235"
        ],
        "TC" => [
            "name" => "Turks and Caicos Islands",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "TC",
                "alpha3" => "TCA"
            ],
            "numeric" => "796",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "America/Grand_Turk"
            ],
            "dial_code" => "+1649"
        ],
        "LY" => [
            "name" => "Libya",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "LY",
                "alpha3" => "LBY"
            ],
            "numeric" => "434",
            "currencies" => [
                "LYD"
            ],
            "timezones" => [
                "Africa/Tripoli"
            ],
            "dial_code" => "+218"
        ],
        "VA" => [
            "name" => "Vatican",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "VA",
                "alpha3" => "VAT"
            ],
            "numeric" => "336",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Vatican"
            ],
            "dial_code" => "+379"
        ],
        "VC" => [
            "name" => "Saint Vincent and the Grenadines",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "VC",
                "alpha3" => "VCT"
            ],
            "numeric" => "670",
            "currencies" => [
                "XCD"
            ],
            "timezones" => [
                "America/St_Vincent"
            ],
            "dial_code" => "+1784"
        ],
        "AE" => [
            "name" => "United Arab Emirates",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "AE",
                "alpha3" => "ARE"
            ],
            "numeric" => "784",
            "currencies" => [
                "AED"
            ],
            "timezones" => [
                "Asia/Dubai"
            ],
            "dial_code" => "+971"
        ],
        "AD" => [
            "name" => "Andorra",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "AD",
                "alpha3" => "AND"
            ],
            "numeric" => "020",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Andorra"
            ],
            "dial_code" => "+376"
        ],
        "AG" => [
            "name" => "Antigua and Barbuda",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "AG",
                "alpha3" => "ATG"
            ],
            "numeric" => "028",
            "currencies" => [
                "XCD"
            ],
            "timezones" => [
                "America/Antigua"
            ],
            "dial_code" => "+1268"
        ],
        "AF" => [
            "name" => "Afghanistan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "AF",
                "alpha3" => "AFG"
            ],
            "numeric" => "004",
            "currencies" => [
                "AFN"
            ],
            "timezones" => [
                "Asia/Kabul"
            ],
            "dial_code" => "+93"
        ],
        "AI" => [
            "name" => "Anguilla",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "AI",
                "alpha3" => "AIA"
            ],
            "numeric" => "660",
            "currencies" => [
                "XCD"
            ],
            "timezones" => [
                "America/Anguilla"
            ],
            "dial_code" => "+1264"
        ],
        "VI" => [
            "name" => "U.S. Virgin Islands",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "VI",
                "alpha3" => "VIR"
            ],
            "numeric" => "850",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "America/St_Thomas"
            ],
            "dial_code" => "+1340"
        ],
        "IS" => [
            "name" => "Iceland",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "IS",
                "alpha3" => "ISL"
            ],
            "numeric" => "352",
            "currencies" => [
                "ISK"
            ],
            "timezones" => [
                "Atlantic/Reykjavik"
            ],
            "dial_code" => "+354"
        ],
        "IR" => [
            "name" => "Iran",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "IR",
                "alpha3" => "IRN"
            ],
            "numeric" => "364",
            "currencies" => [
                "IRR"
            ],
            "timezones" => [
                "Asia/Tehran"
            ],
            "dial_code" => "+98"
        ],
        "AM" => [
            "name" => "Armenia",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "AM",
                "alpha3" => "ARM"
            ],
            "numeric" => "051",
            "currencies" => [
                "AMD"
            ],
            "timezones" => [
                "Asia/Yerevan"
            ],
            "dial_code" => "+374"
        ],
        "AL" => [
            "name" => "Albania",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "AL",
                "alpha3" => "ALB"
            ],
            "numeric" => "008",
            "currencies" => [
                "ALL"
            ],
            "timezones" => [
                "Europe/Tirane"
            ],
            "dial_code" => "+355"
        ],
        "AO" => [
            "name" => "Angola",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "AO",
                "alpha3" => "AGO"
            ],
            "numeric" => "024",
            "currencies" => [
                "AOA"
            ],
            "timezones" => [
                "Africa/Luanda"
            ],
            "dial_code" => "+244"
        ],
        "AQ" => [
            "name" => "Antarctica",
            "continent" => [
                "code" => "AN",
                "name" => "Antarctica"
            ],
            "code" => [
                "alpha2" => "AQ",
                "alpha3" => "ATA"
            ],
            "numeric" => "010",
            "currencies" => [
                "ARS",
                "AUD",
                "BGN",
                "BRL",
                "BYR",
                "CLP",
                "CNY",
                "CZK",
                "EUR",
                "GBP",
                "INR",
                "JPY",
                "KRW",
                "NOK",
                "NZD",
                "PEN",
                "PKR",
                "PLN",
                "RON",
                "RUB",
                "SEK",
                "UAH",
                "USD",
                "UYU",
                "ZAR"
            ],
            "timezones" => [
                "Antarctica/Casey",
                "Antarctica/Davis",
                "Antarctica/DumontDUrville",
                "Antarctica/Mawson",
                "Antarctica/McMurdo",
                "Antarctica/Palmer",
                "Antarctica/Rothera",
                "Antarctica/Syowa",
                "Antarctica/Troll",
                "Antarctica/Vostok"
            ],
            "dial_code" => "+672"
        ],
        "AS" => [
            "name" => "American Samoa",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "AS",
                "alpha3" => "ASM"
            ],
            "numeric" => "016",
            "currencies" => [
                "USD"
            ],
            "timezones" => [
                "Pacific/Pago_Pago"
            ],
            "dial_code" => "+1684"
        ],
        "AR" => [
            "name" => "Argentina",
            "continent" => [
                "code" => "SA",
                "name" => "South america"
            ],
            "code" => [
                "alpha2" => "AR",
                "alpha3" => "ARG"
            ],
            "numeric" => "032",
            "currencies" => [
                "ARS"
            ],
            "timezones" => [
                "America/Argentina/Buenos_Aires",
                "America/Argentina/Catamarca",
                "America/Argentina/Cordoba",
                "America/Argentina/Jujuy",
                "America/Argentina/La_Rioja",
                "America/Argentina/Mendoza",
                "America/Argentina/Rio_Gallegos",
                "America/Argentina/Salta",
                "America/Argentina/San_Juan",
                "America/Argentina/San_Luis",
                "America/Argentina/Tucuman",
                "America/Argentina/Ushuaia"
            ],
            "dial_code" => "+54"
        ],
        "AU" => [
            "name" => "Australia",
            "continent" => [
                "code" => "OC",
                "name" => "Oceania"
            ],
            "code" => [
                "alpha2" => "AU",
                "alpha3" => "AUS"
            ],
            "numeric" => "036",
            "currencies" => [
                "AUD"
            ],
            "timezones" => [
                "Antarctica/Macquarie",
                "Australia/Adelaide",
                "Australia/Brisbane",
                "Australia/Broken_Hill",
                "Australia/Currie",
                "Australia/Darwin",
                "Australia/Eucla",
                "Australia/Hobart",
                "Australia/Lindeman",
                "Australia/Lord_Howe",
                "Australia/Melbourne",
                "Australia/Perth",
                "Australia/Sydney"
            ],
            "dial_code" => "+61"
        ],
        "AT" => [
            "name" => "Austria",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "AT",
                "alpha3" => "AUT"
            ],
            "numeric" => "040",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Vienna"
            ],
            "dial_code" => "+43"
        ],
        "AW" => [
            "name" => "Aruba",
            "continent" => [
                "code" => "NA",
                "name" => "North america"
            ],
            "code" => [
                "alpha2" => "AW",
                "alpha3" => "ABW"
            ],
            "numeric" => "533",
            "currencies" => [
                "AWG"
            ],
            "timezones" => [
                "America/Aruba"
            ],
            "dial_code" => "+297"
        ],
        "IN" => [
            "name" => "India",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "IN",
                "alpha3" => "IND"
            ],
            "numeric" => "356",
            "currencies" => [
                "INR"
            ],
            "timezones" => [
                "Asia/Kolkata"
            ],
            "dial_code" => "+91"
        ],
        "AX" => [
            "name" => "Aland Islands",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "AX",
                "alpha3" => "ALA"
            ],
            "numeric" => "248",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Mariehamn"
            ],
            "dial_code" => "+358"
        ],
        "AZ" => [
            "name" => "Azerbaijan",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "AZ",
                "alpha3" => "AZE"
            ],
            "numeric" => "031",
            "currencies" => [
                "AZN"
            ],
            "timezones" => [
                "Asia/Baku"
            ],
            "dial_code" => "+994"
        ],
        "IE" => [
            "name" => "Ireland",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "IE",
                "alpha3" => "IRL"
            ],
            "numeric" => "372",
            "currencies" => [
                "EUR"
            ],
            "timezones" => [
                "Europe/Dublin"
            ],
            "dial_code" => "+353"
        ],
        "ID" => [
            "name" => "Indonesia",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "ID",
                "alpha3" => "IDN"
            ],
            "numeric" => "360",
            "currencies" => [
                "IDR"
            ],
            "timezones" => [
                "Asia/Jakarta",
                "Asia/Jayapura",
                "Asia/Makassar",
                "Asia/Pontianak"
            ],
            "dial_code" => "+62"
        ],
        "UA" => [
            "name" => "Ukraine",
            "continent" => [
                "code" => "EU",
                "name" => "Europe"
            ],
            "code" => [
                "alpha2" => "UA",
                "alpha3" => "UKR"
            ],
            "numeric" => "804",
            "currencies" => [
                "UAH"
            ],
            "timezones" => [
                "Europe/Kiev",
                "Europe/Simferopol",
                "Europe/Uzhgorod",
                "Europe/Zaporozhye"
            ],
            "dial_code" => "+380"
        ],
        "QA" => [
            "name" => "Qatar",
            "continent" => [
                "code" => "AS",
                "name" => "Asia"
            ],
            "code" => [
                "alpha2" => "QA",
                "alpha3" => "QAT"
            ],
            "numeric" => "634",
            "currencies" => [
                "QAR"
            ],
            "timezones" => [
                "Asia/Qatar"
            ],
            "dial_code" => "+974"
        ],
        "MZ" => [
            "name" => "Mozambique",
            "continent" => [
                "code" => "AF",
                "name" => "Africa"
            ],
            "code" => [
                "alpha2" => "MZ",
                "alpha3" => "MOZ"
            ],
            "numeric" => "508",
            "currencies" => [
                "MZN"
            ],
            "timezones" => [
                "Africa/Maputo"
            ],
            "dial_code" => "+258"
        ]
    ];

    protected static bool $cache_recorded = false;

    /**
     * @var array<string, Country>
     */
    protected static array $countryRecords = [];

    /**
     * @var array<string, string>
     */
    protected static array $countryAlpha3Records = [];

    /**
     * @var array<string, array<string, string>
     */
    protected static array $countriesPhoneId = [];

    private static function buildCacheCode(): void
    {
        if (!self::$cache_recorded) {
            self::$cache_recorded = true;
            foreach (self::LISTS as $code => $country) {
                self::$countryAlpha3Records[$country['code']['alpha3']] = $code;
                self::$countriesPhoneId[$country['dial_code']][] = $code;
            }
        }
    }

    /**
     * @param string|int $code
     * @return ?array<string, Country>
     */
    public static function findByPhone(string|int $code): ?array
    {
        if (is_string($code)) {
            if (str_starts_with($code, '+')) {
                $code = substr($code, 1);
            }
            if (!is_numeric($code)) {
                return null;
            }
            if (str_starts_with($code, '0')) {
                $code = ltrim($code, '0');
            }
            if ($code === '') {
                return null;
            }
        } elseif ($code < 1) {
            return null;
        }
        $code ="+$code";
        if (!self::$cache_recorded) {
            self::buildCacheCode();
        }

        if (!($codes = self::$countriesPhoneId[$code]??null)) {
            return null;
        }

        $result = [];
        foreach ($codes as $code) {
            self::$countryRecords[$code] ??= new Country($code);
            $result[$code] = self::$countryRecords[$code];
        }
        return $result;
    }

    public static function findByCode(string $code): ?Country
    {
        $code = strtoupper($code);
        if (strlen($code) > 3) {
            return null;
        }

        if (!self::$cache_recorded) {
            self::buildCacheCode();
        }

        $code = strlen($code) === 2 ? $code : self::$countryAlpha3Records[$code]??null;
        if ($code === null || !isset(self::LISTS[$code])) {
            return null;
        }

        self::$countryRecords[$code] ??= new Country($code);
        return self::$countryRecords[$code];
    }
}
