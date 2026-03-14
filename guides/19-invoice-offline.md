# 離線電子發票指引

> 對應 ECPay API 版本 | 最後更新：2026-03
> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Invoice/離線電子發票API技術文件.md` — 生成程式碼前請 web_fetch 取得最新規格

## 概述

離線電子發票服務適用於無穩定網路的場景，讓商家在離線狀態下先開立發票，待恢復連線後再上傳到綠界系統進行歸檔和上傳財政部。

## 適用場景

- 市集、展覽攤位
- 偏鄉或山區門市
- 流動攤販
- 網路不穩定的實體店面
- 災害或斷網時的應急方案

## 與線上 B2C 發票的差異

| 面向 | 線上 B2C 發票 | 離線發票 |
|------|-------------|---------|
| 網路需求 | 必須即時連線 | 可離線操作 |
| 開立方式 | 即時呼叫 API | 本地暫存後批次上傳 |
| 發票號碼 | 即時取得 | 預先配號 |
| 同步機制 | 不需要 | 需離線→上線同步 |

## 核心流程

```
1. 預先從綠界取得發票字軌和號碼區間
2. 離線狀態下本地開立發票
3. 恢復連線後批次上傳發票資料
4. 綠界驗證並上傳至財政部電子發票整合服務平台
```

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式 | AES-JSON — 詳見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證 | AES-128-CBC 加密 Data 欄位 — 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| Revision | `3.0.0` |
| 測試環境 | `https://einvoice-stage.ecpay.com.tw` |
| 正式環境 | `https://einvoice.ecpay.com.tw` |
| 測試帳號 | MerchantID `2000132` / HashKey `ejCk326UnaZWKisg` / HashIV `q9jcZX8Ib9LM8wYk`（同 B2C 發票，請於 `references/Invoice/離線電子發票API技術文件.md` 準備事項確認） |
| 回應結構 | 三層 JSON（TransCode → 解密 Data → RtnCode） |

## API 端點概覽

### 端點 URL 一覽

> 端點來源：官方 API 技術文件 `references/Invoice/離線電子發票API技術文件.md`

| 功能 | 端點路徑 |
|------|---------|
| 查詢特店基本資料 | `/B2CInvoice/GetOfflineMerchantInfo` |
| 查詢財政部配號結果 | `/B2CInvoice/GetGovInvoiceWordSetting` |
| 管理發票機台 | `/B2CInvoice/OfflineMerchantPosSetting` |
| 字軌與配號設定 | `/B2CInvoice/AddInvoiceWordSetting` |
| 設定字軌號碼狀態 | `/B2CInvoice/UpdateInvoiceWordStatus` |
| 發送發票通知 | `/B2CInvoice/SendNotification` |
| 取得發票字軌號碼區間 | `/B2CInvoice/GetOfflineInvoiceWordSetting` |
| 取得字軌號碼清單 | `/B2CInvoice/GetOfflineInvoiceWordSettingList` |
| 上傳開立發票 | `/B2CInvoice/OfflineIssue` |
| 上傳作廢發票 | `/B2CInvoice/OfflineInvalid` |
| 查詢發票機台 | `/B2CInvoice/QueryOfflineMerchantPosSetting` |
| 查詢字軌 | `/B2CInvoice/GetInvoiceWordSetting` |

### 取得發票字軌

在離線前預先取得足夠的發票號碼：

> 注：以下 PHP 範例基於 `references/Invoice/離線電子發票API技術文件.md` 撰寫，官方 PHP SDK 未提供離線發票的獨立 example 檔案。生成程式碼前請 web_fetch 上述 reference 取得最新參數規格。

```php
$factory = new Factory([
    'hashKey' => getenv('ECPAY_INVOICE_HASH_KEY'),
    'hashIv'  => getenv('ECPAY_INVOICE_HASH_IV'),
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$input = [
    'MerchantID' => getenv('ECPAY_INVOICE_MERCHANT_ID'),
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'Qty' => 50,    // 預取 50 組發票號碼
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/GetOfflineInvoiceWordSetting');
```

### 上傳離線發票

恢復連線後批次上傳：

```php
$input = [
    'MerchantID' => getenv('ECPAY_INVOICE_MERCHANT_ID'),
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'   => getenv('ECPAY_INVOICE_MERCHANT_ID'),
        'RelateNumber' => 'OFF' . time(),
        'InvoiceNo'    => '預取的發票號碼',
        'InvoiceDate'  => '2026-03-13',   // yyyy-MM-dd；請依官方離線 API 規格格式填寫
        'SalesAmount'  => 1000,
        'Items'        => [
            ['ItemName' => '商品', 'ItemCount' => 1, 'ItemWord' => '件',
             'ItemPrice' => 1000, 'ItemTaxType' => '1', 'ItemAmount' => 1000],
        ],
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/OfflineIssue');
```

### 作廢離線發票

```php
$input = [
    'MerchantID' => getenv('ECPAY_INVOICE_MERCHANT_ID'),
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID' => getenv('ECPAY_INVOICE_MERCHANT_ID'),
        'InvoiceNo'  => '要作廢的發票號碼',
        'Reason'     => '作廢原因',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/OfflineInvalid');
```

### 48 小時上傳時限

依法規，電子發票必須在開立後 48 小時內上傳至財政部。建議實作方式：

```
定時排程策略：
├── 方案 A：每小時檢查並上傳（推薦）
│   └── cron: 0 * * * * php upload_offline_invoices.php
├── 方案 B：恢復連線時立即上傳
│   └── 偵測網路狀態變化，觸發上傳
└── 方案 C：手動觸發
    └── 提供管理介面讓人員手動上傳（不推薦，容易遺忘）
```

### 異常處理

| 狀況 | 處理方式 |
|------|---------|
| 上傳失敗 | 記錄失敗原因，30 分鐘後自動重試，最多重試 3 次 |
| 部分成功 | 逐筆檢查結果，僅重傳失敗的發票 |
| 超過 48 小時 | 立即上傳並通知管理員，可能需向國稅局說明 |
| 號碼用完 | 立即連線取得新字軌，暫停離線開票 |

## 完整規格文件

詳細的 API 參數和離線同步機制，請參閱官方技術文件：

> 📄 `references/Invoice/離線電子發票API技術文件.md`（外部文件 URL）

## 相關文件

- 線上 B2C 發票：[guides/04-invoice-b2c.md](./04-invoice-b2c.md)
- B2B 發票：[guides/05-invoice-b2b.md](./05-invoice-b2b.md)
- 跨服務整合：[guides/11-cross-service-scenarios.md](./11-cross-service-scenarios.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
