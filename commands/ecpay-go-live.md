---
description: 綠界 API 上線前檢查清單（測試環境 → 正式環境切換）
---

使用者準備將綠界串接從測試環境切換到正式環境。請依以下步驟引導：

1. 讀取 `guides/16-go-live-checklist.md` 完整上線清單
2. 確認以下關鍵項目：
   - 測試帳號 → 正式帳號（MerchantID、HashKey、HashIV）
   - Domain 切換（所有 `-stage` 移除）
   - 站內付 2.0 雙 Domain 確認（ecpg vs ecpayment）
   - Callback URL 設為正式環境的公開 URL
   - HTTPS 強制（正式環境不接受 HTTP callback）
3. 若有金流 + 發票 + 物流整合，參考 `guides/11-cross-service-scenarios.md` 確認所有服務都已切換
4. 從 `references/` web_fetch 最新規格確認端點路徑無異動
