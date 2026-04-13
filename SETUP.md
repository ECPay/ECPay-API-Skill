# 各平台安裝指南

> **版本**：V2.7

> 將 ECPay API Skill 安裝到 OpenAI Codex CLI、Google Gemini CLI 或 ChatGPT GPTs。
> VS Code Copilot Chat 的安裝方式請見 [vscode_copilot.md](./vscode_copilot.md)。
> Visual Studio 2026 的安裝方式請見 [visual_studio_2026.md](./visual_studio_2026.md)。
> Claude Code、GitHub Copilot CLI、Cursor 的安裝方式請見 [README.md](./README.md#安裝)。

## 概覽

| 平台 | 入口文件 | 安裝核心步驟 | 跳轉 |
|------|---------|------------|------|
| ChatGPT GPTs | `SKILL_OPENAI.md` | Instructions + Knowledge Files 上傳 | [§ChatGPT GPTs 建置](#chatgpt-gpts-建置) |
| OpenAI Codex CLI | `AGENTS.md` | Clone + AGENTS.md 引用 | [§CLI 安裝](#cli-安裝openai-codex-cli--google-gemini-cli) |
| Google Gemini CLI | `GEMINI.md` | Clone + GEMINI.md 引用 | [§CLI 安裝](#cli-安裝openai-codex-cli--google-gemini-cli) |

---

## CLI 安裝（OpenAI Codex CLI / Google Gemini CLI）

> 兩者流程幾乎相同，差異僅在 CLI 工具名稱與入口文件名。

| 平台 | 訂閱需求 |
|------|---------|
| **Codex CLI** | 需 ChatGPT 付費方案（Plus $20/月以上）或 OpenAI API 額度 |
| **Gemini CLI** | **免費**（個人 Google 帳號即可，每日 1,000 次請求） |

### 步驟 1：安裝 CLI

| 平台 | 安裝 | 官方文件 |
|------|------|---------|
| Codex | `npm install -g @openai/codex` | [github.com/openai/codex](https://github.com/openai/codex) |
| Gemini | `npm install -g @google/gemini-cli` | [github.com/google-gemini/gemini-cli](https://github.com/google-gemini/gemini-cli) |

### 步驟 2：Clone + 設定入口

**方案 A：專案層級（推薦）**

```bash
git clone https://github.com/ECPay/ECPay-API-Skill.git .ecpay-skill
```

在專案根目錄的入口文件（Codex → `AGENTS.md`、Gemini → `GEMINI.md`）末尾追加：

```markdown
## ECPay API Skill
讀取 `.ecpay-skill/<入口文件>` 作為 ECPay 整合知識庫入口。
完整指南位於 `.ecpay-skill/guides/`（28 份），即時 API 規格索引位於 `.ecpay-skill/references/`。
```

**方案 B：全域安裝**

| 平台 | Clone 至 | 入口追加至 |
|------|---------|----------|
| Codex | `~/.codex/ecpay-skill` | `~/.codex/AGENTS.md` |
| Gemini | `~/.gemini/ecpay-skill` | `~/.gemini/GEMINI.md` |

**方案 C：Git Submodule（團隊）**

```bash
git submodule add https://github.com/ECPay/ECPay-API-Skill.git .ecpay-skill
```

### 步驟 3：驗證

```bash
codex "請問綠界 AIO 金流的測試 MerchantID 是什麼？"   # 或 gemini "..."
# 預期：3002607
```

> **Gemini 特有**：Gemini CLI 支援 Google Search，遇 API 參數問題可直接搜尋 `site:developers.ecpay.com.tw`。

---

## ChatGPT GPTs 建置

> 前置條件：可建立 GPT 的 ChatGPT 方案、已 clone 或下載本 repo。

### 步驟 1：開啟 GPT 編輯器

[chatgpt.com/gpts/editor](https://chatgpt.com/gpts/editor) → **Create a GPT** → **Configure**。

### 步驟 2：基本設定

| 欄位 | 建議值 |
|------|--------|
| **Name** | ECPay 綠界科技整合助手 |
| **Description** | 綠界科技官方 API 整合顧問 — 金流、物流、電子發票、電子票證。支援 12 種語言。 |

**Conversation Starters**：
1. 我要用 Node.js 串接信用卡付款，前後端分離架構
2. CheckMacValue 驗證失敗，錯誤碼 10400002
3. 我需要收款後自動開發票再出貨
4. 測試環境可以了，要怎麼切換到正式環境？

### 步驟 3：Knowledge Files（最多 20 個）

> `SKILL_OPENAI.md` 為 GPT 專用精簡版入口（超過 8,000 字元，無法貼入 Instructions 欄位，請直接上傳至 Knowledge）。
> `SKILL.md` 為完整決策樹，作為補充參考一併上傳。若兩者衝突，以 `SKILL_OPENAI.md` 為準。
> `references/` 不需上傳，GPTs 透過 Web Search 存取。

**必上傳（核心）— 14 個**

| # | 檔案 | 用途 |
|---|------|------|
| 1 | `SKILL_OPENAI.md` | GPT 專用入口（精簡版指令） |
| 2 | `SKILL.md` | 完整決策樹與導航 |
| 3 | `guides/01-payment-aio.md` | AIO 金流 |
| 4 | `guides/02-payment-ecpg.md` | 站內付 2.0（hub） |
| 5 | `guides/02a-ecpg-quickstart.md` | 站內付首次串接 |
| 6 | `guides/03-payment-backend.md` | 幕後授權/取號 |
| 7 | `guides/04-invoice-b2c.md` | B2C 電子發票 |
| 8 | `guides/13-checkmacvalue.md` | CheckMacValue 12 語言 |
| 9 | `guides/14-aes-encryption.md` | AES 12 語言 |
| 10 | `guides/15-troubleshooting.md` | 除錯 |
| 11 | `guides/19-http-protocol-reference.md` | HTTP 協議 |
| 12 | `guides/20-error-codes-reference.md` | 錯誤碼 |
| 13 | `guides/21-webhook-events-reference.md` | Webhook |
| 14 | `guides/23-multi-language-integration.md` | 多語言 E2E |

**建議上傳（擴充）— 6 個**

| # | 檔案 | 用途 |
|---|------|------|
| 15 | `guides/00-getting-started.md` | 入門 |
| 16 | `guides/05-invoice-b2b.md` | B2B 發票 |
| 17 | `guides/06-logistics-domestic.md` | 國內物流 |
| 18 | `guides/07-logistics-allinone.md` | 全方位物流 |
| 18 | `guides/09-ecticket.md` | 電子票證 |
| 19 | `guides/11-cross-service-scenarios.md` | 跨服務整合 |

> 共 20 個，達 Knowledge Files 上限。`guides/02b`、`02c`、`guides/16` 為選用，可替換低優先度檔案。

### 步驟 4：Capabilities

- [x] **Web Search** — 必須啟用（即時讀取 `developers.ecpay.com.tw`）
- [x] **Code Interpreter & Data Analysis**
- [ ] Image Generation / Canvas — 不需要

### 步驟 5：發布與驗證

發布後測試：
1. 「我要串接信用卡付款，用 Python」→ 推薦 AIO 或站內付，生成完整程式碼
2. 「站內付 2.0 一直 404」→ 提醒 ecpg vs ecpayment 雙 domain

### 更新維護

1. 更新 `SKILL_OPENAI.md` → 重新上傳至 Knowledge（移除舊版再上傳新版）
2. **移除舊版**再上傳新版 Knowledge Files（避免語意搜尋混淆）

---

## 共用維護

### 更新 Skill

```bash
cd <skill-path> && git pull origin main
```

| 平台 | 額外步驟 |
|------|---------|
| Codex / Gemini CLI | 無 |
| ChatGPT GPTs | 移除舊版 → 上傳新版 Knowledge Files |

### 常見問題

**Q：AI 找不到 ECPay API Skill？**
確認入口文件位置正確——Codex: `AGENTS.md`、Gemini: `GEMINI.md`。

**Q：Skill 知識過期？**
`git pull origin main` 更新。或提問時指定「請查詢最新 ECPay 官方規格」。

**Q：可和其他 Skill 共存嗎？**
可以。多個支付 Skill 共存時，加上「ECPay」或「綠界」確認來源。

**Q：需要 API Key 嗎？**
不需要。本 Skill 是純知識文件。

---

> 技術支援：sysanalydep.sa@ecpay.com.tw
