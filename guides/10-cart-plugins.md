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

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Cart/購物車設定說明.md` — 請參閱官方頁面確認最新版本

| 平台 | 官方驗證版本 | 說明 |
|------|------------|------|
| WooCommerce | WordPress 6.5.3 / WooCommerce 8.8.0 / PHP 8.2 | WordPress 電商外掛（模組名稱：ECPay Ecommerce for WooCommerce） |
| OpenCart | OpenCart 4.0.2.3 / PHP 8.2 | 開源電商平台（模組名稱：ECPay Ecommerce for OpenCart） |
| Magento | 2.4.3 / 2.4.5 | Adobe 電商平台（版本資訊請至 ECPay 官網廠商專區確認） |
| Shopify | — | 透過 Shopify 專用 API |

> ℹ️ 上述版本為官方文件最後驗證版本，模組可能持續更新支援更新的平台版本。最新版本請至 [ECPay 官網](https://www.ecpay.com.tw) 廠商專區 → 模組下載頁面確認。

## 各平台整合方式

### WooCommerce

**模組名稱**：ECPay Ecommerce for WooCommerce

**系統需求**：WordPress 6.5.3+、WooCommerce 8.8.0+、PHP 8.2+、SSL 憑證

1. 從 [ECPay 官網](https://www.ecpay.com.tw) → 廠商專區 → 模組下載，下載 WooCommerce 模組
2. 解壓縮套件檔，取得 `ecpay-ecommerce-for-woocommerce.zip`
3. WordPress 後台 → 外掛(Plugins) → 安裝外掛(Add New) → 上傳外掛(Upload Plugin) → 選擇 zip 檔 → 立即安裝(Install Now)
4. 安裝完成後按「啟用外掛(Activate Plugin)」
5. 前往 WooCommerce → 設定(Settings) → 點選「綠界科技」分頁
6. 分別設定：
   - **金流設定**：填入 MerchantID、HashKey、HashIV，選擇啟用的付款方式
   - **物流設定**：填入物流帳號資訊、寄件人資料，並至「運送方式 → 運送區域」新增綠界物流種類
   - **電子發票設定**：填入發票帳號資訊，設定開立模式與延期天數

> ⚠️ 金流、物流、電子發票使用**不同**的 MerchantID / HashKey / HashIV,請分別填入。**錯誤配置症狀**:
> - 把金流帳號用於物流 → 呼叫物流 API 時回傳「MerchantID 不符」或 CheckMacValue 驗證失敗
> - 把物流/發票帳號用於金流 → 前台結帳出現 `10100248` 帳號錯誤或 CheckMacValue Error
> - 三組帳號在 ECPay 後台 [廠商專區](https://vendor.ecpay.com.tw) 分別管理,測試階段可用 ECPay 提供的三組測試帳號(金流 3002607 / 發票 2000132 / 物流另行申請)

**常見問題**：
- SSL 未啟用 → 金流無法正常運作（ECPay 要求 HTTPS）
- 外掛衝突 → 若已安裝舊版模組（ECPay Payment / ECPay Logistics / ECPay Invoice for WooCommerce），請先移除，這些舊模組已下架且不再更新，會與新模組產生衝突
- 回呼失敗 → 確認 WordPress 站台的 `wp-json` 或 `wc-api` 端點可被外部存取
- 永久連結設定 → 選擇超商物流後頁面顯示「找不到符合條件的頁面」，請將 WooCommerce 後台的永久連結改為「預設」
- ATM/CVS 訂單被提前取消 → WooCommerce 內建「保留庫存」機制（WordPress 後台 → WooCommerce → 設定 → 商品 → 庫存 → 保留庫存(分)）會自動取消超時未付款訂單，請依繳費期限調整分鐘數（例如 ATM 3 天 = 4320 分鐘）
- 超商取貨付款 → 需額外至 WooCommerce → 設定 → 付款 → 貨到付款 → 啟用運送方式，加入超商取貨付款的物流種類

### OpenCart

**模組名稱**：ECPay Ecommerce for OpenCart

**系統需求**：OpenCart 4.0.2.3+、PHP 8.2+（需安裝 PHP `curl` 模組）

1. 購物車後台 → 擴充模組安裝(Extension Installer) → 上傳 `ecpay.ocmod.zip` → 等待安裝完成
2. 金流：擴充模組(Extensions) → 付款模組(Payments) → 綠界金流模組 → 安裝 → 編輯 → 填入 MerchantID、HashKey、HashIV
3. 物流：擴充模組(Extensions) → 運送模組(Shipping) → 綠界物流模組 → 安裝 → 編輯 → 填入物流帳號資訊與運費設定
4. 發票：擴充模組(Extensions) → 功能模組(Modules) → 綠界電子發票模組 → 安裝 → 編輯 → 填入發票帳號資訊

> ⚠️ 注意事項：
> - 安裝模組需花費一點時間，請等安裝完成後再繼續操作
> - Hash Key 與 Hash IV 不可包含空白，建議使用複製貼上
> - 須搭配綠界金流模組才能使用物流和電子發票模組
> - 超商物流金額限制 1~20,000 元，黑貓宅配取貨付款限制 1~20,000 元，超過時前台結帳頁會自動隱藏選項
> - TWQR 金額限制 6~49,999 元，微信支付金額限制 6~500,000 元
> - 貨到付款(COD) 僅支援綠界超商取貨付款及綠界黑貓宅配取貨付款

### Magento

**系統需求**：Magento 2.4.3 或 2.4.5、PHP 8.1+

> ⚠️ **Magento 2.4.3 限制**:金流功能有支援,但物流與電子發票功能**不支援**(模組尚未完成)。
> **👉 建議**:若需物流或發票功能,請直接使用 Magento **2.4.5**(完整支援金流 + 物流 + 發票三項)。
> 僅需金流功能的商家可繼續使用 2.4.3。
>
> ⚠️ **版本與套件名稱注意**：Magento 模組未包含在 `ecpay/sdk` 套件中，Composer 套件名稱（如 `ecpay/magento2-payment`）與支援版本**可能隨版本更新變動**，請以 [Packagist](https://packagist.org/) 及 [ECPay 官網](https://www.ecpay.com.tw) 廠商專區 → 模組下載頁面的最新資訊為準。

1. Composer 安裝(推薦,套件名稱請以官方頁面為準):
   ```bash
   # ⚠️ 套件名稱可能隨版本變動,請至以下任一來源確認最新名稱:
   #   - Packagist: https://packagist.org/ (搜尋 "ecpay")
   #   - ECPay 官網: https://www.ecpay.com.tw 廠商專區 → 模組下載
   composer require ecpay/magento2-payment
   php bin/magento module:enable ECPay_Payment
   php bin/magento setup:upgrade
   php bin/magento cache:flush
   ```
2. 後台 → Stores → Configuration → Sales → Payment Methods
3. 找到 ECPay 區塊，啟用並填入帳號資訊

### Shopify

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/Shopify專用金流API技術文件.md`

Shopify 透過 ECPay 提供的 **Shopify 專用付款 App** 串接,商家不需自行撰寫付款 API 程式碼。Shopify 專用 API 主要用於對帳、訂單查詢等管理功能:
- **付款整合**:在 Shopify 後台 → Settings → Payments → 新增 ECPay 付款供應商(App 安裝方式)
- **API 規格**(對帳/訂單管理用):詳細 URL 清單見 `references/Payment/Shopify專用金流API技術文件.md`(索引檔);**首次串接前務必 web_fetch 該檔案列出的官方 URL** 取得最新 API 參數、加密規格與 webhook 設定
- **Webhook 差異**:Shopify 的 webhook 機制透過 Shopify Admin API 設定(非 ECPay 標準 ReturnURL),付款通知流程由 ECPay App 代理轉送,詳見 Shopify 開發者文件

## 模組功能支援矩陣

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Cart/購物車設定說明.md`
> 功能支援可能隨模組更新而變動，請至官網確認最新支援狀態。

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
| TradeAmount Error | 金額含小數點 | ECPay 不支援小數點金額，將購物車小數位數設為 0 |
| 模組無法安裝 | PHP 版本過低 | WooCommerce / OpenCart 需 PHP 8.2+，Magento 需 PHP 8.1+ |
| 發票未開立 | 發票模組未啟用 | 另外安裝並啟用 ECPay 發票模組（OpenCart 須搭配金流模組） |
| Hash Key/IV 錯誤 | 複製時含空白 | 確認 Hash Key 與 Hash IV 內容不包含空白字元，建議使用複製貼上 |

## 詳細設定

各平台的完整安裝和設定說明：`references/Cart/購物車設定說明.md`（5 個 URL）

## 相關文件

- 購物車設定：`references/Cart/購物車設定說明.md`
- Shopify API：`references/Payment/Shopify專用金流API技術文件.md`
- 如需自訂整合：[guides/01-payment-aio.md](./01-payment-aio.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
