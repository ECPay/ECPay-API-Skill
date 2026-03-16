> 對應 ECPay API 版本 | 最後更新：2026-03
> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/直播主收款網址串接技術文件.md` — 生成程式碼前請 web_fetch 取得最新規格

# 直播收款指引

> **本指南為初步整合指引**，提供直播收款的概念說明和特殊 URL 結構。
> 詳細 API 規格見 `references/Payment/直播主收款網址串接技術文件.md`。
>
> **注意**：本指南的 PHP 範例為依 `references/Payment/直播主收款網址串接技術文件.md` 手寫，非官方 SDK 範例。

## 概述

直播收款網址服務讓直播主或賣家能快速產生收款連結，在直播過程中分享給觀眾完成付款。適用於直播電商、網紅經濟等即時銷售場景。

## 適用場景

- 直播電商（Facebook Live、YouTube Live、蝦皮直播等）
- 網紅 / KOL 即時帶貨
- 社群團購分享收款連結
- 不需要自建購物車的輕量收款

## 核心流程

```
1. 賣家透過 API 建立收款網址（含商品名稱、金額）
2. 直播中分享收款連結給觀眾
3. 觀眾點擊連結完成付款
4. 賣家透過 API 或後台查詢訂單
```

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式（API 請求） | AES-JSON — 詳見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證 | AES-128-CBC 加密 Data 欄位 — 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| 回應結構 | 三層 JSON（TransCode → 解密 Data → RtnCode） |
| ReturnURL Callback | JSON POST (`application/json`)；驗證：① `TransCode === 1` ② AES 解密 Data ③ CheckMacValue（timing-safe，ECTicket 公式 `strtoupper(SHA256(toLowerCase(urlencode(HashKey+Data明文+HashIV))))`，**無 .NET 字元替換、無參數排序**）④ `RtnCode === 1`（**整數**）；**回應純文字 `1\|OK`** |

## API 端點概覽

直播收款服務的主要操作：

| 操作 | 端點路徑 | 說明 |
|------|---------|------|
| 建立收款網址 | `/1.0.0/Cashier/LiveStreamPayment` | 產生含金額和商品資訊的付款連結 |
| 查詢收款網址清單 | 見官方文件 | 查詢所有已建立的收款網址 |
| 查詢單筆詳情 | 見官方文件 | 查詢特定收款網址的詳細資訊和付款狀態 |
| 關閉收款網址 | 見官方文件 | 停用不再需要的收款連結 |
| 查詢付款紀錄 | 見官方文件 | 查詢已完成的付款明細 |

> 「見官方文件」端點路徑請查閱 `references/Payment/直播主收款網址串接技術文件.md` 中的官方連結。
> Base domain 使用 ECPG **交易 domain**（`ecpayment`，不是 Token domain `ecpg`）：測試 `ecpayment-stage.ecpay.com.tw`，正式 `ecpayment.ecpay.com.tw`。

## 建立收款網址

### 請求參數

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/直播主收款網址串接技術文件.md`

| 參數 | 型別 | 必填 | 說明 |
|------|------|------|------|
| MerchantID | String | ✅ | 特店編號 |
| ItemName | String | ✅ | 商品名稱 |
| Amount | Int | ✅ | 收款金額（新台幣整數） |
| ExpiryDate | String | ✅ | 收款網址有效期限（yyyy/MM/dd HH:mm:ss） |
| PaymentTypes | String | ✅ | 允許的付款方式（Credit/ATM/CVS 等） |
| ReturnURL | String | ✅ | 付款結果通知 URL |

### PHP 範例

```php
$factory = new Factory([
    'hashKey' => getenv('ECPAY_HASH_KEY'),
    'hashIv'  => getenv('ECPAY_HASH_IV'),
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$input = [
    'MerchantID' => getenv('ECPAY_MERCHANT_ID'),
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'MerchantID'  => getenv('ECPAY_MERCHANT_ID'),
        'ItemName'    => '直播限定商品',
        'Amount'      => 990,
        'ExpiryDate'  => date('Y/m/d H:i:s', strtotime('+24 hours')),
        'PaymentTypes'=> 'Credit',
        'ReturnURL'   => 'https://你的網站/ecpay/livestream-notify',
    ],
];
$response = $postService->post($input, 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/LiveStreamPayment');
// 正式環境：https://ecpayment.ecpay.com.tw/1.0.0/Cashier/LiveStreamPayment
```

> **端點說明**：直播收款使用 ECPG 支付閘道基礎設施（MerchantID/HashKey/HashIV 同 ECPG 測試帳號 3002607）。
> 具體端點路徑和完整參數規格請以官方 API 技術文件為準：`references/Payment/直播主收款網址串接技術文件.md`

## 收款網址管理

- **有效期限**：建立時設定，過期後消費者無法付款
- **狀態管理**：可透過 API 主動關閉不再需要的收款網址
- **付款通知**：消費者付款後，綠界以 **JSON POST** 傳送到 ReturnURL（格式同 ECTicket：AES 解密 Data + ECTicket-式 CMV，**非** AIO Form POST 格式）；驗證順序：TransCode === 1 → AES 解密 → CMV timing-safe 驗證 → RtnCode === 1（**整數**）；**回應純文字 `1|OK`**（⚠️ 與電子票證不同 — ECTicket 回 AES+CMV，直播收款回純文字）

## 完整規格文件

詳細的 API 參數和串接流程，請參閱官方技術文件：

> 📄 `references/Payment/直播主收款網址串接技術文件.md`（7 個外部文件 URL）

## 相關文件

- 標準金流串接：[guides/01-payment-aio.md](./01-payment-aio.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
