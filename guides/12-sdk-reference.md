> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03
>
> **目標讀者**：使用 ECPay 官方 PHP SDK（Composer 安裝）的 PHP 開發者。
>
> **非 PHP 開發者**：本指南可跳過，改讀 [guides/20 HTTP 協議參考](./20-http-protocol-reference.md) 直接實作 HTTP 請求。
>
> **PHP 開發者（不用 SDK）**：本指南可跳過，直接參考 [scripts/SDK_PHP/example/](../scripts/SDK_PHP/example/) 中的 134 個 PHP 範例。
>
> **API 規格來源**：`references/` 目錄包含所有服務的官方 API 文件 URL 索引，SDK Service 與 API 端點的對應請參閱各 reference 檔案。

# PHP SDK 完整參考

## 安裝

```bash
composer require ecpay/sdk
```

## 基本用法

```php
use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Services\CheckMacValueService;

$factory = new Factory([
    'hashKey'    => '你的HashKey',
    'hashIv'     => '你的HashIV',
    'hashMethod' => CheckMacValueService::METHOD_SHA256,  // 或 METHOD_MD5
]);

$service = $factory->create('ServiceName');
```

> ⚠️ `Factory::createWithHash()` 已於 **v1.0.2105270**（2021 年 5 月）棄用，目前 SDK 仍可呼叫但不建議使用，未來版本可能移除。請改用建構子傳入 hashKey/hashIv：
> `$factory = new Factory(['hashKey' => '...', 'hashIv' => '...'])`

## Factory Service 完整別名表

> 從 `scripts/SDK_PHP/src/Factories/Factory.php` 原始碼提取

### CheckMacValue 系列（用於 AIO 金流、國內物流）

| 別名 | 用途 | 請求方式 | 回應方式 |
|------|------|---------|---------|
| `AutoSubmitFormWithCmvService` | 產生自動送出表單 | HTML Form + CMV | 瀏覽器導向 |
| `FormWithCmvService` | 產生手動表單 | HTML Form + CMV | 瀏覽器導向 |
| `PostWithCmvVerifiedEncodedStrResponseService` | POST + 驗證回應 CMV | POST + CMV | URL-encoded 字串（驗證 CMV）|
| `PostWithCmvEncodedStrResponseService` | POST | POST + CMV | URL-encoded 字串 |
| `PostWithCmvJsonResponseService` | POST | POST + CMV | JSON |
| `PostWithCmvStrResponseService` | POST | POST + CMV | 純字串 |

**FormWithCmvService 使用範例**（產生可讓使用者手動送出的 HTML 表單，適用瀏覽器導向流程，如訂閱/定期定額確認頁）：

```php
$factory = new Factory([
    'hashKey' => getenv('ECPAY_HASH_KEY'),
    'hashIv'  => getenv('ECPAY_HASH_IV'),
]);
$formService = $factory->create('FormWithCmvService');
$html = $formService->generate($input, $url);
echo $html;
```

### AES 系列（用於 ECPG、發票、全方位物流、跨境物流）

| 別名 | 用途 | 請求方式 | 回應方式 |
|------|------|---------|---------|
| `PostWithAesJsonResponseService` | POST + AES | JSON + AES 加密 Data | JSON（AES 解密 Data）|
| `PostWithAesStrResponseService` | POST + AES | JSON + AES 加密 Data | 純字串（HTML）|

### 其他

| 別名 | 用途 |
|------|------|
| `AutoSubmitFormService` | 表單送出（無 CMV，跨境地圖用）|
| `JsonCurlService` | 純 JSON POST（內部使用，PostWithAesJsonResponseService 的底層元件）|
| `CheckMacValueService::class` | 取得 CMV 服務實例（手動生成/驗證）|
| `AesService::class` | 取得 AES 服務實例（手動加解密）|

### 回應類別

| 類別 | 用途 |
|------|------|
| `VerifiedArrayResponse::class` | 驗證 CMV 後轉陣列（付款 ReturnURL、物流通知）|
| `ArrayResponse::class` | 直接轉陣列（無驗證，發票回呼、地圖回應）|
| `AesJsonResponse::class` | AES 解密 JSON（全方位物流通知、跨境狀態通知）|
| `VerifiedEncodedStrResponse::class` | URL-encoded 字串（驗證 CMV，`PostWithCmvVerifiedEncodedStrResponseService`）|
| `EncodedStrResponse::class` | URL-encoded 字串（不驗 CMV，`PostWithCmvEncodedStrResponseService`）|
| `JsonResponse::class` | JSON 回應（`PostWithCmvJsonResponseService`）|
| `StrResponse::class` | 純字串回應（`PostWithCmvStrResponseService`）|
| `AesStrResponse::class` | HTML / 純字串（`PostWithAesStrResponseService`）|

### Callback 回應輔助類別

| 類別 | 命名空間 | 用途 |
|------|---------|------|
| `AesRequest::class` | `Ecpay\Sdk\Request\` | 產生 AES 加密的 Callback 回應（PHP 範例常 alias 為 `AesGenerater`）|

> **注意**：`AesRequest` 位於 `src/Request/` 命名空間（非 `src/Response/`），用於**發出**加密回應，而非解析收到的回應。部分 PHP 範例使用 `use Ecpay\Sdk\Request\AesRequest as AesGenerater`。

## 兩種加密模式

### CheckMacValue（SHA256 / MD5）

```
適用：AIO 金流、國內物流
流程：參數排序 → 組合字串 → URL encode → Hash → 大寫
```

| 服務 | Hash 方法 |
|------|----------|
| AIO 金流 | SHA256 |
| 國內物流 | **MD5** |

### AES-128-CBC

```
適用：站內付 2.0、電子發票、全方位物流、跨境物流
流程：JSON → URL encode → AES encrypt → Base64
```

## 各服務對應的 Factory 用法

| 服務 | Factory hashMethod | 主要 Service |
|------|-------------------|-------------|
| AIO 金流 | SHA256 | AutoSubmitFormWithCmvService |
| 國內物流 | **md5** | PostWithCmvStrResponseService |
| 站內付 2.0 | SHA256（不影響） | PostWithAesJsonResponseService |
| B2C/B2B 發票 | SHA256（不影響） | PostWithAesJsonResponseService |
| 全方位物流 | SHA256（不影響） | PostWithAesJsonResponseService |
| 跨境物流 | SHA256（不影響） | PostWithAesJsonResponseService |

## 完整請求/回應對照

### AIO 金流

| 操作 | Service | 端點 |
|------|---------|------|
| 建立訂單 | AutoSubmitFormWithCmvService | /Cashier/AioCheckOut/V5 |
| 接收通知 | VerifiedArrayResponse | ReturnURL |
| 查詢訂單 | PostWithCmvVerifiedEncodedStrResponseService | /Cashier/QueryTradeInfo/V5 |
| 查詢付款 | PostWithCmvVerifiedEncodedStrResponseService | /Cashier/QueryPaymentInfo |
| 信用卡查詢 | PostWithCmvJsonResponseService | /CreditDetail/QueryTrade/V2 |
| 定期查詢 | PostWithCmvJsonResponseService | /Cashier/QueryCreditCardPeriodInfo |
| 請款退款 | PostWithCmvEncodedStrResponseService | /CreditDetail/DoAction |
| 定期管理 | PostWithCmvEncodedStrResponseService | /Cashier/CreditCardPeriodAction |
| 下載對帳 | AutoSubmitFormWithCmvService | /PaymentMedia/TradeNoAio |

### 站內付 2.0

| 操作 | Service | 端點 |
|------|---------|------|
| 取 Token | PostWithAesJsonResponseService | ecpg/GetTokenbyTrade |
| 建立交易 | PostWithAesJsonResponseService | ecpg/CreatePayment |
| 解密回應 | AesService::class | — |
| 請款退款 | PostWithAesJsonResponseService | ecpayment/Credit/DoAction |
| 查詢 | PostWithAesJsonResponseService | ecpayment/QueryTrade |

### 發票

| 操作 | Service | Revision |
|------|---------|---------|
| B2C 所有 | PostWithAesJsonResponseService | 3.0.0 |
| B2B 所有 | PostWithAesJsonResponseService | 1.0.0（含 RqID） |

### 物流

| 操作 | Service | 加密 |
|------|---------|------|
| 國內（建單/退貨/更新） | PostWithCmvStrResponseService | MD5 |
| 國內（查詢） | PostWithCmvVerifiedEncodedStrResponseService | MD5 |
| 全方位（API） | PostWithAesJsonResponseService | AES |
| 全方位（HTML） | PostWithAesStrResponseService | AES |
| 跨境 | PostWithAesJsonResponseService | AES |

## SDK Service → HTTP 協議對照

本表幫助非 PHP 開發者理解每個 SDK Service 在底層做了什麼 HTTP 操作，以便在目標語言中正確實作。

| SDK Service | 族群 | Content-Type | 請求 Body | 回應格式 | 認證 |
|------------|------|-------------|----------|---------|------|
| AutoSubmitFormWithCmvService | A | form-urlencoded | 產生 HTML 表單自動提交 | HTML（瀏覽器重導） | CMV SHA256 |
| FormWithCmvService | A | form-urlencoded | 產生 HTML 表單手動提交 | HTML（瀏覽器重導） | CMV SHA256 |
| PostWithCmvStrResponseService | A/C | form-urlencoded | key=value 表單 | 純字串（多為 pipe-separated） | CMV（SHA256 或 MD5 依 hashMethod） |
| PostWithCmvEncodedStrResponseService | A | form-urlencoded | key=value 表單 | URL-encoded 字串 | CMV SHA256 |
| PostWithCmvVerifiedEncodedStrResponseService | A | form-urlencoded | key=value 表單 | URL-encoded 字串（驗證回傳 CMV） | CMV SHA256 |
| PostWithCmvJsonResponseService | A | form-urlencoded | key=value 表單 | JSON | CMV SHA256 |
| PostWithAesJsonResponseService | B | application/json | 三層 JSON（AES Data） | 三層 JSON（AES Data） | AES-128-CBC |
| PostWithAesStrResponseService | B | application/json | 三層 JSON（AES Data） | HTML 字串 | AES-128-CBC |

### 非 PHP 語言的翻譯規則

對應每個 SDK Service，非 PHP 開發者需要實作：

**AutoSubmitFormWithCmvService**（AIO 建單用）：
1. 構造 key=value 參數（含 CheckMacValue）
2. 產生 HTML `<form>` 包含所有參數作為 `<input type="hidden">`
3. 加上自動提交 JavaScript：`document.getElementById('ecpay-form').submit()`
4. 此表單由消費者瀏覽器提交至綠界（非伺服器端 POST）

**PostWithCmvStrResponseService**（物流建單用）：
1. 構造 key=value 參數（含 CheckMacValue）
2. 發送 HTTP POST，Content-Type: `application/x-www-form-urlencoded`
3. 解析回應：以 `|` 分割，第一部分為狀態碼（1=成功/0=失敗），第二部分為資料
4. 資料部分再以 `&` 和 `=` 解析為 key-value

**PostWithCmvEncodedStrResponseService / PostWithCmvVerifiedEncodedStrResponseService**（AIO 查詢用）：
1. 構造 key=value 參數（含 CheckMacValue）
2. 發送 HTTP POST，Content-Type: `application/x-www-form-urlencoded`
3. 回應為 URL-encoded 字串，以 `&` 和 `=` 解析為 key-value
4. Verified 版本需額外驗證回應中的 CheckMacValue

**PostWithCmvJsonResponseService**（AIO 信用卡查詢用）：
1. 構造 key=value 參數（含 CheckMacValue）
2. 發送 HTTP POST，Content-Type: `application/x-www-form-urlencoded`
3. 回應為 JSON，標準 JSON parse

**PostWithAesJsonResponseService**（ECPG/發票/幕後/全方位物流/跨境用）：
1. 構造業務參數 JSON
2. URL encode → AES-128-CBC 加密 → Base64
3. 組裝三層 JSON：`{ Data: "加密字串", MerchantID: "...", RqHeader: { Timestamp } }`（⚠️ SDK 實際送出時按 naturalSort 字母序排列：D < M < R；一般 JSON 解析器忽略 key 順序，對功能無影響）
4. 發送 HTTP POST，Content-Type: `application/json`
5. 解析回應 JSON，檢查 `TransCode === 1`
6. Base64 解碼 → AES 解密 → URL decode → JSON parse Data 欄位
7. 檢查 `RtnCode === 1`

完整的 HTTP 協議規格（含端點 URL、回應格式對照表）見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md)。

## SDK 工具函式（Reference Implementation）

PHP SDK 包含多個 provider-specific 工具函式，這些是主要服務的變體實作，而非獨立功能。
非 PHP 開發者可忽略這些工具函式，直接參考主要範例的 HTTP 協議實作。

| 工具函式類型 | 範例檔案 | 對應主要範例 | 說明 |
|------------|---------|------------|------|
| 便利商店列印 | `PrintFamic2cOrderInfo.php` | `PrintTradeDocument.php` | 特定超商（全家 C2C）的列印變體 |
| 退貨物流 | `ReturnFamiCvs.php` | `ReturnHome.php` | 特定超商（全家）的退貨變體 |
| 站內付 2.0 GetToken | `CreateCreditOrder/GetToken.php` | `CreateAllOrder/GetToken.php` | 指定信用卡的 Token 取得（主範例支援全付款方式）。開發者可直接呼叫 `/Merchant/GetTokenbyTrade`，代表性範例見 `CreateAllOrder/GetToken.php`。 |
| 物流更新 | `UpdateStoreInfo.php` | `UpdateShipmentInfo.php` | 門市/配送資訊更新變體（AllInOne C2C 與 Domestic 均有對應範例） |
| 對帳下載 | `DownloadReconcileCsv.php` | `DownloadReconcileCsv.php` | 僅 AIO 有此範例（`Payment/Aio/`），ECPG 對帳使用 `QueryTradeMedia.php`（端點與格式不同） |

> **非 PHP 開發者**：上述工具函式在 HTTP 層面與主要範例使用相同的 API 端點和請求格式，
> 只是參數組合不同。建議直接看主要範例（如 `CreateAllOrder/GetToken.php`），
> 了解通用的請求/回應格式後，再依需求調整參數即可。
> 完整的非 PHP 語言整合範例見 [guides/24-multi-language-integration.md](./24-multi-language-integration.md)。

## 相關文件

- SDK 原始碼：`scripts/SDK_PHP/src/`
- 範例程式：`scripts/SDK_PHP/example/`（134 個）
- HTTP 協議參考：[guides/20-http-protocol-reference.md](./20-http-protocol-reference.md)
- 多語言整合：[guides/24-multi-language-integration.md](./24-multi-language-integration.md)
- CheckMacValue：[guides/13-checkmacvalue.md](./13-checkmacvalue.md)
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- 錯誤碼參考：[guides/21-error-codes-reference.md](./21-error-codes-reference.md)
- Callback 參考：[guides/22-webhook-events-reference.md](./22-webhook-events-reference.md)
- 效能與擴展：[guides/23-performance-scaling.md](./23-performance-scaling.md)
