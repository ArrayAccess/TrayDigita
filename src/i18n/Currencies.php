<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\i18n;

use ArrayAccess\TrayDigita\i18n\Records\Currency;
use function strtoupper;
use function trim;

class Currencies
{
    public const LIST = [
        "BDT" => [
            "code" => "BDT",
            "name" => "Bangladeshi Taka",
            "symbol" => "\u09f3"
        ],
        "EUR" => [
            "code" => "EUR",
            "name" => "Euro",
            "symbol" => "\u20ac"
        ],
        "XOF" => [
            "code" => "XOF",
            "name" => "CFA Franc BCEAO",
            "symbol" => "CFA"
        ],
        "BGN" => [
            "code" => "BGN",
            "name" => "Bulgarian L\u0103v",
            "symbol" => "\u041b\u0432."
        ],
        "BAM" => [
            "code" => "BAM",
            "name" => "Bosnia-Herzegovina Convertible Marka",
            "symbol" => "KM"
        ],
        "BBD" => [
            "code" => "BBD",
            "name" => "Barbados Dollar",
            "symbol" => "Bds$"
        ],
        "XPF" => [
            "code" => "XPF",
            "name" => "CFP Franc",
            "symbol" => "\u20a3"
        ],
        "BMD" => [
            "code" => "BMD",
            "name" => "Bermudian Dollar",
            "symbol" => "$"
        ],
        "BND" => [
            "code" => "BND",
            "name" => "Brunei Dollar",
            "symbol" => "B$"
        ],
        "SGD" => [
            "code" => "SGD",
            "name" => "Singapore Dollar",
            "symbol" => "$"
        ],
        "BOB" => [
            "code" => "BOB",
            "name" => "Boliviano",
            "symbol" => "Bs"
        ],
        "BHD" => [
            "code" => "BHD",
            "name" => "Bahraini Dinar",
            "symbol" => "\u062f.\u0628"
        ],
        "BIF" => [
            "code" => "BIF",
            "name" => "Burundian Franc",
            "symbol" => "FBu"
        ],
        "BTN" => [
            "code" => "BTN",
            "name" => "Bhutanese Ngultrum",
            "symbol" => "Nu."
        ],
        "JMD" => [
            "code" => "JMD",
            "name" => "Jamaican Dollar",
            "symbol" => "$"
        ],
        "NOK" => [
            "code" => "NOK",
            "name" => "Norwegian Krone",
            "symbol" => "kr"
        ],
        "BWP" => [
            "code" => "BWP",
            "name" => "Botswana Pula",
            "symbol" => "P"
        ],
        "WST" => [
            "code" => "WST",
            "name" => "Samoan T\u0101l\u0101",
            "symbol" => "SAT"
        ],
        "USD" => [
            "code" => "USD",
            "name" => "US Dollar",
            "symbol" => "$"
        ],
        "BRL" => [
            "code" => "BRL",
            "name" => "Brazilian Real",
            "symbol" => "R$"
        ],
        "BSD" => [
            "code" => "BSD",
            "name" => "Bahamian Dollar",
            "symbol" => "$"
        ],
        "GBP" => [
            "code" => "GBP",
            "name" => "Pound Sterling",
            "symbol" => "\u00a3"
        ],
        "BYN" => [
            "code" => "BYN",
            "name" => "Belarussian Ruble",
            "symbol" => "BYN"
        ],
        "BZD" => [
            "code" => "BZD",
            "name" => "Belize Dollar",
            "symbol" => "$"
        ],
        "RUB" => [
            "code" => "RUB",
            "name" => "Russian Ruble",
            "symbol" => "\u20bd"
        ],
        "RWF" => [
            "code" => "RWF",
            "name" => "Rwandan Franc",
            "symbol" => "R\u20a3"
        ],
        "RSD" => [
            "code" => "RSD",
            "name" => "Serbian Dinar",
            "symbol" => "din"
        ],
        "TMT" => [
            "code" => "TMT",
            "name" => "Turkmenistani Manat",
            "symbol" => "T"
        ],
        "TJS" => [
            "code" => "TJS",
            "name" => "Tajikistani Somoni",
            "symbol" => "\u0405M"
        ],
        "RON" => [
            "code" => "RON",
            "name" => "Romanian Leu",
            "symbol" => "lei"
        ],
        "NZD" => [
            "code" => "NZD",
            "name" => "New Zealand Dollar",
            "symbol" => "$"
        ],
        "GTQ" => [
            "code" => "GTQ",
            "name" => "Guatemalan Quetzal",
            "symbol" => "Q"
        ],
        "XAF" => [
            "code" => "XAF",
            "name" => "CFA Franc BEAC",
            "symbol" => "FCFA"
        ],
        "JPY" => [
            "code" => "JPY",
            "name" => "Japanese Yen",
            "symbol" => "\u00a5"
        ],
        "GYD" => [
            "code" => "GYD",
            "name" => "Guyanese Dollar",
            "symbol" => "G$"
        ],
        "GEL" => [
            "code" => "GEL",
            "name" => "Georgian Lari",
            "symbol" => "\u10da"
        ],
        "XCD" => [
            "code" => "XCD",
            "name" => "East Caribbean Dollar",
            "symbol" => "$"
        ],
        "GNF" => [
            "code" => "GNF",
            "name" => "Guinean Franc",
            "symbol" => "FG"
        ],
        "GMD" => [
            "code" => "GMD",
            "name" => "Gambian Dalasi",
            "symbol" => "D"
        ],
        "DKK" => [
            "code" => "DKK",
            "name" => "Danish Krone",
            "symbol" => "Kr."
        ],
        "GIP" => [
            "code" => "GIP",
            "name" => "Gibraltar Pound",
            "symbol" => "\u00a3"
        ],
        "GHS" => [
            "code" => "GHS",
            "name" => "Ghanaian Cedi",
            "symbol" => "GH\u20b5"
        ],
        "OMR" => [
            "code" => "OMR",
            "name" => "Omani Rial",
            "symbol" => "\u0631.\u0639."
        ],
        "TND" => [
            "code" => "TND",
            "name" => "Tunisian Dinar",
            "symbol" => "\u062f.\u062a"
        ],
        "JOD" => [
            "code" => "JOD",
            "name" => "Jordanian Dinar",
            "symbol" => "\u062f.\u0627"
        ],
        "HRK" => [
            "code" => "HRK",
            "name" => "Croatian Kuna",
            "symbol" => "kn"
        ],
        "HTG" => [
            "code" => "HTG",
            "name" => "Haitian Gourde",
            "symbol" => "G"
        ],
        "HUF" => [
            "code" => "HUF",
            "name" => "Hungarian Forint",
            "symbol" => "Ft"
        ],
        "HKD" => [
            "code" => "HKD",
            "name" => "Hong Kong Dollar",
            "symbol" => "HK$"
        ],
        "HNL" => [
            "code" => "HNL",
            "name" => "Honduran Lempira",
            "symbol" => "L"
        ],
        "AUD" => [
            "code" => "AUD",
            "name" => "Australian Dollar",
            "symbol" => "A$"
        ],
        "VEF" => [
            "code" => "VEF",
            "name" => "Venezuelan Bolivar",
            "symbol" => "VEF"
        ],
        "ILS" => [
            "code" => "ILS",
            "name" => "Israeli New Sheqel",
            "symbol" => "\u20aa"
        ],
        "PYG" => [
            "code" => "PYG",
            "name" => "Paraguayan Guarani",
            "symbol" => "\u20b2"
        ],
        "IQD" => [
            "code" => "IQD",
            "name" => "Iraqi Dinar",
            "symbol" => "\u0639.\u062f"
        ],
        "PAB" => [
            "code" => "PAB",
            "name" => "Panamanian Balboa",
            "symbol" => "B\/."
        ],
        "PGK" => [
            "code" => "PGK",
            "name" => "Papua New Guinean Kina",
            "symbol" => "K"
        ],
        "PEN" => [
            "code" => "PEN",
            "name" => "Peruvian Nuevo Sol",
            "symbol" => "S\/"
        ],
        "PKR" => [
            "code" => "PKR",
            "name" => "Pakistani Rupee",
            "symbol" => "\u20a8"
        ],
        "PHP" => [
            "code" => "PHP",
            "name" => "Philippine Peso",
            "symbol" => "\u20b1"
        ],
        "PLN" => [
            "code" => "PLN",
            "name" => "Polish Zloty",
            "symbol" => "z\u0142"
        ],
        "ZMW" => [
            "code" => "ZMW",
            "name" => "Zambian Kwacha",
            "symbol" => "ZK"
        ],
        "MAD" => [
            "code" => "MAD",
            "name" => "Moroccan Dirham",
            "symbol" => "MAD"
        ],
        "EGP" => [
            "code" => "EGP",
            "name" => "Egyptian Pound",
            "symbol" => "E\u00a3"
        ],
        "ZAR" => [
            "code" => "ZAR",
            "name" => "South African Rand",
            "symbol" => "R"
        ],
        "VND" => [
            "code" => "VND",
            "name" => "Vietnamese Dong",
            "symbol" => "\u20ab"
        ],
        "SBD" => [
            "code" => "SBD",
            "name" => "Solomon Islands Dollar",
            "symbol" => "Si$"
        ],
        "ETB" => [
            "code" => "ETB",
            "name" => "Ethiopian Birr",
            "symbol" => "\u1265\u122d"
        ],
        "SOS" => [
            "code" => "SOS",
            "name" => "Somali Shilling",
            "symbol" => "Sh.so."
        ],
        "SAR" => [
            "code" => "SAR",
            "name" => "Saudi Riyal",
            "symbol" => "\u0631.\u0633"
        ],
        "ERN" => [
            "code" => "ERN",
            "name" => "Eritrean Nakfa",
            "symbol" => "\u0646\u0627\u0641\u0643\u0627"
        ],
        "MDL" => [
            "code" => "MDL",
            "name" => "Moldovan Leu",
            "symbol" => "L"
        ],
        "MGA" => [
            "code" => "MGA",
            "name" => "Malagasy Ariary",
            "symbol" => "Ar"
        ],
        "UZS" => [
            "code" => "UZS",
            "name" => "Uzbekistan Som",
            "symbol" => "so'm"
        ],
        "MMK" => [
            "code" => "MMK",
            "name" => "Myanmar Kyat",
            "symbol" => "K"
        ],
        "MOP" => [
            "code" => "MOP",
            "name" => "Macanese Pataca",
            "symbol" => "MOP$"
        ],
        "MNT" => [
            "code" => "MNT",
            "name" => "Mongolian T\u00f6gr\u00f6g",
            "symbol" => "\u20ae"
        ],
        "MKD" => [
            "code" => "MKD",
            "name" => "Macedonian Denar",
            "symbol" => "\u0414\u0435\u043d"
        ],
        "MUR" => [
            "code" => "MUR",
            "name" => "Mauritian Rupee",
            "symbol" => "\u20a8"
        ],
        "MWK" => [
            "code" => "MWK",
            "name" => "Malawian Kwacha",
            "symbol" => "MK"
        ],
        "MVR" => [
            "code" => "MVR",
            "name" => "Maldivian Rufiyaa",
            "symbol" => "Rf"
        ],
        "MRO" => [
            "code" => "MRO",
            "name" => "Mauritanian Ouguiya",
            "symbol" => "UM"
        ],
        "UGX" => [
            "code" => "UGX",
            "name" => "Ugandan Shilling",
            "symbol" => "USh"
        ],
        "TZS" => [
            "code" => "TZS",
            "name" => "Tanzanian Shilling",
            "symbol" => "TSh"
        ],
        "MYR" => [
            "code" => "MYR",
            "name" => "Malaysian Ringgit",
            "symbol" => "RM"
        ],
        "MXN" => [
            "code" => "MXN",
            "name" => "Mexican Peso",
            "symbol" => "Mex$"
        ],
        "SHP" => [
            "code" => "SHP",
            "name" => "Saint Helena Pound",
            "symbol" => "\u00a3"
        ],
        "FJD" => [
            "code" => "FJD",
            "name" => "Fiji Dollar",
            "symbol" => "FJ$"
        ],
        "FKP" => [
            "code" => "FKP",
            "name" => "Falkland Islands Pound",
            "symbol" => "\u00a3"
        ],
        "NIO" => [
            "code" => "NIO",
            "name" => "Nicaraguan C\u00f3rdoba",
            "symbol" => "C$"
        ],
        "NAD" => [
            "code" => "NAD",
            "name" => "Namibian Dollar",
            "symbol" => "N$"
        ],
        "VUV" => [
            "code" => "VUV",
            "name" => "Vanuatu Vatu",
            "symbol" => "VT"
        ],
        "NGN" => [
            "code" => "NGN",
            "name" => "Nigerian Naira",
            "symbol" => "\u20a6"
        ],
        "NPR" => [
            "code" => "NPR",
            "name" => "Nepalese Rupee",
            "symbol" => "\u0930\u0942"
        ],
        "CHF" => [
            "code" => "CHF",
            "name" => "Swiss Franc",
            "symbol" => "Fr."
        ],
        "COP" => [
            "code" => "COP",
            "name" => "Colombian Peso",
            "symbol" => "$"
        ],
        "CNY" => [
            "code" => "CNY",
            "name" => "Chinese Yuan",
            "symbol" => "\u00a5"
        ],
        "CNH" => [
            "code" => "CNH",
            "name" => "Chinese Yuan Renminbi",
            "symbol" => "\u00a5"
        ],
        "CLP" => [
            "code" => "CLP",
            "name" => "Chilean Peso",
            "symbol" => "$"
        ],
        "CAD" => [
            "code" => "CAD",
            "name" => "Canadian Dollar",
            "symbol" => "CAD $"
        ],
        "CDF" => [
            "code" => "CDF",
            "name" => "Congolese Franc",
            "symbol" => "FC"
        ],
        "CZK" => [
            "code" => "CZK",
            "name" => "Czech Koruna",
            "symbol" => "CZK"
        ],
        "CRC" => [
            "code" => "CRC",
            "name" => "Costa Rican Colon",
            "symbol" => "\u20a1"
        ],
        "ANG" => [
            "code" => "ANG",
            "name" => "Netherlands Antillean Guilder",
            "symbol" => "NA\u0192"
        ],
        "CVE" => [
            "code" => "CVE",
            "name" => "Cape Verde Escudo",
            "symbol" => "$"
        ],
        "CUC" => [
            "code" => "CUC",
            "name" => "Cuban Convertible Peso",
            "symbol" => "CUC"
        ],
        "CUP" => [
            "code" => "CUP",
            "name" => "Cuban Peso",
            "symbol" => "$"
        ],
        "SZL" => [
            "code" => "SZL",
            "name" => "Swazi Lilangeni",
            "symbol" => "E"
        ],
        "SYP" => [
            "code" => "SYP",
            "name" => "Syrian Pound",
            "symbol" => "\u00a3S"
        ],
        "KGS" => [
            "code" => "KGS",
            "name" => "Kyrgyzstani Som",
            "symbol" => "\u041b\u0432"
        ],
        "KES" => [
            "code" => "KES",
            "name" => "Kenyan Shilling",
            "symbol" => "Ksh"
        ],
        "SSP" => [
            "code" => "SSP",
            "name" => "South Sudanese Pound",
            "symbol" => "\u00a3"
        ],
        "SRD" => [
            "code" => "SRD",
            "name" => "Surinamese Dollar",
            "symbol" => "$"
        ],
        "KHR" => [
            "code" => "KHR",
            "name" => "Cambodian Riel",
            "symbol" => "\u17db"
        ],
        "KMF" => [
            "code" => "KMF",
            "name" => "Comoro Franc",
            "symbol" => "CF"
        ],
        "STD" => [
            "code" => "STD",
            "name" => "Sao Tomean Dobra",
            "symbol" => "Db"
        ],
        "KRW" => [
            "code" => "KRW",
            "name" => "South Korean Won",
            "symbol" => "\u20a9"
        ],
        "KPW" => [
            "code" => "KPW",
            "name" => "North Korean Won",
            "symbol" => "\u20a9"
        ],
        "KWD" => [
            "code" => "KWD",
            "name" => "Kuwaiti Dinar",
            "symbol" => "\u062f.\u0643"
        ],
        "SLL" => [
            "code" => "SLL",
            "name" => "Sierra Leonean Leone",
            "symbol" => "Le"
        ],
        "SCR" => [
            "code" => "SCR",
            "name" => "Seychelles Rupee",
            "symbol" => "SR"
        ],
        "KZT" => [
            "code" => "KZT",
            "name" => "Kazakhstani Tenge",
            "symbol" => "\u20b8"
        ],
        "KYD" => [
            "code" => "KYD",
            "name" => "Cayman Islands Dollar",
            "symbol" => "$"
        ],
        "SEK" => [
            "code" => "SEK",
            "name" => "Swedish Krona",
            "symbol" => "kr"
        ],
        "SDG" => [
            "code" => "SDG",
            "name" => "Sudanese Pound",
            "symbol" => "\u062c.\u0633."
        ],
        "DOP" => [
            "code" => "DOP",
            "name" => "Dominican Peso",
            "symbol" => "$"
        ],
        "DJF" => [
            "code" => "DJF",
            "name" => "Djiboutian Franc",
            "symbol" => "Fdj"
        ],
        "YER" => [
            "code" => "YER",
            "name" => "Yemeni Rial",
            "symbol" => "\ufdfc"
        ],
        "DZD" => [
            "code" => "DZD",
            "name" => "Algerian Dinar",
            "symbol" => "\u062f\u062c"
        ],
        "UYU" => [
            "code" => "UYU",
            "name" => "Uruguayan Peso",
            "symbol" => "$"
        ],
        "LBP" => [
            "code" => "LBP",
            "name" => "Lebanese Pound",
            "symbol" => "\u0644.\u0644."
        ],
        "LAK" => [
            "code" => "LAK",
            "name" => "Lao Kip",
            "symbol" => "\u20ad"
        ],
        "TWD" => [
            "code" => "TWD",
            "name" => "New Taiwan Dollar",
            "symbol" => "$"
        ],
        "TTD" => [
            "code" => "TTD",
            "name" => "Trinidad and Tobago Dollar",
            "symbol" => "$"
        ],
        "TRY" => [
            "code" => "TRY",
            "name" => "Turkish Lira",
            "symbol" => "\u20ba"
        ],
        "LKR" => [
            "code" => "LKR",
            "name" => "Sri Lankan Rupee",
            "symbol" => "\u0dbb\u0dd4"
        ],
        "TOP" => [
            "code" => "TOP",
            "name" => "Tongan pa\u02bbanga",
            "symbol" => "T$"
        ],
        "LRD" => [
            "code" => "LRD",
            "name" => "Liberian Dollar",
            "symbol" => "L$"
        ],
        "LSL" => [
            "code" => "LSL",
            "name" => "Lesotho Loti",
            "symbol" => "M"
        ],
        "THB" => [
            "code" => "THB",
            "name" => "Thai Baht",
            "symbol" => "\u0e3f"
        ],
        "LYD" => [
            "code" => "LYD",
            "name" => "Libyan Dinar",
            "symbol" => "\u0644.\u062f"
        ],
        "AED" => [
            "code" => "AED",
            "name" => "UAE Dirham",
            "symbol" => "\u062f.\u0625"
        ],
        "AFN" => [
            "code" => "AFN",
            "name" => "Afghan Afghani",
            "symbol" => "\u060b"
        ],
        "ISK" => [
            "code" => "ISK",
            "name" => "Icelandic Kr\u00f3na",
            "symbol" => "kr"
        ],
        "IRR" => [
            "code" => "IRR",
            "name" => "Iranian Rial",
            "symbol" => "\ufdfc"
        ],
        "AMD" => [
            "code" => "AMD",
            "name" => "Armenian Dram",
            "symbol" => "\u058f"
        ],
        "ALL" => [
            "code" => "ALL",
            "name" => "Albanian Lek",
            "symbol" => "L"
        ],
        "AOA" => [
            "code" => "AOA",
            "name" => "Angolan Kwanza",
            "symbol" => "Kz"
        ],
        "ARS" => [
            "code" => "ARS",
            "name" => "Argentine Peso",
            "symbol" => "$"
        ],
        "BYR" => [
            "code" => "BYR",
            "name" => "Belarusian Rouble",
            "symbol" => "BYN"
        ],
        "INR" => [
            "code" => "INR",
            "name" => "Indian Rupee",
            "symbol" => "\u20b9"
        ],
        "UAH" => [
            "code" => "UAH",
            "name" => "Ukrainian Hryvnia",
            "symbol" => "\u20b4"
        ],
        "AWG" => [
            "code" => "AWG",
            "name" => "Aruban Florin",
            "symbol" => "\u0192"
        ],
        "AZN" => [
            "code" => "AZN",
            "name" => "Azerbaijani Manat",
            "symbol" => "\u20bc"
        ],
        "IDR" => [
            "code" => "IDR",
            "name" => "Indonesian Rupiah",
            "symbol" => "Rp"
        ],
        "QAR" => [
            "code" => "QAR",
            "name" => "Qatari Rial",
            "symbol" => "\u0631.\u0642"
        ],
        "MZN" => [
            "code" => "MZN",
            "name" => "Mozambican Metical",
            "symbol" => "MT"
        ]
    ];

    protected static array $currencies = [];

    /**
     * @param string $code
     * @return Currency
     */
    public static function getCurrencyByCode(string $code): Currency
    {
        if (isset(self::LIST[$code])) {
            return self::$currencies[$code] ??= new Currency($code);
        }

        $code = strtoupper(trim($code));
        return ($code !== '' && isset(self::LIST[$code]))
            ? (self::$currencies[$code] ??= new Currency($code))
            : new Currency($code);
    }
}
