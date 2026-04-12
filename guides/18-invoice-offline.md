> 對應 ECPay API 版本 | 最後更新：2026-04

# 離線電子發票指南
> ⚠️ **SNAPSHOT 2026-04** | 來源：`references/Invoice/離線電子發票API技術文件.md` — 生成程式碼前請 web_fetch 取得最新規格
> 📖 即時規格：`references/Invoice/離線電子發票API技術文件.md`（22 個 URLs）

## 概述

離線電子發票服務適用於無穩定網路的場景，讓商家在離線狀態下先開立發票，待恢復連線後再上傳到綠界系統進行歸檔和上傳財政部。

### ⚠️ AES-JSON 開發者必讀：雙層錯誤檢查

離線電子發票使用 AES-JSON 協議，回應為**三層 JSON** 結構。**必須做兩次檢查**：

1. 檢查外層 `TransCode === 1`（整數；否則加密格式有誤，無需解密 Data）
2. 解密 `Data` 後，檢查內層 `RtnCode === 1`（**整數**，非字串 `"1"`）

> ⚠️ `RtnCode` 在所有 AES-JSON 服務（含離線發票）解密後為**整數 `1`**，不同於 AIO 金流 Callback 的字串 `"1"`。只檢查其中一層會導致錯誤漏檢。完整錯誤碼見 [guides/20](./20-error-codes-reference.md)。

### 首次串接注意事項

- [ ] 🚨 **離線發票測試帳號獨立,與線上 B2C 發票不同(最常見錯誤)**:MerchantID `3085340` / HashKey `HwiqPsywG1hLQNuN` / HashIV `YqITWD4TyKacYXpn`
  - ❌ **不可用線上 B2C 發票的** `2000132`(會收到 MerchantID 錯誤)
  - ❌ **不可用 AIO 金流的** `3002607`(完全不同服務)
  - ✅ 離線發票專屬帳號只能用於 `OfflineIssue` / `OfflineInvalid` 等離線上傳 API
- [ ] `Revision` 必須填 `"1.0.0"`（留空或版本錯誤會導致 `TransCode ≠ 1`）
- [ ] 離線發票有**前置作業**：需先登錄 POS 機台、申請字軌、取得配號，才能離線開票（見下方「POS 前置設定流程」）
- [ ] 依法規，發票開立後 **48 小時內**必須上傳至財政部；逾時須向國稅局說明
- [ ] ⚠️ 首次串接必讀：`references/Invoice/離線電子發票API技術文件.md` → **準備事項 / 介接注意事項**（`web_fetch` https://developers.ecpay.com.tw/13768.md）

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
| 商品上限 | 最多 999 項 | 最多 **200 項** |

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
| 協議模式 | AES-JSON — 詳見 [guides/19-http-protocol-reference.md](./19-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證 | AES-128-CBC 加密 Data 欄位 — 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| Revision | `1.0.0` |
| 測試環境 | `https://einvoice-stage.ecpay.com.tw` |
| 正式環境 | `https://einvoice.ecpay.com.tw` |
| 測試帳號 | MerchantID `3085340` / HashKey `HwiqPsywG1hLQNuN` / HashIV `YqITWD4TyKacYXpn`（⚠️ 與線上 B2C 發票帳號不同，請於 `references/Invoice/離線電子發票API技術文件.md` 準備事項確認） |
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
| 發送發票通知 | `/B2CInvoice/InvoiceNotify` |
| 取得發票字軌號碼區間 | `/B2CInvoice/GetOfflineInvoiceWordSetting` |
| 取得字軌號碼清單（含隨機碼） | `/B2CInvoice/GetOfflineInvoiceWordSettingNumber` |
| 上傳開立發票 | `/B2CInvoice/OfflineIssue` |
| 上傳作廢發票 | `/B2CInvoice/OfflineInvalid` |
| 查詢發票機台 | `/B2CInvoice/QueryOfflineMerchantPosSetting` |
| 查詢字軌 | `/B2CInvoice/GetInvoiceWordSetting` |

### POS 前置設定流程

離線發票在正式離線開票前，**必須完成以下前置設定**（一次性設定，後續維護字軌庫存即可）：

```
前置設定順序：
1. 登錄 POS 機台      → OfflineMerchantPosSetting（向綠界登記每台 POS）
                         Data 必填：ActionType（1 新增/2 修改/3 刪除）、MachineID（機台 ID）
2. 申請字軌配號       → AddInvoiceWordSetting（向財政部申請發票字軌）
3. 確認配號結果       → GetGovInvoiceWordSetting（確認財政部已核准配號）
4. 取得號碼清單       → GetOfflineInvoiceWordSettingNumber（含隨機碼和加密資料）
5. 存入本機           → 儲存至 POS 本地加密儲存（SQLite 或應用層加密）
```

> 📋 各步驟詳細請求參數請 `web_fetch` `references/Invoice/離線電子發票API技術文件.md` 中「前置作業」各節取得最新規格。

#### `GetOfflineInvoiceWordSetting` vs `GetOfflineInvoiceWordSettingNumber` 差異

| API | 用途 |
|-----|------|
| `GetOfflineInvoiceWordSetting` | 查詢可用字軌區間（數字範圍，用於確認庫存） |
| `GetOfflineInvoiceWordSettingNumber` | 取得完整號碼清單（**含隨機碼、加密資料**），用於實際離線開票 |

> ⚠️ 實際開票時必須使用 `GetOfflineInvoiceWordSettingNumber` 回傳的號碼（含隨機碼），直接使用數字區間會導致發票號碼無效。取得後建議：
> - 標記每個號碼的使用狀態（未使用 / 已使用 / 已上傳）
> - 設定最低庫存警示（建議剩餘 20% 時補充）
> - 本地儲存需加密保護，防止號碼被盜用

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
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'MerchantID'    => getenv('ECPAY_INVOICE_MERCHANT_ID'),
        'InvoiceYear'   => (string)(date('Y') - 1911),  // 民國年，例如 "114"
        'InvoiceTerm'   => (int)ceil(date('n') / 2),     // 發票期別 1-6
        'InvoiceStatus' => 1,                             // 1:啟用 2:備用字軌
        'MachineID'     => '機台ID',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/GetOfflineInvoiceWordSetting');
```

### 上傳離線發票

恢復連線後批次上傳：

```php
$input = [
    'MerchantID' => getenv('ECPAY_INVOICE_MERCHANT_ID'),
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'MerchantID'   => getenv('ECPAY_INVOICE_MERCHANT_ID'),
        'MachineID'    => 'POS001',                 // 發票機台 ID（必填）
        'RelateNumber' => 'OFF' . time(),
        'InvoiceNo'    => '預取的發票號碼',
        'InvoiceDate'  => '2026-03-13 14:30:00',   // yyyy-MM-dd HH:mm:ss
        'TaxType'      => '1',                      // 課稅類別：1=應稅 2=零稅率 3=免稅 4=應稅(特種) 9=混合
        // ⚠️ TaxType=2 時必填：'ClearanceMark' => '1'（1=經海關出口 2=非經海關出口）
        // ⚠️ TaxType=2 或 9 時必填：'ZeroTaxRateReason' => '...'（零稅率原因）
        // ⚠️ TaxType=3 或 4 時必填：'SpecialTaxType' => '...'（特種稅額類別）
        'InvType'      => '07',                     // 字軌類別：07=一般稅額 08=特種稅額
        'RandomNumber' => '9527',                   // 4 位隨機碼（必填，僅限數字，不可流水號）
        'SalesAmount'  => 1000,                     // 發票總金額(正整數,必填)
                                                     // ⚠️ 依 vat 參數決定:vat='1' 時為含稅總額,vat='0' 時為未稅總額
                                                     //    必須等於 Items[].ItemAmount 加總(四捨五入)
        'vat'          => '1',                      // 商品單價是否含稅:'1'=含稅(預設) '0'=未稅
                                                     //   此設定同時影響 SalesAmount 與 Items[].ItemPrice/ItemAmount 的含稅性
        'Print'        => '0',                      // 列印註記：0=不列印 1=要列印（必填）
        'Donation'     => '0',                      // 捐贈註記：0=不捐贈 1=要捐贈（必填）
        // 選填：'CustomerIdentifier' => '統一編號'（有值時 Print 須為 '1'）
        // 選填：'CarrierType' => '3'（載具：1=綠界 2=自然人憑證 3=手機條碼 4=悠遊卡 5=一卡通）
        // 選填：'CarrierNum' / 'CarrierNum2'（悠遊卡/一卡通需兩組載具編號）
        // 選填：'LoveCode' => '捐贈碼'（Donation='1' 時必填）
        // ⚠️ 離線發票最多 200 項商品（線上 B2C 為 999 項）
        'Items'        => [
            ['ItemName' => '商品', 'ItemCount' => 1, 'ItemWord' => '件',
             'ItemPrice' => 1000, 'ItemTaxType' => '1', 'ItemAmount' => 1000],
        ],
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/OfflineIssue');
```

#### 解析上傳回應（兩層錯誤檢查）

```php
// 第一層：檢查 AES 加密是否成功
if ($response['TransCode'] !== 1) {
    // 加密格式或 Revision 問題，Data 欄位不可解密
    throw new RuntimeException('AES format error: ' . $response['TransMsg']);
}

// 第二層：解密後檢查業務邏輯（RtnCode 為整數）
$data = $response['Data'];
if ($data['RtnCode'] !== 1) {
    // 業務邏輯錯誤（例如號碼重複、已逾 48 小時、InvoiceNo 無效等）
    throw new RuntimeException('Upload failed: [' . $data['RtnCode'] . '] ' . $data['RtnMsg']);
}

// 上傳成功
$invoiceNo = $data['InvoiceNo'] ?? null;
```

> ⚠️ `$data['RtnCode'] !== 1` 必須與**整數** 1 比較，不要寫 `!== '1'`（字串）。

### 作廢離線發票

```php
$input = [
    'MerchantID' => getenv('ECPAY_INVOICE_MERCHANT_ID'),
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'MerchantID' => getenv('ECPAY_INVOICE_MERCHANT_ID'),
        'InvoiceNo'  => '要作廢的發票號碼',
        'InvoiceDate' => '2026-03-13',               // 發票開立日期 yyyy-MM-dd（必填）
        'Reason'     => '作廢原因',                    // 作廢原因（必填，最多 20 字）
        'CancelDate' => '2026-03-14 10:30:00',        // 作廢時間 yyyy-MM-dd HH:mm:ss（必填）
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/OfflineInvalid');
```

### 發送發票通知（InvoiceNotify）

發票上傳成功後，可透過此 API 補送通知給消費者（Email 或手機簡訊）：

- **適用場景**：POS 開票時未取得消費者聯絡資料，事後補填後需補發通知
- **端點**：`/B2CInvoice/InvoiceNotify`
- **Data 必填參數**：

| 參數 | 類型 | 說明 |
|------|------|------|
| `MerchantID` | String(10) | 特店編號 |
| `InvoiceNo` | String(10) | 發票號碼（10 碼） |
| `Phone` | String(20) | 發送簡訊號碼（與 NotifyMail 擇一必填） |
| `NotifyMail` | String(200) | 發送電子郵件（與 Phone 擇一必填，多組以 `;` 分隔） |
| `Notify` | String(1) | 發送方式：`S` 簡訊 / `E` 電子郵件 / `A` 皆通知 |
| `InvoiceTag` | String(2) | 發送內容類型：`OI`（發票開立） |
| `Notified` | String(1) | 發送對象：`C` 客戶 / `M` 特店 / `A` 皆發送 |

- **詳細規格**：`web_fetch` https://developers.ecpay.com.tw/45974.md

> ⚠️ 測試環境下綠界不會主動發送任何通知，使用廠商後台「補發通知」也不會通知。

### 設定字軌號碼狀態（UpdateInvoiceWordStatus）

用於更新字軌號碼的使用狀態（例如字軌到期、回收未使用號碼）：

- **適用場景**：字軌授權期限到期或 POS 機台停用時，將剩餘未使用號碼回收，避免號碼浪費
- **端點**：`/B2CInvoice/UpdateInvoiceWordStatus`
- **Data 必填參數**：

| 參數 | 類型 | 說明 |
|------|------|------|
| `MerchantID` | String(10) | 特店編號 |
| `TrackID` | String(10) | 字軌號碼 ID（新增字軌後取得的 TrackID） |
| `InvoiceStatus` | Int | 字軌狀態：`0` 停用 / `1` 暫停 / `2` 啟用 |

> ⚠️ 狀態設定為停用（`0`）後，該字軌區間無法再上傳發票。

- **詳細規格**：`web_fetch` https://developers.ecpay.com.tw/13793.md

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

> 📄 `references/Invoice/離線電子發票API技術文件.md`（各節 URL 索引）

| 章節 | 說明 | URL |
|------|------|-----|
| 使用流程圖說明 | 離線發票完整作業流程示意 | https://developers.ecpay.com.tw/13758.md |
| 準備事項 / 測試介接資訊 | ⚠️ **離線發票專用測試帳號**（MerchantID 3085340） | https://developers.ecpay.com.tw/13763.md |
| 準備事項 / 介接注意事項 | ⚠️ **首次串接必讀**，POS 開發限制與注意事項 | https://developers.ecpay.com.tw/13768.md |
| 前置作業 / 查詢特店基本資料 | 查詢特店名稱與統一編號 | https://developers.ecpay.com.tw/13773.md |
| 前置作業 / 查詢財政部配號結果 | 確認財政部是否已核准字軌配號 | https://developers.ecpay.com.tw/13778.md |
| 前置作業 / 管理發票機台 | POS 機台登錄（新增/修改/刪除） | https://developers.ecpay.com.tw/13783.md |
| 前置作業 / 字軌與配號設定 | 向財政部申請字軌 | https://developers.ecpay.com.tw/13788.md |
| 前置作業 / 設定字軌號碼狀態 | 停用/暫停/啟用字軌 | https://developers.ecpay.com.tw/13793.md |
| 前置作業 / 取得字軌號碼區間 | 查詢可用字軌數字範圍（確認庫存用） | https://developers.ecpay.com.tw/13795.md |
| 前置作業 / 取得字軌號碼清單 | 含隨機碼、加密資料，實際開票用 | https://developers.ecpay.com.tw/15502.md |
| 發送通知 / 發送發票通知 | 補送開立發票通知給消費者 | https://developers.ecpay.com.tw/45974.md |
| 發票作業 / 上傳開立發票 | 上傳離線開立的發票至綠界 | https://developers.ecpay.com.tw/13823.md |
| 發票作業 / 上傳作廢發票 | 上傳作廢發票資料至綠界 | https://developers.ecpay.com.tw/13828.md |
| 發票查詢 / 查詢發票機台 | 查詢已登錄的 POS 機台清單 | https://developers.ecpay.com.tw/13833.md |
| 發票查詢 / 查詢字軌 | 查詢已申請的字軌資訊 | https://developers.ecpay.com.tw/13843.md |
| 附錄 / 錯誤代碼一覽表 | 離線發票錯誤碼查詢 | https://developers.ecpay.com.tw/13853.md |
| 附錄 / 電子發票列印格式說明 | ⚠️ **POS 列印格式規範（實體收銀機必讀）** | https://developers.ecpay.com.tw/31732.md |
| 附錄 / 參數加密方式說明 | AES-128-CBC 加密詳細說明 | https://developers.ecpay.com.tw/13863.md |

> 💡 **POS 開發者特別注意**：**附錄 / 電子發票列印格式說明**規定了電子發票明細聯、存根聯的列印格式和欄位順序，是實體收銀機整合的必要規範，必須在 POS 列印功能實作前閱讀。

## 相關文件

- 線上 B2C 發票：[guides/04-invoice-b2c.md](./04-invoice-b2c.md)
- B2B 發票：[guides/05-invoice-b2b.md](./05-invoice-b2b.md)
- AES 加解密詳細演算法與 URL 編碼規範：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- CheckMacValue 計算（離線發票本身不使用 CMV，但 `aesUrlEncode` vs `ecpayUrlEncode` 差異見此）：[guides/13-checkmacvalue.md](./13-checkmacvalue.md)
- 跨服務整合：[guides/11-cross-service-scenarios.md](./11-cross-service-scenarios.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
