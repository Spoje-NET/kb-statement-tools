Raiffeisenbank Statement Downloader
===================================

![raiffeisenbank-statement-downloader](raiffeisenbank-statement-downloader.svg?raw=true)

Statement Downloader
--------------------

```shell
raiffeisenbank-statement-downloader [save/to/directory] [format] [path/to/.env]
```

Example output when EASE_LOGGER=console

```
12/01/2023 16:37:10 ⚙ ❲RaiffeisenBank Statement Downloader⦒123456789@VitexSoftware\Raiffeisenbank\Statementor❳ Request statements from 2023-11-30 to 2023-11-30
12/01/2023 16:37:13 🌼 ❲RaiffeisenBank Statement Downloader⦒123@VitexSoftware\Raiffeisenbank\Statementor❳ 10_2023_123_3780381_CZK_2023-11-01.xml saved
12/01/2023 16:37:13 ℹ ❲RaiffeisenBank Statement Downloader⦒123456789@VitexSoftware\Raiffeisenbank\Statementor❳ Download done. 1 of 1 saved

```

Balance Check
-------------

```shell
raiffeisenbank-balance [path/to/.env]
```

Example output:

```json
{
    "numberPart2": "635814116",
    "bankCode": "5500",
    "currencyFolders": [
        {
            "currency": "CZK",
            "status": "ACTIVE",
            "balances": [
                {
                    "balanceType": "CLAB",
                    "currency": "CZK",
                    "value": 5883.89
                },
                {
                    "balanceType": "CLBD",
                    "currency": "CZK",
                    "value": 5883.89
                },
                {
                    "balanceType": "CLAV",
                    "currency": "CZK",
                    "value": 20853.89
                },
                {
                    "balanceType": "BLCK",
                    "currency": "CZK",
                    "value": 0
                }
            ]
        },
        {
            "currency": "EUR",
            "status": "ACTIVE",
            "balances": [
                {
                    "balanceType": "CLAB",
                    "currency": "EUR",
                    "value": 133.76
                },
                {
                    "balanceType": "CLBD",
                    "currency": "EUR",
                    "value": 133.76
                },
                {
                    "balanceType": "CLAV",
                    "currency": "EUR",
                    "value": 133.76
                },
                {
                    "balanceType": "BLCK",
                    "currency": "EUR",
                    "value": 0
                }
            ]
        }
    ]
}
```

Configuration
-------------

Please set this environment variables or specify path to .env file

```env
CERT_FILE='RAIFF_CERT.p12'
CERT_PASS=CertPass
XIBMCLIENTID=PwX4XXXXXXXXXXv6I
ACCOUNT_NUMBER=666666666
ACCOUNT_CURRENCY=CZK
STATEMENT_FORMAT=pdf | xml | MT940
STATEMENT_LINE=MAIN
STATEMENT_IMPORT_SCOPE=last_two_months
STATEMENTS_DIR=~/Documents/
API_DEBUG=True
APP_DEBUG=True
EASE_LOGGER=syslog|eventlog|console
```


Availble Import Scope Values
----------------------------

* 'yesterday'
* 'current_month'
* 'last_month'
* 'last_two_months'
* 'previous_month'
* 'two_months_ago'
* 'this_year'
* 'January'
* 'February'
* 'March'
* 'April'
* 'May'
* 'June'
* 'July'
* 'August'
* 'September'
* 'October'
* 'November',
* 'December'

Created using the library [php-rbczpremiumapi](https://github.com/VitexSoftware/php-vitexsoftware-rbczpremiumapi)

MultiFlexi
----------

**Raiffeisenbank Statement Downloader** is ready for run as [MultiFlexi](https://multiflexi.eu) application.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)
