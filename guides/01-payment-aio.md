> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 全方位金流 AIO 完整指南

> **非 PHP 開發者？** 建議閱讀順序：
> 1. [guides/13](./13-checkmacvalue.md) — 實作你的語言的 CheckMacValue，並通過測試向量驗證
> 2. [guides/20](./20-http-protocol-reference.md) — 確認 AIO 的 HTTP 請求格式（Content-Type、回應格式）
> 3. 回到本文 — 理解 AIO 整合流程和參數，將 PHP 範例翻譯為你的語言
> 4. [guides/24](./24-multi-language-integration.md) — 完整多語言 E2E 範例和 Checklist

## 概述

AIO（All-In-One）是 ECPay 最常用的金流整合方案，將消費者導向綠界標準付款頁面，支援 10+ 種付款方式。適合絕大多數電商場景。

## 前置需求

- MerchantID / HashKey / HashIV（測試：3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs）
- PHP SDK：`composer require ecpay/sdk`
- 加密方式：CheckMacValue SHA256

> **非 PHP 語言前置安裝**（PHP 開發者忽略此區塊）：
> ```bash
> # Python
> pip install flask requests          # Flask 伺服器 + HTTP 客戶端
>
> # Node.js
> npm install express body-parser     # Express 伺服器
>
> # TypeScript / Bun
> npm install express @types/express typescript ts-node
> ```

> **⚠️ 安全提醒**：本指南範例中的 HashKey / HashIV 為公開測試值。
> 正式環境**禁止**在程式碼中硬編碼 — 務必使用環境變數或密鑰管理服務。
> 見 [guides/16-go-live-checklist.md](./16-go-live-checklist.md) §安全性。

## 🚀 首次串接：最快成功路徑

> 第一次串接 AIO？從這裡開始，目標是完成**一筆信用卡一次付清**的測試交易。

### 前置確認清單

開始前請確認以下全部完成，否則任何步驟都可能無聲失敗：

- [ ] 測試帳號就緒：MerchantID `3002607` / HashKey `pwFHCqoQZGmho4w6` / HashIV `EkRm7iFT261dpevs`
- [ ] **ReturnURL 可公開訪問**（localhost / 127.0.0.1 完全無效，綠界無法回呼。用 ngrok 或部署到可訪問的主機）
- [ ] CheckMacValue 演算法已實作並通過測試向量（見 [guides/13](./13-checkmacvalue.md) §test-vectors）
- [ ] PHP SDK 已安裝（`composer require ecpay/sdk`）或已自行實作 CMV-SHA256 請求格式
- [ ] **付款方式先用 `ChoosePayment=Credit`**（全選 ALL 測試時較難定位問題）

---

### 步驟 1：後端建立訂單

> 參考範例：`scripts/SDK_PHP/example/Payment/Aio/CreateCreditOrder.php`

```php
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');
$input = [
    'MerchantID'        => '3002607',
    'MerchantTradeNo'   => 'AIO' . time(),            // 不可重複，最長20字元
    'MerchantTradeDate' => date('Y/m/d H:i:s'),
    'PaymentType'       => 'aio',
    'TotalAmount'       => 100,
    'TradeDesc'         => 'Test',
    'ItemName'          => '測試商品',
    'ReturnURL'         => 'https://你的網站/ecpay/notify',   // Server-to-Server
    'ChoosePayment'     => 'Credit',
    'EncryptType'       => 1,
];
echo $autoSubmitFormService->generate($input, 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5');
```

回應是 HTML 表單並自動 submit，**瀏覽器會被導向綠界付款頁**。

> 🔍 **此步驟失敗？**
>
> | 症狀 | 最可能原因 |
> |------|----------|
> | 頁面空白 / 看到 error | HashKey/HashIV 錯誤，或 SDK 未安裝 |
> | `MerchantTradeDate` 格式錯誤 | 必須是 `Y/m/d H:i:s`，不是 Unix timestamp |
> | 重複交易編號 | MerchantTradeNo 在測試帳號下已用過，換一個 |

---

### 步驟 2：消費者在綠界付款頁完成付款

測試信用卡號：`4311-9522-2222-2222`，有效期限任意未來日期，CVV `222`（任意 3 位均可），3DS 驗證碼：`1234`。

---

### 步驟 3：接收 ReturnURL 付款通知

綠界以 **Server-to-Server Form POST** 呼叫你的 ReturnURL。

> ⚠️ 必須回應 **`1|OK`**，否則綠界會每 5-15 分鐘重試，最多每日 4 次。

```php
// ReturnURL 處理（scripts/SDK_PHP/example/Payment/Aio/GetCheckoutResponse.php）
$checkMacValueService = $factory->create(CheckMacValueService::class);

// 驗證 CheckMacValue（timing-safe，跳過此步是安全漏洞）
$isValid = hash_equals(
    $checkMacValueService->generate($_POST),
    $_POST['CheckMacValue'] ?? ''
);
if (!$isValid) {
    error_log('ECPay callback CheckMacValue 驗證失敗');
    echo '1|OK';  // 記錄錯誤後仍需回應，避免綠界持續重送
    exit;
}

// 檢查付款結果
if (($_POST['RtnCode'] ?? null) === '1') {
    // 付款成功，更新訂單狀態
}

echo '1|OK';  // 必須回應，否則綠界會重試
```

```python
# Python / Flask — ReturnURL 接收（AIO 金流，Form POST + CheckMacValue SHA256）
import hmac, hashlib, urllib.parse
from flask import Flask, request

app = Flask(__name__)
HASH_KEY = 'pwFHCqoQZGmho4w6'
HASH_IV  = 'EkRm7iFT261dpevs'

def verify_check_mac_value(params: dict) -> bool:
    """ecpayUrlEncode：urlencode → strtolower → .NET 字元替換"""
    received = params.get('CheckMacValue', '')
    sorted_params = sorted(((k, v) for k, v in params.items() if k != 'CheckMacValue'), key=lambda x: x[0].lower())
    raw = f'HashKey={HASH_KEY}&' + '&'.join(f'{k}={v}' for k, v in sorted_params) + f'&HashIV={HASH_IV}'
    encoded = urllib.parse.quote_plus(raw).replace('~', '%7e').lower()
    for orig, repl in [('%2d','-'),('%5f','_'),('%2e','.'),('%21','!'),('%2a','*'),('%28','('),('%29',')')]:
        encoded = encoded.replace(orig, repl)
    computed = hashlib.sha256(encoded.encode()).hexdigest().upper()
    # ⚠️ 使用 timing-safe 比較，禁止用 == 或 !=
    return hmac.compare_digest(computed, received.upper())

@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    params = dict(request.form)
    # Form POST 值是 list，取第一個
    params = {k: v[0] if isinstance(v, list) else v for k, v in params.items()}

    if not verify_check_mac_value(params):
        # ⚠️ 仍需回 1|OK + HTTP 200，否則 ECPay 會重試（最多每日 4 次）
        # 安全建議：用 IP 白名單（綠界 IP 段）取代 HTTP 400 來防範偽造請求
        import logging; logging.error('ECPay callback CheckMacValue 驗證失敗')
        return '1|OK', 200, {'Content-Type': 'text/plain'}

    # ⚠️ AIO RtnCode 是字串 '1'（不是整數 1）
    if params.get('RtnCode') == '1':
        trade_no = params['MerchantTradeNo']
        print(f'[ReturnURL] ✅ 付款成功 訂單={trade_no}')
        # TODO: 更新資料庫訂單狀態為「已付款」
    else:
        print(f'[ReturnURL] ❌ 付款失敗 RtnCode={params.get("RtnCode")}')

    return '1|OK', 200, {'Content-Type': 'text/plain'}  # ⚠️ 必須回應純文字 1|OK
```

```javascript
// Node.js / Express — ReturnURL 接收（AIO 金流，Form POST + CheckMacValue SHA256）
const express = require('express');
const crypto  = require('crypto');

const app = express();
app.use(express.urlencoded({ extended: true }));

const HASH_KEY = 'pwFHCqoQZGmho4w6';
const HASH_IV  = 'EkRm7iFT261dpevs';

function verifyCheckMacValue(params) {
  const { CheckMacValue: received, ...rest } = params;
  const sorted = Object.entries(rest).sort(([a], [b]) => a.toLowerCase().localeCompare(b.toLowerCase()));
  let raw = `HashKey=${HASH_KEY}&` + sorted.map(([k,v]) => `${k}=${v}`).join('&') + `&HashIV=${HASH_IV}`;
  // ecpayUrlEncode：encodeURIComponent → %20→+ → ~→%7e → '→%27 → 轉小寫 → .NET 字元替換
  let encoded = encodeURIComponent(raw).replace(/%20/g,'+').replace(/~/g,'%7e').replace(/'/g,'%27').toLowerCase()
    .replace(/%2d/g,'-').replace(/%5f/g,'_').replace(/%2e/g,'.')
    .replace(/%21/g,'!').replace(/%2a/g,'*').replace(/%28/g,'(').replace(/%29/g,')');
  const computed = crypto.createHash('sha256').update(encoded).digest('hex').toUpperCase();
  // ⚠️ timing-safe 比較
  const bufA = Buffer.from(computed), bufB = Buffer.from((received || '').toUpperCase());
  if (bufA.length !== bufB.length) return false;
  return crypto.timingSafeEqual(bufA, bufB);
}

app.post('/ecpay/notify', (req, res) => {
  if (!verifyCheckMacValue(req.body)) {
    // ⚠️ 仍需回 1|OK + HTTP 200，否則 ECPay 會重試
    console.error('[ECPay] callback CheckMacValue 驗證失敗');
    return res.type('text').send('1|OK');
  }

  // ⚠️ AIO RtnCode 是字串 '1'（不是整數 1）
  if (req.body.RtnCode === '1') {
    console.log('[ReturnURL] ✅ 付款成功 訂單=', req.body.MerchantTradeNo);
    // TODO: 更新資料庫訂單狀態
  } else {
    console.log('[ReturnURL] ❌ 付款失敗 RtnCode=', req.body.RtnCode);
  }
  res.type('text').send('1|OK');  // ⚠️ 必須回應純文字 1|OK
});
```

> 🔍 **此步驟失敗？**
>
> | 症狀 | 最可能原因 |
> |------|----------|
> | ReturnURL 完全沒有被呼叫 | ReturnURL 不可公開訪問（localhost 無效） |
> | CheckMacValue 驗證失敗 | URL encode 順序問題，見 [guides/13 §URL encode](./13-checkmacvalue.md) |
> | 同一筆收到多次通知 | 沒有回應 `1|OK`，綠界觸發重試 |
> | RtnCode ≠ 1 | 查 [guides/21 §AIO 錯誤碼](./21-error-codes-reference.md) |

---

### 步驟 4：（可選）主動查詢訂單狀態

> 僅在 ReturnURL 未正常收到通知時使用；ATM / 超商等非即時付款方式必用。

```php
$postService = $factory->create('PostWithCmvVerifiedEncodedStrResponseService');
$result = $postService->post([
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => $tradeNo,
    'TimeStamp'       => time(),
], 'https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5');
// $result['TradeStatus'] === '1' 表示已付款
```

---

### 首次串接常見失誤

| 錯誤 | 解法 |
|------|------|
| CheckMacValue 一直對不上 | 確認 URL encode 使用 `ecpayUrlEncode`（urlencode→strtolower→.NET替換），詳見 [guides/13](./13-checkmacvalue.md) |
| ReturnURL 沒收到通知 | 用 ngrok 或部署到公開主機；確認防火牆未擋 ECPay IP |
| 付款成功但重複收到通知 | ReturnURL handler 必須 echo `1|OK` |
| 非 PHP 語言接 Callback | 讀取 Form POST body，非 JSON body |
| ATM/CVS/Barcode 選項出現後等不到 ReturnURL | **ATM/CVS Callback 為非同步** — ReturnURL 只有在消費者**實際到 ATM/超商繳費完成後**才觸發。建單後消費者在 ECPay 付款頁選擇 ATM/CVS，`PaymentInfoURL`（若有設定）會收到取號成功通知，再等消費者繳費後 `ReturnURL` 才到 |
| ATM/CVS/Barcode `PaymentInfoURL` 收到 RtnCode=2 或 10100073，誤以為失敗 | 這是**正常取號成功碼**（消費者尚未繳費）。從 `PaymentInfoURL` callback 的 Form POST 中取出 `BankCode`+`vAccount`（ATM）或 `PaymentNo`（CVS）顯示給消費者，參見本文件 §ATM/CVS/BARCODE 取號通知 |

---

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式 | CMV-SHA256 — 詳見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/x-www-form-urlencoded` |
| 認證 | CheckMacValue（SHA256） — 詳見 [guides/13-checkmacvalue.md](./13-checkmacvalue.md) |
| 正式環境 | `https://payment.ecpay.com.tw` |
| 測試環境 | `https://payment-stage.ecpay.com.tw` |
| 建單回應 | HTML 頁面（瀏覽器重導至綠界付款頁） |
| 查詢回應 | URL-encoded 字串 或 JSON（依端點不同） |
| Callback | Form POST 至 ReturnURL，必須回應 `1|OK` |
| Timestamp 有效期 | 查詢 API: 3 分鐘 |
| 重試機制 | 每 5-15 分鐘，每日最多 4 次 |

### 端點 URL 一覽

| 功能 | 端點路徑 | 完整正式環境 URL | 回應格式 |
|------|---------|----------------|---------|
| 建立訂單 | `/Cashier/AioCheckOut/V5` | `https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5` | HTML（重導） |
| 查詢訂單 | `/Cashier/QueryTradeInfo/V5` | `https://payment.ecpay.com.tw/Cashier/QueryTradeInfo/V5` | URL-encoded |
| 信用卡請退款 | `/CreditDetail/DoAction`（⚠️ Stage 環境不可用） | `https://payment.ecpay.com.tw/CreditDetail/DoAction` | URL-encoded |
| 信用卡明細查詢 | `/CreditDetail/QueryTrade/V2` | — | JSON |
| 定期定額查詢 | `/Cashier/QueryCreditCardPeriodInfo` | — | JSON |
| 取號結果查詢 | `/Cashier/QueryPaymentInfo` | — | URL-encoded |
| 定期定額作業 | `/Cashier/CreditCardPeriodAction` | — | URL-encoded |
| 對帳檔下載 | `/PaymentMedia/TradeNoAio` | text |
| 信用卡撥款對帳 | `/CreditDetail/FundingReconDetail` | text |

> ⚠️ **對帳端點 Domain 差異**：`/PaymentMedia/TradeNoAio` 使用 `vendor-stage.ecpay.com.tw`（正式：`vendor.ecpay.com.tw`），與其他 AIO 端點的 `payment-stage.ecpay.com.tw` **不同**。打錯 domain 會收到 404。

## 整合流程

```
你的網站                          綠界
  │                               │
  ├─ POST 訂單 ──────────────────→│ /Cashier/AioCheckOut/V5
  │                               │
  │                               ├─ 消費者在綠界頁面付款
  │                               │
  │←─ POST 付款結果（ReturnURL）──┤ Server-to-Server
  │                               │
  │  消費者瀏覽器 ←─ 導回 ────────┤ ClientBackURL
```

> ⚠️ **絕對禁止 iframe**：不可用 `<iframe>` 嵌入綠界付款頁。現代瀏覽器的 `X-Frame-Options` / CSP 策略會完全封鎖，導致付款頁無法顯示或消費者卡在空白頁。必須使用**全頁跳轉**（redirect）或新分頁開啟。

> ⚠️ **LINE / Facebook in-app WebView 會造成付款失敗**：LINE App、Facebook App 的內嵌瀏覽器（in-app browser）會攔截綠界付款頁的導向，造成付款失敗或卡在空白頁。遇到此問題需引導消費者改用外部瀏覽器（Safari / Chrome）開啟連結後再操作。可透過 User-Agent 偵測並顯示「請用外部瀏覽器開啟」提示。

## AIO 共用必填參數

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/全方位金流API技術文件.md`
> 以下參數表僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

> 從所有 `scripts/SDK_PHP/example/Payment/Aio/*.php` 交集提取

| 參數 | 類型 | 長度 | 說明 | 範例值 |
|------|------|------|------|--------|
| MerchantID | String | 10 | 特店編號 | 3002607 |
| MerchantTradeNo | String | 20 | 特店交易編號（不可重複，建議含時間戳） | Test1709123456 |
| MerchantTradeDate | String | 20 | 交易時間 `yyyy/MM/dd HH:mm:ss` | 2025/01/01 12:00:00 |
| PaymentType | String | 20 | 固定值 | aio |
| TotalAmount | Int | — | 交易金額（新台幣整數） | 100 |
| TradeDesc | String | 200 | 交易描述（需 URL encode） | 測試交易 |
| ItemName | String | 400 | 商品名稱（多項用 `#` 分隔） | 商品A#商品B |
| ReturnURL | String | 200 | 付款結果通知 URL（Server 端） | https://你的網站/ecpay/notify |
| ChoosePayment | String | 20 | 付款方式 | ALL / Credit / ATM / CVS 等 |
| EncryptType | Int | — | 固定值 1（SHA256） | 1 |
| CheckMacValue | String | — | 檢查碼（SDK 自動產生） | — |

### 選用參數

| 參數 | 型別 | 長度 | 說明 |
|------|------|------|------|
| ClientBackURL | String | 200 | 消費者付款完成後導回的網址（前端）|
| OrderResultURL | String | 200 | 付款完成後導向並帶回結果的網址 |
| NeedExtraPaidInfo | String(1) | 1 | 是否需要額外付款資訊（Y/N）|
| IgnorePayment | String | 100 | 排除的付款方式（用 `#` 分隔）|
| PlatformID | String | 10 | 平台商編號（平台商代收代付模式，一般商店不需填寫）|
| CustomField1~4 | String | 50 | 自訂欄位 |
| Language | String | 3 | 語言（CHT/ENG/KOR/JPN/CHI）|
| StoreID | String | 10 | 門市代號 |

> ⚠️ **三個 URL 用途不同，不可設為同一 URL**：
> - `ReturnURL`（必填）— **Server-to-Server POST**，由綠界後台直接呼叫你的伺服器，消費者看不到此請求。必須回應 `1|OK`，否則觸發重試。
> - `OrderResultURL`（選填）— **瀏覽器導向**，付款完成後消費者瀏覽器被帶回你的網站，並以 Form POST 帶上付款結果。**不需要回應 `1|OK`**，直接顯示結果頁面給消費者即可。
> - `ClientBackURL`（選填）— 消費者在綠界付款頁**主動按「取消/返回」**時的導回網址，不含付款結果。
>
> 混用這三個 URL 是最常見的串接錯誤之一。若同時設定 `ReturnURL` 和 `OrderResultURL`，兩者都會被呼叫（一個 Server 端、一個前端）。

### MerchantTradeNo 注意事項
- **不可重複**，重複會回傳錯誤
- 建議格式：前綴 + 時間戳，如 `'Test' . time()`
- 最長 20 字元，僅允許英數字

## 各付款方式專用參數

| 付款方式 | ChoosePayment | 專用參數 | 金額限制 | 範例檔案 |
|---------|--------------|---------|---------|---------|
| 全部 | ALL | — | — | CreateOrder.php |
| 信用卡 | Credit | Redeem, UnionPay, BindingCard | — | CreateCreditOrder.php |
| 分期 | Credit | CreditInstallment=3,6,18 | — | CreateInstallmentOrder.php |
| 定期定額 | Credit | PeriodAmount,PeriodType,Frequency,ExecTimes,PeriodReturnURL | — | CreatePeriodicOrder.php |
| ATM | ATM | ExpireDate=7（天） | — | CreateAtmOrder.php |
| 超商代碼 | CVS | StoreExpireDate=4320（分鐘）,Desc_1~4,PaymentInfoURL | — | CreateCvsOrder.php |
| 條碼 | BARCODE | StoreExpireDate=5（天）,Desc_1~4,PaymentInfoURL | — | CreateBarcodeOrder.php |
| WebATM | WebATM | — | — | CreateWebAtmOrder.php |
| TWQR | TWQR | — | — | CreateTwqrOrder.php |
| BNPL | BNPL | — | ≥3000 | CreateBnplOrder.php |
| Apple Pay | ApplePay | — | — | —（更完整的 Apple Pay 整合見 [guides/02 §Apple Pay 前置準備](./02-payment-ecpg.md)）|
| 綠界PAY | ECPay | — | — | —（API 規格見 `references/Payment/全方位金流API技術文件.md`）|
| 微信 | WeiXin | — | — | CreateWeiXinOrder.php |

> **分期期數說明**：AIO 支援的分期期數：3, 6, 12, 18, 24, 30 期（依合約而定）。實際可用期數以商店後台設定和信用卡銀行合約為準。
>
> **消費者自費分期**：除了商家吸收手續費的一般分期外，ECPay 也支援「消費者自費分期」，由消費者自行負擔分期手續費。需另外向綠界申請啟用，啟用後可透過 `CreditInstallment` 參數設定。詳見官方文件：`references/Payment/全方位金流API技術文件.md` → 消費者自費分期。

**端點**：`POST https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`

**SDK 用法**：`$factory->create('AutoSubmitFormWithCmvService')`

### 信用卡範例

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreateCreditOrder.php`

```php
$input = [
    'MerchantID'       => '3002607',
    'MerchantTradeNo'  => 'Test' . time(),
    'MerchantTradeDate'=> date('Y/m/d H:i:s'),
    'PaymentType'      => 'aio',
    'TotalAmount'      => 100,
    'TradeDesc'        => UrlService::ecpayUrlEncode('測試交易'),  // 官方 SDK 範例做法，見 scripts/SDK_PHP/example/Payment/Aio/CreateCreditOrder.php
    'ItemName'         => '測試商品',
    'ReturnURL'        => 'https://你的網站/ecpay/notify',
    'ChoosePayment'    => 'Credit',
    'EncryptType'      => 1,
];
echo $autoSubmitFormService->generate($input, $actionUrl);
```

### ATM 範例

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreateAtmOrder.php`

```php
$input = [
    'MerchantID'       => '3002607',
    'MerchantTradeNo'  => 'Test' . time(),
    'MerchantTradeDate'=> date('Y/m/d H:i:s'),
    'PaymentType'      => 'aio',
    'TotalAmount'      => 100,
    'TradeDesc'        => UrlService::ecpayUrlEncode('交易描述範例'),
    'ItemName'         => '範例商品一批 100 TWD x 1',
    'ReturnURL'        => 'https://你的網站/ecpay/notify',
    'ChoosePayment'    => 'ATM',
    'EncryptType'      => 1,
    'ExpireDate'       => 7,              // ATM 繳費期限（天）
    'PaymentInfoURL'   => 'https://你的網站/ecpay/payment-info',  // 取號結果通知 URL
];
echo $autoSubmitFormService->generate($input, $actionUrl);
```

> **PaymentInfoURL vs ReturnURL**：ATM 付款是非同步流程。`PaymentInfoURL` 接收取號結果（RtnCode=2），`ReturnURL` 接收實際付款結果（RtnCode=1）。

### 超商代碼（CVS）範例

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreateCvsOrder.php`

```php
$input = [
    'MerchantID'       => '3002607',
    'MerchantTradeNo'  => 'Test' . time(),
    'MerchantTradeDate'=> date('Y/m/d H:i:s'),
    'PaymentType'      => 'aio',
    'TotalAmount'      => 100,
    'TradeDesc'        => UrlService::ecpayUrlEncode('交易描述範例'),
    'ItemName'         => '範例商品一批 100 TWD x 1',
    'ReturnURL'        => 'https://你的網站/ecpay/notify',
    'ChoosePayment'    => 'CVS',
    'EncryptType'      => 1,
    'StoreExpireDate'  => 4320,         // 繳費期限（分鐘）= 3天
    'Desc_1'           => '範例交易描述 1',
    'Desc_2'           => '範例交易描述 2',
    'Desc_3'           => '範例交易描述 3',
    'Desc_4'           => '範例交易描述 4',
    'PaymentInfoURL'   => 'https://你的網站/ecpay/payment-info',  // 取號結果通知 URL
];
echo $autoSubmitFormService->generate($input, $actionUrl);
```

### 條碼（BARCODE）範例

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreateBarcodeOrder.php`

```php
$input = [
    'MerchantID'       => '3002607',
    'MerchantTradeNo'  => 'Test' . time(),
    'MerchantTradeDate'=> date('Y/m/d H:i:s'),
    'PaymentType'      => 'aio',
    'TotalAmount'      => 100,
    'TradeDesc'        => UrlService::ecpayUrlEncode('交易描述範例'),
    'ItemName'         => '範例商品一批 100 TWD x 1',
    'ReturnURL'        => 'https://你的網站/ecpay/notify',
    'ChoosePayment'    => 'BARCODE',
    'EncryptType'      => 1,
    'StoreExpireDate'  => 5,            // 繳費期限（天）
    'Desc_1'           => '範例交易描述 1',
    'Desc_2'           => '範例交易描述 2',
    'Desc_3'           => '範例交易描述 3',
    'Desc_4'           => '範例交易描述 4',
    'PaymentInfoURL'   => 'https://你的網站/ecpay/payment-info',  // 取號結果通知 URL
];
echo $autoSubmitFormService->generate($input, $actionUrl);
```

### 分期範例

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreateInstallmentOrder.php`

```php
$input = [
    // 共用參數見「信用卡範例」（上方 MerchantID ~ EncryptType）
    'ChoosePayment'     => 'Credit',
    'CreditInstallment' => '3,6,18',  // 可分 3/6/18 期
    'EncryptType'       => 1,
];
```

### BNPL 範例

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreateBnplOrder.php`

```php
$input = [
    // 共用參數見「信用卡範例」（上方 MerchantID ~ EncryptType）
    'TotalAmount'      => 3000,        // BNPL 最低 3000 元
    'ChoosePayment'    => 'BNPL',
    'EncryptType'      => 1,
];
```

### TWQR 範例

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreateTwqrOrder.php`

```php
$input = [
    // 共用參數見「信用卡範例」（上方 MerchantID ~ EncryptType）
    'ChoosePayment'    => 'TWQR',
    'EncryptType'      => 1,
];
```

### 微信支付範例

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreateWeiXinOrder.php`

```php
$input = [
    // 共用參數見「信用卡範例」（上方 MerchantID ~ EncryptType）
    'ChoosePayment'    => 'WeiXin',
    'EncryptType'      => 1,
];
```

## 付款結果通知（ReturnURL）

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/GetCheckoutResponse.php`

綠界會 POST 以下欄位到你的 ReturnURL：

| 欄位 | 說明 |
|------|------|
| MerchantID | 特店編號 |
| MerchantTradeNo | 特店交易編號 |
| RtnCode | 交易狀態碼（**1=成功**） |
| RtnMsg | 交易訊息 |
| TradeNo | 綠界交易編號 |
| TradeAmt | 交易金額 |
| PaymentDate | 付款時間 |
| PaymentType | 付款方式 |
| PaymentTypeChargeFee | 手續費 |
| TradeDate | 交易日期 |
| SimulatePaid | 是否為模擬付款（0=否, 1=是） |
| CheckMacValue | 檢查碼 |

> ⚠️ **ATM/CVS/BARCODE 取號通知走 `PaymentInfoURL`，不是 `ReturnURL`**
>
> | RtnCode | 通知端點 | 意義 |
> |:-------:|---------|------|
> | 1 | ReturnURL | 付款成功（信用卡/WebATM/TWQR 等即時付款） |
> | 2 | **PaymentInfoURL** | ATM 取號成功（消費者**尚未繳費**，等待第二次 RtnCode=1）|
> | 10100073 | **PaymentInfoURL** | CVS/BARCODE 取號成功（同上，等待消費者繳費）|
>
> - RtnCode=2 和 10100073 發到 `PaymentInfoURL`（若未設定則 fallback 到 `ReturnURL`）。
> - **不要把 RtnCode=2 或 10100073 當作錯誤！** 這代表取號成功，消費者會在期限內繳費。

### 各付款方式額外回傳參數

Callback 除了基本欄位外，各付款方式會額外回傳（需設定 `NeedExtraPaidInfo=Y`）：

| 付款方式 | 額外欄位 | 說明 |
|---------|---------|------|
| 信用卡 | `card4no`, `card6no`, `auth_code` | 卡號末四碼、前六碼、授權碼 |
| 信用卡（分期） | `stage`, `stast`, `staed` | 分期期數、頭期金額、各期金額 |
| 信用卡（紅利） | `red_dan`, `red_de_amt`, `red_ok_amt`, `red_yet` | 紅利扣點、折抵金額、實際扣款、剩餘點數 |
| ATM | `ATMAccBank`, `ATMAccNo` | 付款人銀行代碼、帳號末五碼 |
| WebATM | `WebATMAccBank`, `WebATMAccNo`, `WebATMBankName` | 銀行代碼、帳號末五碼、銀行名稱 |
| 超商代碼 | `PaymentNo`, `PayFrom` | 繳費代碼、繳費超商 |
| 超商條碼 | `PayFrom` | 繳費超商 |
| TWQR | `TWQRTradeNo` | 行動支付交易編號 |

> **注意**：額外回傳的參數**全部都需要加入 CheckMacValue 計算**。完整欄位清單見 `references/Payment/全方位金流API技術文件.md` → 額外回傳的參數。

### 驗證流程

```php
$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$checkoutResponse = $factory->create(VerifiedArrayResponse::class);
$result = $checkoutResponse->get($_POST);  // 自動驗證 CheckMacValue

if ($result['RtnCode'] === '1') {
    if ($result['SimulatePaid'] === '0') {
        // 真實付款，處理訂單
    }
}
echo '1|OK';  // 必須回應
```

### ReturnURL 重要限制
- 必須回應純字串 `1|OK`
- 不可放在 CDN 後面
- 僅支援 80/443 埠
- 非 ASCII 域名需用 punycode
- TLS 1.2 必須
- 不可含特殊字元（分號、管道、反引號）
- 重送機制：每 5-15 分鐘重送，每天最多 4 次
- **10 秒超時**：耗時操作（開發票、建物流單、發通知信）需放入非同步佇列，見 [guides/23](./23-performance-scaling.md) §Webhook 佇列架構

#### ReturnURL Handler 效能核查清單

> ECPay 要求 ReturnURL 必須在 **10 秒內**回應 `1|OK`，逾時視為失敗並重試。

- [ ] Handler 執行時間 < 1 秒（保留 9 秒餘量）
- [ ] 無外部 HTTP 呼叫（若必要，設定 timeout ≤ 3 秒）
- [ ] 資料庫操作僅 INSERT/UPDATE（避免複雜 JOIN 查詢）
- [ ] 耗時操作（發信、開發票）放入非同步佇列，非同步處理
- [ ] 先回應 `1|OK`，再執行後續業務邏輯

詳細效能設計見 [guides/23 §效能調校](./23-performance-scaling.md)。

> 🔍 **ReturnURL 整體失敗？** ①確認 URL 公開可訪問（非 localhost）；② CheckMacValue 驗證失敗→見 [guides/13](./13-checkmacvalue.md)；③多次收到相同通知→確認已回應 `1|OK`；④完全沒收到→查 [guides/15 §AIO ReturnURL 沒收到](./15-troubleshooting.md)。

## ATM/CVS/BARCODE 取號通知（PaymentInfoURL）

ATM/CVS/BARCODE 付款是**非同步流程**：建立訂單 → 取得繳費資訊 → 消費者去繳費 → 付款完成通知。

取號成功的 RtnCode **不是 1**：
- ATM 取號成功：`RtnCode=2`
- CVS 取號成功：`RtnCode=10100073`
- BARCODE 取號成功：`RtnCode=10100073`

PaymentInfoURL 回呼欄位：

| 付款方式 | 額外欄位 |
|---------|---------|
| ATM | BankCode（銀行代碼）, vAccount（虛擬帳號）, ExpireDate（繳費期限）|
| CVS | PaymentNo（繳費代碼）, ExpireDate |
| BARCODE | Barcode1, Barcode2, Barcode3, ExpireDate |

### 查詢付款資訊

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/QueryPaymentInfo.php`

```php
$postService = $factory->create('PostWithCmvVerifiedEncodedStrResponseService');
$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => '你的訂單編號',
    'TimeStamp'       => time(),
];
$response = $postService->post(
    $input,
    'https://payment-stage.ecpay.com.tw/Cashier/QueryPaymentInfo'
);
```

## 定期定額（訂閱制）

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreatePeriodicOrder.php`

### 建立定期定額訂單

```php
$input = [
    'MerchantID'       => '3002607',
    'MerchantTradeNo'  => 'Sub' . time(),
    'MerchantTradeDate'=> date('Y/m/d H:i:s'),
    'PaymentType'      => 'aio',
    'TotalAmount'      => 299,
    'TradeDesc'        => '月訂閱方案',
    'ItemName'         => '月訂閱 x1',
    'ReturnURL'        => 'https://你的網站/ecpay/notify',
    'ChoosePayment'    => 'Credit',
    'EncryptType'      => 1,
    'PeriodAmount'     => 299,     // 每期金額
    'PeriodType'       => 'M',     // D=天, M=月, Y=年
    'Frequency'        => 1,       // 每 1 個月執行一次
    'ExecTimes'        => 12,      // 共執行 12 次
    'PeriodReturnURL'  => 'https://你的網站/ecpay/period-notify',
];
echo $autoSubmitFormService->generate(
    $input,
    'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5'
);
```

### PeriodReturnURL 每期通知

每次扣款完成後，綠界會 POST 到 `PeriodReturnURL`，包含：
- RtnCode（1=成功）
- PeriodType、Frequency、ExecTimes
- TotalSuccessTimes（已成功次數）
- TotalSuccessAmount（已成功金額）

### 定期定額管理

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/CreditCardPeriodAction.php`

```php
$postService = $factory->create('PostWithCmvEncodedStrResponseService');
$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => '你的訂閱訂單編號',
    'Action'          => 'Cancel',  // Cancel=取消, ReAuth=重新授權（⚠️ ReAuth 在 Stage 環境無法測試）
    'TimeStamp'       => time(),
];
$response = $postService->post(
    $input,
    'https://payment-stage.ecpay.com.tw/Cashier/CreditCardPeriodAction'
);
```

### 查詢定期定額

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/QueryPeridicTrade.php`

```php
$postService = $factory->create('PostWithCmvJsonResponseService');
$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => '你的訂閱訂單編號',
    'TimeStamp'       => time(),
];
$response = $postService->post(
    $input,
    'https://payment-stage.ecpay.com.tw/Cashier/QueryCreditCardPeriodInfo'
);
```

#### 定期定額失敗重試機制

| 失敗次數 | 綠界行為 | 建議商家動作 |
|:-------:|---------|-----------|
| 1-3 次 | 自動重試（間隔 3-5 天） | 監控，無需介入 |
| 4-5 次 | 自動重試（間隔延長） | 通知消費者更新付款資訊 |
| **6 次** | **自動取消合約** | 通知消費者重新訂閱 |

> 連續扣款失敗 6 次後，綠界將自動終止該定期定額合約。
> 商家應在第 3 次失敗時主動通知消費者，避免合約被取消。
> 扣款結果通知至 `PeriodReturnURL`。

## 信用卡請款 / 退款 / 取消

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/Capture.php`

```php
$postService = $factory->create('PostWithCmvEncodedStrResponseService');
$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => '你的訂單編號',
    'TradeNo'         => '綠界交易編號',
    'Action'          => 'C',          // C=請款, R=退款, E=取消授權, N=放棄
    'TotalAmount'     => 100,
];

try {
    // ⚠️ DoAction 在測試環境（payment-stage）不可用，請換用正式環境 URL
    $response = $postService->post(
        $input,
        'https://payment.ecpay.com.tw/CreditDetail/DoAction'  // 僅正式環境有效
    );
    // 回應格式：RtnCode|RtnMsg，例如 "1|OK"
} catch (\Exception $e) {
    error_log('ECPay Capture Error: ' . $e->getMessage());
    // 依業務需求處理（通知管理員、重試等）
}
```

| Action | 說明 |
|--------|------|
| C | 請款（關帳） |
| R | 退款（可部分退款） |
| E | 取消授權 |
| N | 放棄（取消請款） |

### 部分退款範例

`Action=R` 時，`TotalAmount` 填入**欲退款的金額**（非原訂單金額）。同一筆訂單可多次部分退款，累計退款金額不得超過原交易金額。

```php
$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => '你的訂單編號',
    'TradeNo'         => '綠界交易編號',
    'Action'          => 'R',
    'TotalAmount'     => 50,  // 退款 50 元（原交易 100 元）
];
$response = $postService->post(
    $input,
    'https://payment.ecpay.com.tw/CreditDetail/DoAction'  // ⚠️ DoAction 僅正式環境，測試環境不可用
);
```

### 退款注意事項

- **已關帳（已請款）**：`Action=R` 可退款，`TotalAmount` 填退款金額。支援多次部分退款，累計不得超過原交易金額
- **未關帳（未請款）**：僅能取消授權（`Action=E`）或放棄（`Action=N`），不支援部分取消
- 退款後無法復原，請確認金額正確再執行
- **⚠️ DoAction 僅適用於信用卡**：ATM、超商代碼、條碼付款為消費者臨櫃/轉帳付現，不支援線上退款 API。這些付款方式需透過綠界商家後台人工處理或聯繫綠界客服

> 完整退款參數規格請 web_fetch `references/Payment/全方位金流API技術文件.md` 中「信用卡請退款功能」對應 URL。

> 🔍 **DoAction 失敗？** ①確認 `TradeNo` 是綠界給的，非你自己的 `MerchantTradeNo`；② `Action=R` 要求交易已關帳（請款）；③ ATM/CVS 付款不支援此 API，需人工退款。

## 查詢訂單

### 一般查詢

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/QueryTrade.php`

```php
$postService = $factory->create('PostWithCmvVerifiedEncodedStrResponseService');
$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => '你的訂單編號',
    'TimeStamp'       => time(),
];

try {
    $response = $postService->post(
        $input,
        'https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5'
    );
    // $response 為 key=value 格式字串，已自動驗證 CheckMacValue
} catch (\Exception $e) {
    error_log('ECPay QueryTrade Error: ' . $e->getMessage());
}
```

> 🔍 **查詢失敗？** ①確認 `TimeStamp` 是 Unix 秒（非毫秒）；② CheckMacValue 驗證失敗→確認 HashKey/HashIV 正確；③ `TradeStatus` 回傳 `10200047`→訂單不存在，確認 MerchantTradeNo。

### PaymentType 回覆值對照

查詢訂單或 Callback 中的 `PaymentType` 欄位，常見回覆值：

| 回覆值 | 付款方式 |
|--------|---------|
| `Credit_CreditCard` | 信用卡（一般） |
| `Flexible_Installment` | 永豐 30 期分期 |
| `TWQR_OPAY` | TWQR 行動支付 |
| `ATM_BOT` | ATM 台灣銀行 |
| `ATM_CHINATRUST` | ATM 中國信託 |
| `ATM_FIRST` | ATM 第一銀行 |
| `ATM_LAND` | ATM 土地銀行 |
| `ATM_CATHAY` | ATM 國泰世華 |
| `ATM_PANHSIN` | ATM 板信銀行 |
| `ATM_KGI` | ATM 凱基銀行 |
| `CVS_CVS` | 超商代碼 |
| `CVS_OK` | OK 超商代碼 |
| `CVS_FAMILY` | 全家超商代碼 |
| `CVS_HILIFE` | 萊爾富超商代碼 |
| `CVS_IBON` | 7-11 ibon 代碼 |
| `BARCODE_BARCODE` | 超商條碼 |
| `BNPL_URICH` | 裕富無卡分期 |
| `BNPL_ZINGALA` | 中租銀角零卡 |
| `DigitalPayment_Jkopay` | 街口支付 |
| `DigitalPayment_IPASS` | 一卡通 iPASS MONEY |

> 完整清單見 `references/Payment/全方位金流API技術文件.md` → 回覆付款方式一覽表。

### 信用卡交易查詢

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/QueryCreditTrade.php`

```php
$postService = $factory->create('PostWithCmvJsonResponseService');
$input = [
    'MerchantID'      => '3002607',
    'CreditRefundId'  => 13475885,    // 從 QueryTrade 取得的信用卡退款識別碼（整數）
    'CreditAmount'    => 100,
    'CreditCheckCode' => 62861749,    // 從 QueryTrade 取得的 ECPay 系統驗證碼（整數，非銀行授權碼）
];
$response = $postService->post(
    $input,
    'https://payment-stage.ecpay.com.tw/CreditDetail/QueryTrade/V2'
);
```

## 下載對帳檔

### AIO 對帳

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/DownloadReconcileCsv.php`

```php
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');
$input = [
    'MerchantID'    => '3002607',
    'DateType'      => '2',
    'BeginDate'     => '2025-01-01',
    'EndDate'       => '2025-01-31',
    'MediaFormated' => '0',
];
echo $autoSubmitFormService->generate(
    $input,
    'https://vendor-stage.ecpay.com.tw/PaymentMedia/TradeNoAio'
);
```

#### 對帳檔格式說明

> ⚠️ 對帳端點使用 `vendor(-stage).ecpay.com.tw`，與其他 AIO 端點不同。

對帳檔為 **CSV/TSV 純文字格式**，主要欄位：

| 欄位 | 說明 |
|------|------|
| MerchantTradeNo | 特店訂單編號 |
| TradeNo | 綠界交易編號 |
| TradeDate | 交易日期 |
| TradeAmt | 交易金額 |
| PaymentType | 付款方式 |
| HandlingCharge | 手續費 |
| PaymentDate | 撥款日期 |

> 對帳檔通常在 **T+1 營業日**生成。建議每日排程下載前一日對帳檔，比對本地訂單記錄。
> 信用卡撥款對帳使用另一端點 `/CreditDetail/FundingReconDetail`。

### 信用卡對帳

> 原始範例：`scripts/SDK_PHP/example/Payment/Aio/DownloadCreditReconcileCsv.php`

```php
$input = [
    'MerchantID'  => '3002607',
    'PayDateType' => 'close',
    'StartDate'   => '2025-01-01',
    'EndDate'     => '2025-01-31',
];
echo $autoSubmitFormService->generate(
    $input,
    'https://payment-stage.ecpay.com.tw/CreditDetail/FundingReconDetail'
);
```

## 完整範例檔案對照

| 檔案 | 用途 | SDK Service |
|------|------|-------------|
| CreateOrder.php | 全部付款 (ALL) | AutoSubmitFormWithCmvService |
| CreateCreditOrder.php | 信用卡 | AutoSubmitFormWithCmvService |
| CreateInstallmentOrder.php | 分期 | AutoSubmitFormWithCmvService |
| CreatePeriodicOrder.php | 定期定額 | AutoSubmitFormWithCmvService |
| CreateAtmOrder.php | ATM | AutoSubmitFormWithCmvService |
| CreateCvsOrder.php | 超商代碼 | AutoSubmitFormWithCmvService |
| CreateBarcodeOrder.php | 條碼 | AutoSubmitFormWithCmvService |
| CreateWebAtmOrder.php | WebATM | AutoSubmitFormWithCmvService |
| CreateTwqrOrder.php | TWQR | AutoSubmitFormWithCmvService |
| CreateBnplOrder.php | BNPL (≥3000) | AutoSubmitFormWithCmvService |
| CreateWeiXinOrder.php | 微信支付 | AutoSubmitFormWithCmvService |
| GetCheckoutResponse.php | 付款結果處理 | VerifiedArrayResponse |
| QueryTrade.php | 查詢訂單 | PostWithCmvVerifiedEncodedStrResponseService |
| QueryPaymentInfo.php | 查詢付款資訊 | PostWithCmvVerifiedEncodedStrResponseService |
| QueryCreditTrade.php | 信用卡交易查詢 | PostWithCmvJsonResponseService |
| QueryPeridicTrade.php | 定期定額查詢 | PostWithCmvJsonResponseService |
| Capture.php | 請款/退款/取消 | PostWithCmvEncodedStrResponseService |
| CreditCardPeriodAction.php | 定期定額管理 | PostWithCmvEncodedStrResponseService |
| DownloadReconcileCsv.php | AIO 對帳 | AutoSubmitFormWithCmvService |
| DownloadCreditReconcileCsv.php | 信用卡對帳 | AutoSubmitFormWithCmvService |

## ⚡ 完整可執行範例（Python Flask）

> **Python 開發者請複製此範例。** 單一檔案實作 AIO 信用卡付款完整流程：建立訂單 → 自動跳轉到綠界付款頁 → 接收 ReturnURL 通知。

```python
# pip install flask requests
# AIO 完整可執行範例 — 單一 Python 檔案，直接複製執行
import hashlib, time, urllib.parse, hmac
from flask import Flask, request, redirect

app = Flask(__name__)

MERCHANT_ID  = '3002607'
HASH_KEY     = 'pwFHCqoQZGmho4w6'
HASH_IV      = 'EkRm7iFT261dpevs'
PAYMENT_URL  = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5'

# ✅ 填入你的 ngrok 或可公開訪問的 URL
RETURN_URL   = 'https://你的網域/ecpay/notify'   # Server-to-Server Form POST（必填）

def ecpay_url_encode(s: str) -> str:
    """AIO CheckMacValue 專用 URL encode：urlencode → 全部小寫 → .NET 替換"""
    encoded = urllib.parse.quote_plus(s)
    encoded = encoded.lower()
    # PHP urlencode 會將 ~ 編為 %7e；Python quote_plus 不編 ~，需手動補
    encoded = encoded.replace('~', '%7e')
    # .NET HttpUtility.UrlEncode 的特殊字元處理
    encoded = encoded.replace('%2d', '-').replace('%5f', '_').replace('%2e', '.') \
                     .replace('%21', '!').replace('%2a', '*') \
                     .replace('%28', '(').replace('%29', ')')
    return encoded

def generate_cmv(params: dict) -> str:
    """計算 CheckMacValue（SHA256 大寫）"""
    # 移除 CheckMacValue 本身（PHP SDK 行為：不過濾空字串，空字串仍參與計算）
    filtered = {k: v for k, v in params.items() if v is not None and k != 'CheckMacValue'}
    # strcasecmp 排序（大小寫不敏感字母排序）
    sorted_items = sorted(filtered.items(), key=lambda x: x[0].lower())
    raw = '&'.join(f'{k}={v}' for k, v in sorted_items)
    raw = f'HashKey={HASH_KEY}&{raw}&HashIV={HASH_IV}'
    encoded = ecpay_url_encode(raw)
    return hashlib.sha256(encoded.encode('utf-8')).hexdigest().upper()

def verify_cmv(params: dict) -> bool:
    """timing-safe CheckMacValue 驗證（防止 timing attack）"""
    expected = generate_cmv(params)
    received = params.get('CheckMacValue', '')
    return hmac.compare_digest(expected.encode(), received.encode())

def build_auto_submit_form(params: dict, action: str) -> str:
    """產生自動提交的 HTML 表單，讓消費者瀏覽器 POST 到綠界"""
    params['CheckMacValue'] = generate_cmv(params)
    fields = '\n'.join(
        f'<input type="hidden" name="{k}" value="{v}">'
        for k, v in params.items()
    )
    return f'''<!DOCTYPE html>
<html><body>
<form id="f" method="post" action="{action}">{fields}</form>
<script>document.getElementById("f").submit();</script>
</body></html>'''

# ─────────────────────────── 建立訂單 → 跳轉到綠界 ───────────────────────
@app.route('/checkout')
def checkout():
    trade_no = 'AIO' + str(int(time.time()))   # 每次必須唯一（最長20字元）
    params = {
        'MerchantID':        MERCHANT_ID,
        'MerchantTradeNo':   trade_no,
        'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
        'PaymentType':       'aio',
        'TotalAmount':       '100',
        'TradeDesc':         '測試商品',
        'ItemName':          '測試商品x1',
        'ReturnURL':         RETURN_URL,
        'ChoosePayment':     'Credit',          # Credit / ATM / CVS / ALL
        'EncryptType':       1,                 # 1 = SHA256（整數）
    }
    return build_auto_submit_form(params, PAYMENT_URL)

# ─────────────────────────── ReturnURL：接收付款通知 ─────────────────────
@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    data = request.form.to_dict()

    # ① 驗證 CheckMacValue（使用 timing-safe 比較）
    if not verify_cmv(data):
        print('[ReturnURL] ❌ CheckMacValue 驗證失敗')
        return '1|OK', 200   # 仍回傳 1|OK，防止重送風暴

    rtn_code = data.get('RtnCode', '')
    trade_no = data.get('MerchantTradeNo', '')

    # ② 冪等性檢查（防止 ECPay 重試 4 次時重複處理）
    if is_already_processed(trade_no):
        return '1|OK', 200

    if rtn_code == '1':
        print(f'[ReturnURL] ✅ 付款成功 訂單={trade_no} 交易號={data.get("TradeNo")}')
        # TODO: db.mark_order_paid(trade_no)
    elif rtn_code == '2':
        print(f'[ReturnURL] 🏧 ATM 取號成功 訂單={trade_no}（等待消費者到 ATM 繳費）')
    elif rtn_code == '10100073':
        print(f'[ReturnURL] 🏪 CVS 取號成功 訂單={trade_no}')

    return '1|OK', 200, {'Content-Type': 'text/plain'}   # 必須回傳此字串

def is_already_processed(trade_no: str) -> bool:
    """冪等性檢查 — 替換為實際資料庫查詢"""
    return False   # TODO: return db.order_is_paid(trade_no)

if __name__ == '__main__':
    print(f'結帳頁面：http://localhost:5000/checkout')
    print(f'ReturnURL：{RETURN_URL}')
    app.run(port=5000, debug=True)
```

> **測試信用卡號**：`4311-9511-1111-1111` 或 `4311-9522-2222-2222`（免 3D 驗證）。
> **CheckMacValue 驗證**：可先參考 [guides/13-checkmacvalue.md](./13-checkmacvalue.md) 的測試向量確認你的 `generate_cmv` 實作正確。

## 參數邊界情況

| 參數 | 限制 | 說明 |
|------|------|------|
| MerchantTradeNo | 最大 20 字元 | 僅允許英數字，超過會被拒絕 |
| TotalAmount | 最小值 1 | 不可為 0，必須為正整數 |
| TradeDesc | 最大 200 字元 | 需 URL encode |
| ItemName | 最大 400 字元 | ⚠️ 超過 400 字元時系統會截斷；若截斷位置落在中文等多位元組字元中間，會造成 CheckMacValue 驗證失敗。建議送出前先用字元感知截斷（PHP: `mb_substr`，Node.js: 按字元而非 byte 截斷）。含 `#` 時需注意（`#` 是多品項分隔符號） |
| ItemName 含 `#` | 用於多品項分隔 | 若品名本身含 `#`，需 URL encode 為 `%23` |
| 金額一致性 | 必須 | `TotalAmount` 必須等於各 `ItemPrice × ItemCount` 的加總 |

## 生產等級 ReturnURL 處理

> ⚠️ **安全必做清單**
> 1. 驗證 MerchantID 為自己的
> 2. 比對金額與訂單記錄
> 3. 防重複處理（記錄已處理的 MerchantTradeNo）
> 4. 異常時仍回應 `1|OK`（避免重送風暴）
> 5. 記錄完整日誌（遮蔽 HashKey/HashIV）
> 6. CheckMacValue 驗證**必須**使用 timing-safe 比較函式（見 [guides/13](./13-checkmacvalue.md) §各語言實作），禁止使用 `==` 或 `===` 直接比對

```php
$factory = new Factory([
    'hashKey' => env('ECPAY_HASH_KEY'),
    'hashIv'  => env('ECPAY_HASH_IV'),
]);
$checkoutResponse = $factory->create(VerifiedArrayResponse::class);

try {
    $result = $checkoutResponse->get($_POST);

    // 1. 驗證 MerchantID 是否為自己的
    if ($result['MerchantID'] !== env('ECPAY_MERCHANT_ID')) {
        error_log('ECPay: MerchantID mismatch');
        echo '1|OK';
        return;
    }

    // 2. 比對金額與訂單記錄
    $order = findOrder($result['MerchantTradeNo']);
    if (!$order || (int)$result['TradeAmt'] !== $order->amount) {
        error_log('ECPay: Amount mismatch for ' . $result['MerchantTradeNo']);
        echo '1|OK';
        return;
    }

    // 3. 檢查 SimulatePaid（正式環境應為 '0'）
    if ($result['SimulatePaid'] !== '0') {
        error_log('ECPay: SimulatePaid detected in production');
        echo '1|OK';
        return;
    }

    // 4. 防重複處理（冪等性）
    if ($order->isPaid()) {
        echo '1|OK';
        return;
    }

    // 5. 處理付款結果
    if ($result['RtnCode'] === '1') {
        $order->markAsPaid($result['TradeNo']);
    }

    // 6. 記錄日誌（遮蔽敏感欄位）
    $logData = $result;
    unset($logData['CheckMacValue']);
    error_log('ECPay Payment: ' . json_encode($logData));

} catch (\Exception $e) {
    error_log('ECPay ReturnURL Error: ' . $e->getMessage());
}

// 無論成功或失敗，都必須回應 1|OK
echo '1|OK';
```

### CSRF 防護

AIO 表單是 POST 到 ECPay，不需要在 ECPay 端做 CSRF 保護。但你自己的「建立訂單」端點需要：

1. 在自己的「建立訂單」API 驗證 CSRF token
2. 驗證通過後才組裝參數並產生提交到 ECPay 的表單

### IP 白名單建議

建議 ReturnURL/PaymentInfoURL 端點檢查來源 IP，僅允許綠界伺服器回呼。可透過綠界客服索取回呼 IP 範圍。

### ReturnURL 重送機制

若未收到 `1|OK` 回應，綠界會在付款完成後的每 5-15 分鐘重送通知，每天最多重送 4 次。務必實作冪等性處理以避免重複入帳。

## 常見錯誤碼速查

| 錯誤碼 (RtnCode) | 含義 | 解決方式 |
|------------------|------|---------|
| 1 | 付款成功 | 正常處理訂單 |
| 2 | ATM 取號成功 | 等待消費者繳費，非最終結果 |
| 10100073 | CVS/BARCODE 取號成功 | 等待消費者繳費，非最終結果 |
| 10200095 | 交易已付款 | 重複付款，檢查訂單狀態 |
| 10200047 | MerchantTradeNo 重複 | 使用不同的訂單編號 |
| 10200073 | CheckMacValue 驗證失敗 | 檢查 HashKey/HashIV 和加密邏輯 |
| 10200115 | 信用卡授權逾時 | 請消費者重新付款 |
| 10200009 | 訂單已過期 | 檢查 ExpireDate 設定 |
| 10200058 | 信用卡授權失敗 | 請消費者確認卡片資訊 |
| 10300006 | 超商繳費期限已過 | 重新建立訂單 |
| 10100058 | ATM 繳費期限已過 | 重新建立訂單取號 |
| 10200050 | 金額不符 | 檢查 TotalAmount |
| 10100001 | 超商代碼已失效 | 重新取號 |
| 10200043 | 3D 驗證失敗 | 請消費者重試 |
| 10200105 | BNPL 金額未達最低 | TotalAmount 需 >= 3000 |

> 完整錯誤碼清單見 [guides/15-troubleshooting.md](./15-troubleshooting.md)

## 相關文件

- 官方 API 規格：`references/Payment/全方位金流API技術文件.md`（45 個 URL）
- CheckMacValue 解說：[guides/13-checkmacvalue.md](./13-checkmacvalue.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
