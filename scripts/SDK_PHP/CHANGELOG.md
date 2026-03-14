CHANGELOG
=========
## 1.3.2506240 - 2025-06-24
-----
### Added
- Add the `CreditCardPeriodAction` and `CreateWeiXinOrder` examples under Payment > AIO.

### Fixed
- Fix the decoding format in the examples under Logistics > Domestic: `CreateCvs.php`, `CreateHome.php`, and `CreateUnimartFreeze.php`.

## 1.3.2411270 - 2024-11-27
-----
### Added
- Add Logistic Status Code Files.
### Changed
- Change AIO test integration account.
### Deprecated
- Remove Sample Code CreateOrderIssueInvoice.php

## 1.3.2411050 - 2024-11-05
-----
### Added
- Add the domestic logistic `GetStoreList API` example.

## 1.3.2408190 - 2024-08-19
-----
### Added
- Add `ManualFormService` for generating HTML forms is not auto-submit.

### Changed
- Updated jQuery from version 3.5.1 to 3.7.1.
- Enabled the `curl` option `CURLOPT_FOLLOWLOCATION` in the `run` function of the `CurlService`.

## 1.2.2403070 - 2024-03-07
-----
### Added
- Add Ecpg binding card examples.
## 1.2.2301023 - 2023-10-23
-----
### Added
- Add BNPL payment example
- Add TWQR payment example
## 1.2.2308100 - 2023-08-21
-----
### Added
- Add Ecpg payment example
## 1.2.2308010 - 2023-08-01
-----
### Added
- Add an annotation for $_POST parameter in GetCheckoutResponse.php
## 1.2.2210310 - 2022-10-31
-----

### Fixed
- Fix CheckMacValue verify failed when ItemName contains plus(+) sign.

## 1.2.0 - 2022-01-26
-----

### Fixed
- Fix PHP 5.6 compibility.
- Fix exmaple/Logistics/CrossBorder/Map.php using wrong service class.

### Added
- Add support for Ecpay `All in one logistics` API.
- Add TransCode validation(TransException).
- Add Unimart and Hilife freeze delivery return examples.

## 1.1.0 - 2021-07-26
-----

### Fixed
- Added AES decrypt and JSON decode result checking

### Added
- Added support for Ecpay `B2C invoice` API
- Added support for Ecpay `C2C invoice` API
- Follow the [Semantic Versioning](https://semver.org)

## 1.0.2106040 - 2021-07-19
-----

 ### Fixed
 - Added JSON responses format checking

 ### Deprecated
 - Marked `Ecpay\Sdk\Factories\Factory::createWithHash` as deprecated (it's still there, just not the
  recommended way) and remove from examples.

 ### Added
 - Added support for Ecpay `domestic logistics` API
 - Added `MD5` CheckMacValue
 - Added `curl POST with CheckMacValue and parse string reponse without CheckMacValue`


## 1.0.2011250 - 2020-12-24
-----

 ### Added
 - Added support for Ecpay `payment` API
 - Added support for Ecpay `cross-border logistics` API