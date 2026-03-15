# ChatGPT GPTs 建置指南

> **版本**：V1.0 | 對應 SKILL_OPENAI.md V1.0

> 將 ECPay Skill 設定到 ChatGPT GPTs。
> 前置條件：具備可建立 GPT 的 ChatGPT 方案、已 clone 或下載本 repo。

## 步驟 1：開啟 GPT 編輯器

前往 [chatgpt.com/gpts/editor](https://chatgpt.com/gpts/editor) → **Create a GPT** → **Configure** 頁籤。

## 步驟 2：基本設定

| 欄位 | 建議值 |
|------|--------|
| **Name** | ECPay 綠界科技整合助手 |
| **Description** | 綠界科技官方 API 整合顧問 — 金流、物流、電子發票、電子票證串接、除錯、上線檢查。支援 12 種程式語言。 |

**Conversation Starters**（共 4 個）：

| Starter |
|---------|
| 我要用 Node.js 串接信用卡付款，前後端分離架構 |
| CheckMacValue 驗證失敗，錯誤碼 10400002 |
| 我需要收款後自動開發票再出貨 |
| 測試環境可以了，要怎麼切換到正式環境？ |

## 步驟 3：貼入 System Instructions

將 **`SKILL_OPENAI.md`** 的全部內容複製貼入 **Instructions** 欄位。

## 步驟 4：上傳 Knowledge Files（最多 20 個）

> `references/` 下的 URL 索引檔不需上傳，GPTs 透過 Web Search 直接存取 `developers.ecpay.com.tw`。
> 若已上傳的 Knowledge Files（包含 `SKILL.md`）與 `SKILL_OPENAI.md` 有衝突，請以 `SKILL_OPENAI.md` 的 OpenAI 專用規則為準。

### 必上傳（核心）— 12 個檔案

| # | 檔案 | 用途 |
|---|------|------|
| 1 | `SKILL.md` | 完整決策樹與導航（若與 `SKILL_OPENAI.md` 衝突，以後者為準） |
| 2 | `guides/01-payment-aio.md` | AIO 金流（最常用） |
| 3 | `guides/02-payment-ecpg.md` | 站內付 2.0 |
| 4 | `guides/03-payment-backend.md` | 幕後授權/取號 |
| 5 | `guides/04-invoice-b2c.md` | B2C 電子發票 |
| 6 | `guides/13-checkmacvalue.md` | CheckMacValue 12 語言實作 |
| 7 | `guides/14-aes-encryption.md` | AES 加解密 12 語言實作 |
| 8 | `guides/15-troubleshooting.md` | 除錯指南 |
| 9 | `guides/20-http-protocol-reference.md` | HTTP 協議參考（跨語言必備） |
| 10 | `guides/21-error-codes-reference.md` | 錯誤碼參考 |
| 11 | `guides/22-webhook-events-reference.md` | Webhook 參考 |
| 12 | `guides/24-multi-language-integration.md` | 多語言 E2E 範例 |

### 建議上傳（擴充）— 8 個檔案

| # | 檔案 | 用途 |
|---|------|------|
| 13 | `guides/00-getting-started.md` | 入門指南 |
| 14 | `guides/05-invoice-b2b.md` | B2B 電子發票 |
| 15 | `guides/06-logistics-domestic.md` | 國內物流 |
| 16 | `guides/07-logistics-allinone.md` | 全方位物流 |
| 17 | `guides/09-ecticket.md` | 電子票證 |
| 18 | `guides/11-cross-service-scenarios.md` | 跨服務整合場景 |
| 19 | `guides/12-sdk-reference.md` | PHP SDK 參考 |
| 20 | `guides/16-go-live-checklist.md` | 上線檢查清單 |

> 以上共 20 個檔案（必上傳 12 + 建議上傳 8），剛好達到 OpenAI Knowledge Files 上限。新版本更新時，優先替換「建議上傳」組的舊版檔案。
>
> **超出上限的 guides**：guides/08（跨境物流）、guides/10（購物車）、guides/17（POS）、guides/18（直播收款）、guides/19（離線發票）、guides/23（效能調校）共 6 個無法上傳。若問題屬於官方 API 規格（例如 guides/08、guides/19），GPT 可透過 Web Search 存取 `developers.ecpay.com.tw` 補充；若屬 repo 內整理的整合或運維指南（例如 guides/10、guides/17、guides/18、guides/23），Web Search 不能完全替代，建議以較低優先度的已上傳檔案進行替換。

## 步驟 5：設定 Capabilities

- [x] **Web Search** — **必須啟用**：即時讀取 `developers.ecpay.com.tw` 最新 API 規格
- [x] **Code Interpreter & Data Analysis** — 協助計算 CheckMacValue、除錯加密邏輯
- [ ] Image Generation / Canvas — 不需要

> **Web Search 失效備援**：若 GPT 無法連線 `developers.ecpay.com.tw`，將自動改用 Knowledge Files 中的 `guides/20`（HTTP 協議規格）與 `guides/21`（錯誤碼）作為備援，並提示使用者建議手動確認最新規格。此備援邏輯已內建於 `SKILL_OPENAI.md` 的 Live API Spec Access 段落。

## 步驟 6：發布

點選 **Create/Update**，選擇發布範圍：Only me / Anyone with the link / Everyone（GPT Store）。

## 驗證測試

```
1. 「我要串接信用卡付款，用 Python」
   → 預期：推薦 AIO 或站內付 2.0，生成含 CheckMacValue 的完整程式碼

2. 「站內付 2.0 一直 404」
   → 預期：提醒 ecpg vs ecpayment 雙 domain 問題
```

## 更新維護

當 ECPay Skill 有新版本時：
1. 更新 `SKILL_OPENAI.md` → 重新貼入 Instructions
2. 重新上傳更新過的 guides/ 檔案到 Knowledge Files
3. **移除舊版檔案**再上傳新版（OpenAI 用語意搜尋，重複檔案會造成混淆）
