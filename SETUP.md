# 各平台安裝指南

> **版本**：V2.7

> 將 ECPay Skill 安裝到 OpenAI Codex CLI、Google Gemini CLI、OpenClaw 或 ChatGPT GPTs。
> VS Code Copilot Chat 的安裝方式請見 [vscode_copilot.md](./vscode_copilot.md)。
> Visual Studio 2026 的安裝方式請見 [visual_studio_2026.md](./visual_studio_2026.md)。
> Claude Code、GitHub Copilot CLI、Cursor、Windsurf 的安裝方式請見 [README.md](./README.md#安裝)。

## 概覽

| 平台 | 入口文件 | 安裝核心步驟 | 適合對象 | 跳轉 |
|------|---------|------------|---------|------|
| **Google AI Studio** | `SKILL.md` + `SKILL_OPENAI.md` | 上傳檔案 + 貼 System Instructions | **非技術人員首選（免費）** | [§Google AI Studio](#google-ai-studio免費適合非技術人員快速測試) |
| ChatGPT GPTs | `SKILL_OPENAI.md` | Instructions + Knowledge Files 上傳 | 需長期使用或分享團隊 | [§ChatGPT GPTs 建置](#chatgpt-gpts-建置) |
| OpenAI Codex CLI | `AGENTS.md` | Clone + AGENTS.md 引用 | 工程師 | [§CLI 安裝](#cli-安裝openai-codex-cli--google-gemini-cli) |
| Google Gemini CLI | `GEMINI.md` | Clone + GEMINI.md 引用 | 工程師 | [§CLI 安裝](#cli-安裝openai-codex-cli--google-gemini-cli) |
| OpenClaw | `SKILL.md` | Clone → skills watcher 自動偵測 | 工程師 | [§OpenClaw 安裝](#openclaw-安裝) |

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
## ECPay Skill
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

## OpenClaw 安裝

> 前置條件：Node 22.14+（LTS）或 Node 24（推薦）、`npm install -g openclaw@latest`。
> 詳細說明：[docs.openclaw.ai](https://docs.openclaw.ai/start/getting-started)

### 步驟 1：Clone

```bash
# 方案 A：個人共用（推薦，所有 agent 共享）
git clone https://github.com/ECPay/ECPay-API-Skill.git ~/.openclaw/skills/ecpay

# 方案 B：Workspace 層級（僅當前專案）
git clone https://github.com/ECPay/ECPay-API-Skill.git skills/ecpay
```

### 步驟 2：確認載入

```bash
openclaw gateway --port 18789   # 若尚未啟動
openclaw doctor                 # 確認第 11 項 Skill 載入狀態
```

Skills watcher 自動偵測新 `SKILL.md`，下個新 session 生效，不需重啟 Gateway。

### 步驟 3：驗證

在任何已連接頻道（WhatsApp / Telegram / Slack / Discord / LINE 等）傳送：

> 「綠界 AIO 金流的測試 MerchantID 是多少？」→ 預期：**3002607**

---

## Google AI Studio（免費、適合非技術人員快速測試）

> **適合對象**：非技術人員、PM、業務、想快速了解 ECPay API 串接的人。
> **優點**：免費、免安裝、有 Google 帳號即可使用、支援中文對話。
> **限制**：非持久化（每次需重新設定），適合測試和諮詢，不適合長期使用。長期使用建議改用 ChatGPT GPTs（§ChatGPT GPTs 建置）。

### 步驟 1：開啟 Google AI Studio

用瀏覽器打開 **[aistudio.google.com](https://aistudio.google.com)**，使用 Google 帳號登入。

> 💡 **完全免費**，不需要信用卡、不需要 API Key。

### 步驟 2：進入 Playground

登入後會自動進入 **Playground** 頁面（左側選單第一項）。這就是對話介面，不需要額外建立。

在右側 **Run settings** 面板中，點選最上方的模型名稱（預設為 **Gemini 3 Flash**）。如需更強的推理能力，可切換為 **Gemini 2.5 Pro**（穩定版）或 **Gemini 3.1 Pro**（預覽版）。

### 步驟 3：設定 System Instructions

在右側 **Run settings** 面板中，點選 **System instructions** 展開欄位，貼入以下內容：

```
你是綠界科技 ECPay 的專業整合顧問。幫助開發者無痛串接金流、物流、電子發票、電子票證等所有 ECPay 服務。

核心規則：
1. 僅支援新台幣 (TWD)，所有服務僅限台灣
2. 回答必須基於上傳的文件內容，不可猜測 API 參數、端點或加密細節
3. 遇到不確定的 API 規格時，使用 Google Search 搜尋 site:developers.ecpay.com.tw 取得最新資訊
4. ECPay 有四種協議模式：
   - CMV-SHA256（AIO 金流）
   - AES-JSON（站內付 2.0、電子發票、全方位物流）
   - AES-JSON + CMV（電子票證）
   - CMV-MD5（國內物流）
5. 站內付 2.0 使用兩個不同 Domain：ecpg(-stage).ecpay.com.tw（Token API）和 ecpayment(-stage).ecpay.com.tw（查詢/請款/退款 API），打錯會 404
6. 測試環境 MerchantID: 3002607（金流）、2000132（發票/物流）

請用繁體中文回答。回答時先判斷使用者需要哪個服務，再從上傳的文件中找到對應指南提供具體指引。
```

### 步驟 4：上傳文件

在底部輸入列中，點選 **⊕（加號）** 按鈕上傳檔案。

> ⚠️ 透過 ⊕ 上傳的檔案會附加在當前對話中。建議在**第一則訊息送出前**一次上傳所有檔案，這樣整段對話都能參考這些文件。

> 📥 **檔案下載方式**：
> 1. 前往 [github.com/ECPay/ECPay-API-Skill](https://github.com/ECPay/ECPay-API-Skill)
> 2. 點選綠色 **Code** 按鈕 → **Download ZIP**
> 3. 解壓縮後，從資料夾中找到以下檔案上傳

**必上傳（核心知識）— 7 個**

| # | 檔案路徑 | 用途 | 大小參考 |
|---|---------|------|---------|
| 1 | `SKILL.md` | 完整決策樹與導航（AI 主入口） | ~58 KB |
| 2 | `SKILL_OPENAI.md` | 精簡版指令與核心規則 | ~21 KB |
| 3 | `guides/00-getting-started.md` | 從零開始入門 | ~66 KB |
| 4 | `guides/01-payment-aio.md` | AIO 金流（最常用） | ~66 KB |
| 5 | `guides/02a-ecpg-quickstart.md` | 站內付 2.0 首次串接 | ~79 KB |
| 6 | `guides/15-troubleshooting.md` | 除錯指南 | ~56 KB |
| 7 | `guides/20-http-protocol-reference.md` | HTTP 協議規格 | ~50 KB |

**建議加上傳（依需求選擇）**

| # | 檔案路徑 | 何時需要 |
|---|---------|---------|
| 8 | `guides/04-invoice-b2c.md` | 需要電子發票功能 |
| 9 | `guides/06-logistics-domestic.md` | 需要超商取貨/物流 |
| 10 | `guides/09-ecticket.md` | 需要電子票證 |
| 11 | `guides/13-checkmacvalue.md` | 需要非 PHP 語言的加密實作 |
| 12 | `guides/14-aes-encryption.md` | 需要非 PHP 語言的 AES 加密 |
| 13 | `guides/11-cross-service-scenarios.md` | 需要收款+發票+出貨整合 |

> ⚠️ Google AI Studio 單次上傳有檔案大小限制。若檔案過大無法上傳，優先上傳前 7 個核心檔案即可。

### 步驟 5：確認 Google Search 已啟用（通常預設開啟）

**Grounding with Google Search** 通常預設已啟用。請確認底部輸入列顯示 **Google Search** 標籤，或在右側 **Run settings** 面板的 **Tools** 區塊中確認開關為開啟狀態。

若未啟用，可透過以下方式開啟：
- 在底部輸入列點選 **Tools** 按鈕 → 啟用 **Grounding with Google Search**
- 或在右側 **Run settings** 面板中，展開 **Tools** 區塊 → 開啟開關

> 啟用後，AI 可即時搜尋 ECPay 官方文件補充回答。

### 步驟 6：開始對話

直接用中文輸入你的問題，例如：

| 你可以這樣問 | AI 會做什麼 |
|-------------|-----------|
| 「我要用信用卡收款，最簡單的方式是什麼？」 | 推薦 AIO 金流或站內付 2.0，提供步驟 |
| 「測試環境的帳號密碼是什麼？」 | 提供 MerchantID、HashKey、HashIV |
| 「我的 CheckMacValue 驗證一直失敗」 | 引導除錯流程 |
| 「我要收款後自動開發票再出貨」 | 提供跨服務整合指引 |
| 「幫我用 Python 寫一個信用卡付款的範例」 | 生成完整可執行程式碼 |
| 「錯誤碼 10400002 是什麼意思？」 | 查詢錯誤碼並提供解決方案 |
| 「測試好了，要怎麼切換到正式環境？」 | 提供上線檢查清單 |

### 步驟 7：儲存對話（選用）

Google AI Studio 的對話會自動儲存在你的帳號中，下次開啟可繼續。
但 **System Instructions 和上傳的檔案不會自動保留到新對話**——每次建立新對話需重新設定。

> 💡 **省時技巧**：完成步驟 3-5 後，點選左側選單中的 **Enable saving** 按鈕儲存整個 Prompt 設定，下次可直接從 Playground 歷史中載入。

### 常見問題

**Q：需要付費嗎？**
不需要。Google AI Studio 提供免費額度，足夠日常測試使用。

**Q：跟 ChatGPT GPTs 差在哪？**
ChatGPT GPTs 設定一次後可永久使用並分享給他人；Google AI Studio 每次新對話需重新載入（除非儲存為範本）。長期使用或需分享給團隊，建議用 ChatGPT GPTs。

**Q：AI 回答的內容準確嗎？**
AI 的回答基於上傳的官方文件，準確度高。但涉及具體 API 參數時，建議請工程師用 `developers.ecpay.com.tw` 確認最新規格。

**Q：可以分享給同事嗎？**
可以。將儲存的 Prompt 範本匯出分享，或將本指南與 ZIP 檔一併提供給同事。

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
| 11 | `guides/20-http-protocol-reference.md` | HTTP 協議 |
| 12 | `guides/21-error-codes-reference.md` | 錯誤碼 |
| 13 | `guides/22-webhook-events-reference.md` | Webhook |
| 14 | `guides/24-multi-language-integration.md` | 多語言 E2E |

**建議上傳（擴充）— 6 個**

| # | 檔案 | 用途 |
|---|------|------|
| 15 | `guides/00-getting-started.md` | 入門 |
| 16 | `guides/05-invoice-b2b.md` | B2B 發票 |
| 17 | `guides/06-logistics-domestic.md` | 國內物流 |
| 18 | `guides/07-logistics-allinone.md` | 全方位物流 |
| 19 | `guides/09-ecticket.md` | 電子票證 |
| 20 | `guides/11-cross-service-scenarios.md` | 跨服務整合 |

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
| OpenClaw | 開新 session 即生效 |
| ChatGPT GPTs | 移除舊版 → 上傳新版 Knowledge Files |

### 常見問題

**Q：AI 找不到 ECPay Skill？**
確認入口文件位置正確——Codex: `AGENTS.md`、Gemini: `GEMINI.md`、OpenClaw: `~/.openclaw/skills/ecpay/SKILL.md`。

**Q：Skill 知識過期？**
`git pull origin main` 更新。或提問時指定「請查詢最新 ECPay 官方規格」。

**Q：可和其他 Skill 共存嗎？**
可以。多個支付 Skill 共存時，加上「ECPay」或「綠界」確認來源。

**Q：需要 API Key 嗎？**
不需要。本 Skill 是純知識文件。

---

> 技術支援：sysanalydep.sa@ecpay.com.tw
