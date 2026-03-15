---
description: 除錯綠界 API 串接問題 + CheckMacValue/AES 加密驗證
---

使用者遇到綠界串接問題或需要加密實作協助。請依以下步驟排查：

1. 先讀取 `SKILL.md` 的除錯決策樹，定位問題類型
2. 根據問題類型讀取對應資源：
   - CheckMacValue 驗證失敗 → `guides/13-checkmacvalue.md` + `guides/15-troubleshooting.md` §1
   - AES 解密亂碼 → `guides/14-aes-encryption.md` §常見錯誤 + 測試向量
   - 錯誤碼查詢 → `guides/21-error-codes-reference.md` 反向索引
   - Callback 收不到 → `guides/22-webhook-events-reference.md` + `guides/15-troubleshooting.md` §2
   - 上線後異常 → `guides/16-go-live-checklist.md` §上線後觀察清單
3. 若需要全新的 CheckMacValue 實作：讀 `guides/13`（12 語言 + 測試向量），用測試向量驗證正確性
4. 若需要確認最新錯誤碼定義，從 `references/` 對應檔案 web_fetch
