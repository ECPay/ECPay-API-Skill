---
description: 串接綠界電子發票（B2C / B2B / 離線）
---

使用者需要串接綠界電子發票。請依以下步驟引導：

1. 詢問使用者：開給消費者（B2C）還是企業（B2B）？有無網路環境？
2. 根據類型讀取對應 guide：
   - B2C → `guides/04-invoice-b2c.md`
   - B2B → `guides/05-invoice-b2b.md`
   - 離線 → `guides/19-invoice-offline.md`
3. 若需要金流 + 發票整合（收款後自動開票），讀 `guides/11-cross-service-scenarios.md` 場景一
4. **生成程式碼前**，必須從 `references/Invoice/` 對應檔案 web_fetch 最新 API 規格
5. AES 加密實作參考 `guides/14-aes-encryption.md`
