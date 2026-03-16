> 對應 ECPay API 版本 | 最後更新：2026-03

# 購物車模組指南

> **本指南為快速索引**，不含程式碼實作。各電商平台（WooCommerce、OpenCart、Magento、Shopify）
> 使用官方提供的模組安裝即可，詳細設定請參考各平台官方文件。

## 何時選用購物車外掛？

| 情境 | 建議 |
|------|------|
| 已使用 WooCommerce / Magento / OpenCart / Shopify | 使用官方外掛，10 分鐘完成整合 |
| 自訂開發後端 | 直接串接 API，見 [guides/01](./01-payment-aio.md) |
| 需要高度客製化結帳流程 | 自訂開發，外掛限制較多 |

## 概述

ECPay 提供主流電商平台的預製模組，不需要撰寫程式碼即可串接金流。

## 支援平台

| 平台 | 支援版本 | 說明 |
|------|---------|------|
| WooCommerce | 8.X | WordPress 電商外掛 |
| OpenCart | 3.x / 4.x | 開源電商平台 |
| Magento | 2.4.3 / 2.4.5 | Adobe 電商平台 |
| Shopify | — | 透過 Shopify 專用 API |

## 各平台整合方式

### WooCommerce

**系統需求**：WordPress 6.0+、WooCommerce 8.X、PHP 7.4+、SSL 憑證

1. 從 [ECPay 官網](https://www.ecpay.com.tw) → 廠商專區 → 模組下載，下載 WooCommerce 模組
2. WordPress 後台 → 外掛 → 安裝外掛 → 上傳外掛 → 選擇下載的 zip 檔
3. 啟用外掛後，前往 WooCommerce → 設定 → 付款 → 啟用 ECPay
4. 填入 MerchantID、HashKey、HashIV
5. 設定 ReturnURL（通常外掛會自動處理）

**常見問題**：
- SSL 未啟用 → 金流無法正常運作（ECPay 要求 HTTPS）
- 外掛衝突 → 停用其他金流外掛後重試
- 回呼失敗 → 確認 WordPress 站台的 `wp-json` 或 `wc-api` 端點可被外部存取

### OpenCart

**系統需求**：OpenCart 3.x 或 4.x、PHP 7.4+

1. 下載對應版本的 OpenCart 模組（3.x 和 4.x 版本不通用）
2. 解壓後將檔案上傳到 OpenCart 安裝根目錄（覆蓋對應資料夾）
3. 後台 → Extensions → Payments → 找到 ECPay → 安裝並啟用
4. 填入帳號資訊並設定付款方式

### Magento

**系統需求**：Magento 2.4.3 或 2.4.5、PHP 8.1+

> ⚠️ **Magento 2.4.3 注意**：金流功能有支援，但物流與電子發票功能**不支援**（模組尚未完成）。
> 需要物流或發票功能，請使用 Magento **2.4.5**。

1. Composer 安裝（推薦）：
   ```bash
   composer require ecpay/magento2-payment
   php bin/magento module:enable ECPay_Payment
   php bin/magento setup:upgrade
   php bin/magento cache:flush
   ```
2. 後台 → Stores → Configuration → Sales → Payment Methods
3. 找到 ECPay 區塊，啟用並填入帳號資訊

### Shopify

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/Shopify專用金流API技術文件.md`

Shopify 使用專用 API 串接，非模組安裝：
- API 規格：`references/Payment/Shopify專用金流API技術文件.md`（5 個 URL）
- 需要在 Shopify 後台 → Settings → Payments → 新增付款供應商
- Shopify 的 webhook 機制與一般 ReturnURL 不同，需參考 Shopify 文件設定

## 模組功能支援矩陣

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Cart/購物車設定說明.md`

### 金流支援

| 購物車＼功能 | 信用卡一次付清 | 分期付款 | 定期定額 | 銀聯卡 | Apple Pay | TWQR | BNPL 無卡分期 | ATM | 超商代碼 | 超商條碼 | 網路ATM | 微信支付 |
|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| WooCommerce 8.X | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| OpenCart 3.x | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| OpenCart 4.x | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| Magento 2.4.3 | ● | ● | ✗ | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| Magento 2.4.5 | ● | ● | ✗ | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| Shopify | ● | ● | ✗ | ● | ● | ● | ○ | ● | ✗ | ✗ | ● | ● |

### 物流 / 電子發票支援

| 購物車＼功能 | 7-ELEVEN | 全家 | 萊爾富 | OK超商 | 黑貓宅配 | 郵局宅配 | 電子發票 |
|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| WooCommerce 8.X | ● | ● | ● | ● | ● | ● | ● |
| OpenCart 3.x | ● | ● | ● | ● | ● | ● | ● |
| OpenCart 4.x | ● | ● | ● | ● | ● | ● | ● |
| Magento 2.4.3 | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Magento 2.4.5 | ● | ● | ● | ● | ● | ● | ● |

> ● 已支援 | ○ 開發中 | ✗ 不支援

## 版本相容性注意事項

- 模組版本需與平台版本匹配，升級平台前先確認模組是否支援
- ECPay 模組更新時，建議先在測試環境驗證再更新正式環境
- 從 ECPay 測試環境切換到正式環境時，需在模組設定中更換帳號資訊
- 各平台模組的最新版本和更新日誌可在 [ECPay 官網](https://www.ecpay.com.tw) 廠商專區查看

## 常見設定問題

| 問題 | 可能原因 | 解決方式 |
|------|---------|---------|
| 付款頁面空白 | SSL 未啟用 | 安裝 SSL 憑證並強制 HTTPS |
| 回呼未收到 | 防火牆阻擋 | 確認伺服器允許 ECPay IP 的 POST 請求 |
| 金額不符 | 幣別設定錯誤 | 確認購物車幣別為 TWD |
| 模組無法安裝 | PHP 版本過低 | 升級至 PHP 7.4+ |
| 發票未開立 | 發票模組未啟用 | 另外安裝並啟用 ECPay 發票模組 |

## 詳細設定

各平台的完整安裝和設定說明：`references/Cart/購物車設定說明.md`（5 個 URL）

## 相關文件

- 購物車設定：`references/Cart/購物車設定說明.md`
- Shopify API：`references/Payment/Shopify專用金流API技術文件.md`
- 如需自訂整合：[guides/01-payment-aio.md](./01-payment-aio.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
