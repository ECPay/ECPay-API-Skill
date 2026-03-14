---
description: 串接綠界電子票證（演唱會、電影票、餐券、遊樂園等虛擬票證）
---

使用者需要串接綠界電子票證。請依以下步驟引導：

1. 讀取 `guides/09-ecticket.md` 了解電子票證整合流程
2. 測試帳號：官方提供公開測試帳號（見 `guides/09-ecticket.md` §測試帳號）
3. 根據商務模式選擇：
   - 純發行-使用後核銷 → `references/Ecticket/純發行-使用後核銷API技術文件.md`
   - 價金保管-使用後核銷 → `references/Ecticket/價金保管-使用後核銷API技術文件.md`
   - 價金保管-分期核銷 → `references/Ecticket/價金保管-分期核銷API技術文件.md`
4. 電子票證使用 AES-JSON + CMV 協議（AES 加密 + CheckMacValue SHA256 雙重驗證），加密實作參考 `guides/14-aes-encryption.md`，CheckMacValue 計算見 `guides/09-ecticket.md` §CheckMacValue 計算
5. **生成程式碼前**，從 `references/Ecticket/` 對應檔案 web_fetch 最新 API 規格
