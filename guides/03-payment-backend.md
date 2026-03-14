> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 幕後授權 + 幕後取號指南

> **讀對指南了嗎？** 消費者需要看到付款介面 → [guides/01 AIO](./01-payment-aio.md) 或 [guides/02 站內付 2.0](./02-payment-ecpg.md)。需要 Token 綁卡快速付 → [guides/02 §綁卡](./02-payment-ecpg.md)。

## 概述

幕後 API 是純後台操作，消費者不需要看到付款頁面。適合 B2B、電話訂購、自動扣款等場景。
兩套 API 都使用 **AES 加密 + JSON 格式**（與站內付 2.0 相同的三層結構）。

## 何時使用幕後 API

| 場景 | 推薦方案 | 原因 |
|------|---------|------|
| 一般電商 | AIO 或站內付 2.0 | 消費者需要看到付款介面 |
| 電話訂購 | 信用卡幕後授權 | 客服代為輸入卡號 |
| 自動扣款 | 站內付 2.0 綁卡 | Token 模式更安全 |
| 背景產生繳費資訊 | 非信用卡幕後取號 | ATM/CVS 不需消費者互動即可產生 |
| 大型商戶 | 信用卡幕後授權 | 需 PCI DSS 認證 |

> **大多數開發者不需要幕後 API**。如果你的使用者會在網頁/App 上操作，請使用 [AIO](./01-payment-aio.md) 或[站內付 2.0](./02-payment-ecpg.md)。

> **注意**：本指南所有 PHP 程式碼範例為依據官方 API 文件手寫，未包含在 `scripts/SDK_PHP/example/` 官方範例中。程式碼已參照 `references/Payment/信用卡幕後授權API技術文件.md` 驗證，但建議在測試環境完整驗證後再部署正式環境。

## 前置需求

- MerchantID / HashKey / HashIV（測試帳號同站內付 2.0：3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs）
- SDK Service：`PostWithAesJsonResponseService`
- 加密方式：AES-128-CBC（詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md)）

```php
$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');
```

## 🚀 首次串接：最快成功路徑

> 幕後授權適合 **B2B / 電話訂購 / 平台代扣** 等需要直接傳遞卡號的場景。如果你的用戶需要自己輸入信用卡，請改用[站內付 2.0](./02-payment-ecpg.md)（更安全，無需 PCI-DSS 認證）。

### 前置確認清單

- [ ] ⚠️ **幕後授權需事先向綠界申請啟用**，且需通過 **PCI-DSS SAQ-D 認證**（最高合規等級）——若尚未認證，改用站內付 2.0 綁卡代扣更合適
- [ ] 確認使用哪個 API：信用卡幕後授權（需卡號）or 非信用卡幕後取號（ATM/CVS，不需卡號）
- [ ] 測試帳號同站內付 2.0：3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs
- [ ] AES-128-CBC 已實作（見 [guides/14](./14-aes-encryption.md)），三層 JSON 結構已了解

---

### 步驟 1：選擇 API 類型

| 你的需求 | 使用 API | 端點 |
|---------|---------|------|
| 有信用卡號（電話訂購/代扣） | 信用卡幕後授權 `/1.0.0/Cashier/BackAuth` | ecpayment domain |
| 要背景產生 ATM 虛擬帳號 | 非信用卡幕後取號 `/1.0.0/Cashier/GenPaymentCode`（ChoosePayment=ATM） | ecpayment domain |
| 要背景產生超商繳費代碼 | 非信用卡幕後取號 `/1.0.0/Cashier/GenPaymentCode`（ChoosePayment=CVS） | ecpayment domain |

---

### 步驟 2：發送 AES-JSON 請求

```php
// 信用卡幕後授權範例（需 PCI-DSS 認證，卡號不可儲存）
// ⚠️ Data 內必須使用巢狀物件：OrderInfo / CardInfo / ConsumerInfo
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'MerchantID'      => '3002607',
        'ChoosePayment'   => ['Credit' => []],        // 必填，固定格式
        'OrderInfo'       => [
            'MerchantTradeNo'   => 'Back' . time(),
            'MerchantTradeDate' => date('Y/m/d H:i:s'),
            'TotalAmount'       => 100,
            'TradeDesc'         => 'backend auth test',
            'ItemName'          => '測試商品',         // 必填
            'ReturnURL'         => 'https://你的網站/ecpay/backend-notify',
        ],
        'CardInfo'        => [
            'CardNo'       => '4311952222222222',  // 測試卡號（需 PCI-DSS 環境）
            'CardValidMM'  => '12',                // 信用卡有效月份（MM）
            'CardValidYY'  => '29',                // 信用卡有效年份（YY，末兩碼）
            'CardCVV2'     => '222',               // 背面末三碼
        ],
        'ConsumerInfo'    => [
            'Phone' => '0912345678',               // 必填
            'Name'  => '王大明',                   // 必填
        ],
    ],
];
$response = $postService->post($input, 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/BackAuth');
```

> 🔍 **此步驟失敗？**
>
> | 症狀 | 最可能原因 |
> |------|----------|
> | TransCode ≠ 1 | AES Key/IV 錯誤；Timestamp 格式錯誤（需 Unix 秒，非毫秒）|
> | RtnCode ≠ 1 | 卡號/有效期錯誤；帳號未申請幕後授權 |
> | RtnCode ≠ 1（非信用卡幕後） | `ChoosePayment` 填錯；`ATM` 或 `CVS` 對應不同 `ExpireDate` 欄位格式 |
> | 連線拒絕 / 404 | 確認 domain 為 `ecpayment-stage`（非 `payment-stage`）|
> | `MerchantTradeNo` 重複錯誤 | 每次請求需用新的唯一交易號 |
> | ⚠️ 不需要填 `Revision` | 幕後授權/取號不需要 `RqHeader.Revision`（不同於全方位物流的 `1.0.0`）|

---

### 步驟 3：處理 Callback 通知

```php
// ReturnURL / 通知 URL 接收（JSON POST，Content-Type: application/json）
$jsonBody = json_decode(file_get_contents('php://input'), true);
if (($jsonBody['TransCode'] ?? null) === 1) {
    // 需用 AesService 手動解密 Data（callback 不會自動解密）
    $aesService = $factory->create(\Ecpay\Sdk\Services\AesService::class);
    $data = $aesService->decrypt($jsonBody['Data']);
    if (($data['RtnCode'] ?? null) === 1) {
        // 授權成功，記錄 TradeNo 供後續請款/退款
        $tradeNo   = $data['MerchantTradeNo'];
        $ecpayNo   = $data['TradeNo'];          // 綠界交易號，請款/退款時使用
        $auth4     = $data['auth_code'];        // 授權碼
        $cardLast4 = $data['card4no'];          // 末 4 碼（記錄用）
    }
}
echo '1|OK';
```

**信用卡幕後授權成功 Callback（Data 解密後）**：
```json
{
  "RtnCode": 1,
  "RtnMsg": "授權成功",
  "MerchantTradeNo": "Back1234567890",
  "TradeNo": "2401011234567890",
  "PaymentType": "Credit_CreditCard",
  "TradeAmt": 100,
  "auth_code": "777777",
  "card4no": "2222",
  "gwsr": 11111111
}
```

> ⚠️ **非信用卡幕後取號（ATM/CVS）有兩個 Callback**：
> 1. **取號通知**（`PaymentInfoURL`）：綠界完成虛擬帳號/繳費代碼產生後立即觸發，包含帳號資訊
> 2. **付款通知**（`ReturnURL`）：消費者完成付款後觸發，包含付款結果
>
> 兩個 Callback 回應格式相同（JSON POST），都需回應 `1|OK`。

---

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式 | AES-JSON — 詳見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證 | AES-128-CBC 加密 Data 欄位 — 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| 測試環境 | `https://ecpayment-stage.ecpay.com.tw` |
| 正式環境 | `https://ecpayment.ecpay.com.tw` |
| 回應結構 | 三層 JSON（TransCode → 解密 Data → RtnCode） |
| Callback 回應 | 信用卡幕後授權：`1\|OK`（官方規格 45907.md）；非信用卡幕後取號：`1\|OK`（與 AIO 相同）— 詳見 [guides/22](./22-webhook-events-reference.md) |

### 端點 URL 一覽

#### 信用卡幕後授權

| 功能 | 端點路徑 |
|------|---------|
| 信用卡卡號授權 | `/1.0.0/Cashier/BackAuth` |
| 信用卡請退款 | `/1.0.0/Credit/DoAction` |
| 查詢訂單 | `/1.0.0/Cashier/QueryTrade` |
| 查詢發卡行 | `/1.0.0/Cashier/QueryCardInfo` |
| 信用卡明細查詢 | `/1.0.0/CreditDetail/QueryTrade` |
| 定期定額查詢 | `/1.0.0/Cashier/QueryTrade` |
| 定期定額作業 | `/1.0.0/Cashier/CreditCardPeriodAction` |
| 撥款對帳下載 | `/1.0.0/Cashier/QueryTradeMedia` |

#### 非信用卡幕後取號

| 功能 | 端點路徑 |
|------|---------|
| 產生繳費代碼 | `/1.0.0/Cashier/GenPaymentCode` |
| 查詢訂單 | `/1.0.0/Cashier/QueryTrade` |
| 取號結果查詢 | `/1.0.0/Cashier/QueryPaymentInfo` |
| 超商條碼查詢 | `/1.0.0/Cashier/QueryCVSBarcode` |

## 信用卡幕後授權

### 重要前提

- **需要 PCI DSS 認證**：你的伺服器會直接處理信用卡卡號
- 適合大型商戶、電話訂購中心
- 一般電商建議使用 AIO 或站內付 2.0

### 整合流程

```
你的伺服器 → AES 加密卡號等資料
            → POST JSON 到綠界幕後授權端點
            → 綠界回傳授權結果（AES 加密）
            → 解密取得授權結果
```

**注意**：因為你的伺服器直接接觸信用卡卡號，PCI DSS 合規是法律要求。

### PCI DSS 責任範圍比較

| 整合方式 | 你的伺服器接觸卡號？ | PCI DSS 範圍 | 合規成本 |
|---------|-------------------|-------------|---------|
| AIO（全方位金流） | ✗ | 最小（SAQ A） | 低 |
| 站內付 2.0 | ✗ | 中等（SAQ A-EP） | 中 |
| 幕後授權 | **✅ 直接接觸** | **完整（SAQ D）** | **高** |

> **建議**：除非有明確的業務需求（如電話訂購、B2B 大額交易），否則應優先使用 AIO 或站內付 2.0，避免承擔 PCI DSS 完整合規成本。

### 主要功能

| 功能 | 說明 |
|------|------|
| 幕後授權 | 直接傳卡號進行信用卡授權 |
| 請款 | 對已授權的交易進行請款 |
| 退款 | 對已請款的交易進行退款 |
| 取消授權 | 取消尚未請款的授權 |
| 交易查詢 | 查詢交易狀態 |

### 請求格式範例

所有請求都使用 AES 三層結構（與站內付 2.0 相同模式）：

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => [
        'Timestamp' => time(),
    ],
    'Data'       => [
        'MerchantID'      => '3002607',
        'MerchantTradeNo' => 'BA' . time(),
        // ... 其他業務參數（卡號、金額等）
        // 具體參數請查閱官方 API 文件
    ],
];
$response = $postService->post($input, 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/BackAuth');
```

### API 規格

端點：`POST /1.0.0/Cashier/BackAuth`

端點和完整參數詳見官方文件：[references/Payment/信用卡幕後授權API技術文件.md](../references/Payment/信用卡幕後授權API技術文件.md)（16 個 URL），
其中授權交易參數頁面為：https://developers.ecpay.com.tw/45958.md

#### BackAuth 常用核心參數

| 參數名稱 | 型別 | 必填 | 說明 |
|----------|------|------|------|
| MerchantTradeNo | string (20) | ✅ | 特店交易編號，不可重複 |
| MerchantTradeDate | string | ✅ | 特店交易時間，格式 `yyyy/MM/dd HH:mm:ss` |
| TotalAmount | int | ✅ | 交易金額（整數，不含小數） |
| TradeDesc | string | ✅ | 交易描述 |
| CardNo | string | ✅ | 信用卡卡號 |
| CardValidMM | string | ✅ | 信用卡有效月份（MM） |
| CardValidYY | string | ✅ | 信用卡有效年份（YY） |
| CardCVV2 | string | ✅ | 信用卡背面末三碼 |
| ReturnURL | string | ✅ | 付款結果通知 URL（Server POST） |
| OrderResultURL | string | — | 前端導回 URL（可選） |

> 以上為常用核心參數。完整參數（含分期、定期定額、3D 驗證等進階欄位）請查閱官方 API 技術文件。

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/信用卡幕後授權API技術文件.md`
> 以上參數表僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

> ⚠️ **AES-JSON 雙層錯誤檢查**：幕後授權回應需先檢查外層 `TransCode`（1=成功），
> 再解密 Data 檢查內層 `RtnCode`（1=交易成功）。兩層都必須為成功才代表交易成功。

> **重要**：幕後授權的 PHP SDK 沒有提供範例程式碼。上述程式碼僅展示 AES 請求格式。
> 具體端點 URL 和必填參數請務必參考官方 API 技術文件。

## 非信用卡幕後取號

### 適用場景

- 在背景為 ATM / 超商代碼 / 條碼產生繳費資訊
- 不需要消費者在頁面上操作
- 適合自動化系統（如自動產生繳費單）

### 整合流程

```
你的伺服器 → AES 加密訂單資料
            → POST JSON 到綠界幕後取號端點
            → 綠界回傳繳費資訊（AES 加密）
            → 解密取得繳費代碼
            → 將繳費代碼提供給消費者（Email/SMS/頁面顯示）
            → 消費者去 ATM/超商繳費
            → 綠界 POST 付款結果到你的 ReturnURL
```

### 取號結果對照

| 付款方式 | 回傳繳費資訊 | 消費者操作 |
|---------|------------|----------|
| ATM | BankCode（銀行代碼）+ vAccount（虛擬帳號） | 至 ATM 轉帳 |
| 超商代碼 | PaymentNo（繳費代碼） | 至超商繳費機輸入代碼 |
| 條碼 | Barcode1 + Barcode2 + Barcode3 | 至超商出示條碼 |

### 請求格式範例

```php
// ⚠️ Data 內必須使用巢狀物件：OrderInfo + CVSInfo（或 ATMInfo）
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => [
        'Timestamp' => time(),
    ],
    'Data'       => [
        'MerchantID'    => '3002607',
        'ChoosePayment' => 'ATM',  // ATM / CVS / BARCODE（頂層字串，非物件）
        'OrderInfo'     => [
            'MerchantTradeNo'   => 'BG' . time(),
            'MerchantTradeDate' => date('Y/m/d H:i:s'),
            'TotalAmount'       => 1000,
            'TradeDesc'         => '背景取號測試',
            'ItemName'          => '測試商品',
            'ReturnURL'         => 'https://你的網站/ecpay/notify',
        ],
        'ATMInfo'       => [        // CVS 時改為 CVSInfo；BARCODE 時省略
            'ExpireDate' => 3,      // ATM 繳費期限（天）
        ],
    ],
];
$response = $postService->post($input, 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/GenPaymentCode');
```

### 主要功能

| 功能 | 說明 |
|------|------|
| ATM 幕後取號 | 背景產生虛擬帳號 |
| 超商代碼幕後取號 | 背景產生超商繳費代碼 |
| 條碼幕後取號 | 背景產生三段條碼 |
| 交易查詢 | 查詢取號狀態與付款狀態 |

### API 規格

端點和完整參數詳見官方文件：`references/Payment/非信用卡幕後取號API技術文件.md`（15 個 URL）

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/非信用卡幕後取號API技術文件.md`
> 以上流程說明僅供整合理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

> **重要**：幕後取號的 PHP SDK 沒有提供範例程式碼。上述程式碼僅展示 AES 請求格式。
> 具體端點 URL 和必填參數請務必參考官方 API 技術文件。

## 與 AIO/站內付 2.0 的完整比較

| 面向 | AIO | 站內付 2.0 | 幕後授權 | 幕後取號 |
|------|-----|------|---------|---------|
| 消費者互動 | 需要（綠界頁面） | 需要（嵌入式） | 不需要 | 不需要 |
| 付款頁面 | 綠界提供 | 你的頁面 | 無 | 無 |
| 加密方式 | CheckMacValue (SHA256) | AES | AES | AES |
| 信用卡 | ✅ | ✅ | ✅（需 PCI DSS） | ✗ |
| ATM/CVS/條碼 | ✅ | ✅ | ✗ | ✅ |
| 適用場景 | 一般電商 | 嵌入式體驗 | 電話訂購/B2B | 自動化系統 |
| PHP SDK 範例 | 20 個 | 24 個 | 無 | 無 |
| 取號結果 | PaymentInfoURL 回呼 | API 回傳 | N/A | API 直接回傳 |

## 信用卡幕後授權 API

### 核心端點

| 操作 | 端點 | Action | 說明 |
|------|------|--------|------|
| 授權 | `/1.0.0/Cashier/BackAuth` | — | 信用卡幕後授權（直接傳卡號） |
| 請款 | `/1.0.0/Credit/DoAction` | C | 對已授權交易請款 |
| 退款 | `/1.0.0/Credit/DoAction` | R | 對已請款交易退款 |
| 取消授權 | `/1.0.0/Credit/DoAction` | E | 取消尚未請款的授權 |
| 放棄 | `/1.0.0/Credit/DoAction` | N | 放棄（取消請款操作） |
| 查詢 | `/1.0.0/CreditDetail/QueryTrade` | — | 查詢信用卡交易明細 |

> 端點來源：官方 API 技術文件 `references/Payment/信用卡幕後授權API技術文件.md`
> 完整參數規格請查閱該文件中的官方連結。

## 非信用卡幕後取號 API

### ATM/CVS/BARCODE 取號端點

| 付款方式 | 建單後流程 |
|---------|----------|
| ATM | 取得虛擬帳號 → 消費者轉帳 → ReturnURL 通知 |
| CVS（超商代碼）| 取得繳費代碼 → 消費者至超商繳費 → ReturnURL 通知 |
| BARCODE（條碼）| 取得三段條碼 → 消費者至超商掃碼 → ReturnURL 通知 |

### ReturnURL 回呼格式

消費者付款完成後，綠界會 POST 付款結果（AES-JSON 格式）到你指定的 ReturnURL。解密後回呼包含：
- MerchantID、MerchantTradeNo
- RtnCode（1=付款成功）
- 付款方式相關欄位（BankCode/vAccount 或 PaymentNo 或 Barcode1~3）

> ⚠️ **回應格式**：雖然收到的 Callback 是 AES-JSON 格式，但商家必須回應純字串 **`1|OK`**（與 AIO 金流相同，官方規格亦為 `1|OK`）。
> 未正確回應 `1|OK` 會導致綠界每 5-15 分鐘重送，每日最多 4 次。

> 完整參數規格請查閱 `references/Payment/非信用卡幕後取號API技術文件.md` 中的官方文件連結。

## 相關文件

- 信用卡幕後授權：`references/Payment/信用卡幕後授權API技術文件.md`
- 非信用卡幕後取號：`references/Payment/非信用卡幕後取號API技術文件.md`
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- AIO 金流（消費者互動）：[guides/01-payment-aio.md](./01-payment-aio.md)
- 站內付 2.0（嵌入式）：[guides/02-payment-ecpg.md](./02-payment-ecpg.md)
