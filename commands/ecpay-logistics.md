---
description: 串接綠界物流（國內超商/宅配、全方位 v2、跨境）
---

使用者需要串接綠界物流。請依以下步驟引導：

1. 詢問使用者：國內還是跨境？超商取貨還是宅配？
2. 根據類型讀取對應 guide：
   - 國內物流（超商/宅配）→ `guides/06-logistics-domestic.md`
   - 全方位物流 v2 → `guides/07-logistics-allinone.md`
   - 跨境物流 → `guides/08-logistics-crossborder.md`
3. 若需要金流 + 物流整合，讀 `guides/11-cross-service-scenarios.md`
4. 國內物流用 CMV-MD5（`guides/13`），全方位/跨境物流用 AES（`guides/14`）
5. **生成程式碼前**，從 `references/Logistics/` 對應檔案 web_fetch 最新 API 規格
