# 各平台安裝指南

> **版本**：V3.4

> 將 ECPay API Skill 安裝到 OpenAI Codex CLI 或 Google Gemini CLI。
> VS Code Copilot Chat 的安裝方式請見 [vscode_copilot.md](./vscode_copilot.md)。
> Visual Studio 2026 的安裝方式請見 [visual_studio_2026.md](./visual_studio_2026.md)。
> Claude Code、GitHub Copilot CLI、Cursor 的安裝方式請見 [README.md](./README.md#1-安裝)。

## 概覽

| 平台 | 入口文件 | 安裝核心步驟 | 跳轉 |
|------|---------|------------|------|
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
完整指南位於 `.ecpay-skill/guides/`（29 份），即時 API 規格索引位於 `.ecpay-skill/references/`。
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

## 共用維護

### 更新 Skill

```bash
cd <skill-path> && git pull origin master
```

| 平台 | 額外步驟 |
|------|---------|
| Codex / Gemini CLI | 無 |

### 常見問題

**Q：AI 找不到 ECPay API Skill？**
確認入口文件位置正確——Codex: `AGENTS.md`、Gemini: `GEMINI.md`。

**Q：Skill 知識過期？**
`git pull origin master` 更新。或提問時指定「請查詢最新 ECPay 官方規格」。

**Q：可和其他 Skill 共存嗎？**
可以。多個支付 Skill 共存時，加上「ECPay」或「綠界」確認來源。

**Q：需要 API Key 嗎？**
不需要。本 Skill 是純知識文件。

---
