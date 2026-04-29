---
description: 串接綠界ECTicket（演唱會、電影票、餐券、遊樂園等虛擬票證）
---

> **你需要這個指令嗎？**
> - 目標：發行ECTicket（門票/餐券/課程）→ ✅ 是
> - 目標：票券核銷（使用後核銷/分期核銷）→ ✅ 是
> - ⚠️ ECTicket需向綠界**獨立申請開通**（金流帳號不自動包含）
> - ⚠️ 使用 AES-JSON **+ CheckMacValue**（雙重驗證，與其他服務不同）→ 複雜度較高

使用者需要串接綠界ECTicket。請依以下步驟引導：

1. 讀取 `guides/09-ecticket.md` 了解ECTicket整合流程
2. 測試帳號：官方提供公開測試帳號（見 `guides/09-ecticket.md` §測試帳號）
3. 根據商務模式選擇：
   - 純發行-使用後核銷 → `references/Ecticket/純發行-使用後核銷API技術文件.md`
   - 價金保管-使用後核銷 → `references/Ecticket/價金保管-使用後核銷API技術文件.md`
   - 價金保管-分期核銷 → `references/Ecticket/價金保管-分期核銷API技術文件.md`
4. ECTicket使用 AES-JSON + CMV 協議（AES 加密 + CheckMacValue SHA256 雙重驗證），加密實作參考 `guides/14-aes-encryption.md`，CheckMacValue 計算見 `guides/09-ecticket.md` §CheckMacValue 計算
5. **生成程式碼前**，從 `references/Ecticket/` 對應檔案 web_fetch 最新 API 規格

---

## 完成後下一步

- ECTicket通常與金流一起使用 → `/ecpay-pay` 確認金流已就緒
- 遇到 CheckMacValue 計算問題 → `/ecpay-debug`
- 上線前確認 → `/ecpay-go-live`
