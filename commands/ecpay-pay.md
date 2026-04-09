---
description: 串接綠界金流收款（AIO / 站內付 2.0 / 幕後授權）、查詢訂單、退款、Callback 處理
---

> **你需要這個指令嗎？**
> - 目標：建立付款表單、接收 callback、查詢訂單狀態 → ✅ 是
> - 目標：排查 CheckMacValue / AES 錯誤 → ❌ 改用 `/ecpay-debug`
> - 目標：上線前確認 → ❌ 改用 `/ecpay-go-live`
> - 目標：加入電子發票 → ❌ 改用 `/ecpay-invoice`（搭配本指令）

使用者需要串接綠界金流。請依以下步驟引導：

1. 先讀取 `SKILL.md` 的金流決策樹，確認適合的方案（AIO / 站內付 2.0 / 幕後授權）
2. 詢問使用者：使用什麼語言/框架？需要哪些付款方式？
3. 根據方案讀取對應 guide：
   - AIO → `guides/01-payment-aio.md`
   - 站內付 2.0 → `guides/02-payment-ecpg.md`
   - 幕後授權 → `guides/03-payment-backend.md`
4. 加密實作參考 `guides/13-checkmacvalue.md`（CMV）或 `guides/14-aes-encryption.md`（AES）
5. 非 PHP 語言同時參考 `guides/20-http-protocol-reference.md`（HTTP 協議細節）
6. **生成程式碼前**，必須從 `references/Payment/` 對應檔案 web_fetch 最新 API 規格

擴充功能（依使用者需求選用）：
- **查詢/對帳** → 對應 guide 的 §查詢訂單 或 §下載對帳檔 區段
- **退款/取消** → 對應 guide 的 §信用卡請款 / 退款 / 取消 區段（僅限信用卡）；跨服務見 `guides/11`
- **Callback** → `guides/22-webhook-events-reference.md`；收不到見 `guides/15` §2

---

## 完成後下一步

- 加入電子發票自動開立 → `/ecpay-invoice`
- 加入物流出貨通知 → `/ecpay-logistics`
- 上線前完整確認 → `/ecpay-go-live`
- 遇到問題 → `/ecpay-debug`
