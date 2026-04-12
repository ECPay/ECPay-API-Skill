# ECPay API Reference Index

本目錄包含 ECPay 官方開發者文件（developers.ecpay.com.tw）的 URL 索引。

## 用途

- **AI 工具**：透過 `web_fetch` 讀取對應 URL 取得最新 API 規格
- **開發者**：手動查閱參數定義與端點規格
- **CI**：定期驗證 URL 可達性

## 目錄結構

**快速跳轉**：[金流](#payment-金流) | [發票](#invoice-電子發票) | [物流](#logistics-物流) | [電子票證](#ecticket-電子票證) | [購物車](#cart-購物車)

### Payment 金流

| 服務 | 檔案 | URL 數量 | 對應指南 |
|------|------|---------|---------|
| AIO 全方位金流 | `Payment/全方位金流API技術文件.md` | 45 | [guides/01](../guides/01-payment-aio.md) |
| 站內付 2.0 (Web) | `Payment/站內付2.0API技術文件Web.md` | 34 | [guides/02](../guides/02-payment-ecpg.md) |
| 站內付 2.0 (App) | `Payment/站內付2.0API技術文件App.md` | 39 | [guides/02](../guides/02-payment-ecpg.md) |
| 信用卡幕後授權 | `Payment/信用卡幕後授權API技術文件.md` | 16 | [guides/03](../guides/03-payment-backend.md) |
| 非信用卡幕後取號 | `Payment/非信用卡幕後取號API技術文件.md` | 15 | [guides/03](../guides/03-payment-backend.md) |
| POS 刷卡機 | `Payment/刷卡機POS串接規格.md` | 13 | [guides/17 §POS](../guides/17-hardware-services.md#pos-刷卡機串接指引) |
| 直播收款 | `Payment/直播主收款網址串接技術文件.md` | 7 | [guides/17 §直播](../guides/17-hardware-services.md#直播收款指引) |
| Shopify 金流 | `Payment/Shopify專用金流API技術文件.md` | 5 | [guides/10](../guides/10-cart-plugins.md) |

### Invoice 電子發票

| 服務 | 檔案 | URL 數量 | 對應指南 |
|------|------|---------|---------|
| B2C 電子發票 | `Invoice/B2C電子發票介接技術文件.md` | 36 | [guides/04](../guides/04-invoice-b2c.md) |
| B2B 發票（交換模式） | `Invoice/B2B電子發票API技術文件_交換模式.md` | 36 | [guides/05](../guides/05-invoice-b2b.md) |
| B2B 發票（存證模式） | `Invoice/B2B電子發票API技術文件_存證模式.md` | 24 | [guides/05](../guides/05-invoice-b2b.md) |
| 離線電子發票 | `Invoice/離線電子發票API技術文件.md` | 21 | [guides/18](../guides/18-invoice-offline.md) |

### Logistics 物流

| 服務 | 檔案 | URL 數量 | 對應指南 |
|------|------|---------|---------|
| 國內物流（CMV-MD5） | `Logistics/物流整合API技術文件.md` | 36 | [guides/06](../guides/06-logistics-domestic.md) |
| 全方位物流 v2（AES-JSON） | `Logistics/全方位物流服務API技術文件.md` | 27 | [guides/07](../guides/07-logistics-allinone.md) |
| 跨境物流 | `Logistics/綠界科技跨境物流API技術文件.md` | 13 | [guides/08](../guides/08-logistics-crossborder.md) |

### Ecticket 電子票證

| 服務 | 檔案 | URL 數量 | 對應指南 |
|------|------|---------|---------|
| 價金保管（使用後核銷） | `Ecticket/價金保管-使用後核銷API技術文件.md` | 20 | [guides/09](../guides/09-ecticket.md) |
| 價金保管（分期核銷） | `Ecticket/價金保管-分期核銷API技術文件.md` | 12 | [guides/09](../guides/09-ecticket.md) |
| 純發行（使用後核銷） | `Ecticket/純發行-使用後核銷API技術文件.md` | 23 | [guides/09](../guides/09-ecticket.md) |

### Cart 購物車

| 服務 | 檔案 | URL 數量 | 對應指南 |
|------|------|---------|---------|
| 購物車設定 | `Cart/購物車設定說明.md` | 5 | [guides/10](../guides/10-cart-plugins.md) |

**合計**：19 個檔案，約 431 個 URL。

## 使用方式

每個檔案開頭包含 AI 指令，說明如何使用 `web_fetch` 取得最新規格。檔案內容為分類 URL 列表，指向 `developers.ecpay.com.tw` 的對應頁面。

guides/ 中的參數表標記為 **SNAPSHOT**，代表靜態快照。生成程式碼前，應透過本目錄的 URL 索引 `web_fetch` 取得最新規格。
