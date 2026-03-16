# OpenAI Codex CLI 安裝指南

> **版本**：V1.0 | 對應 AGENTS.md V1.0

> 將 ECPay Skill 安裝到 OpenAI Codex CLI，讓 AI Agent 具備完整的綠界 API 串接能力。
> 前置條件：OpenAI 帳號、Codex CLI 已安裝（`npm install -g @openai/codex`）。

## 步驟 1：安裝 Codex CLI（若尚未安裝）

```bash
npm install -g @openai/codex
codex --version
```

詳細說明：[github.com/openai/codex](https://github.com/openai/codex)

## 步驟 2：Clone ECPay Skill

### 方案 A：專案層級安裝（推薦）

放在開發專案目錄下，只對此專案有效：

```bash
# 在你的開發專案目錄下執行
git clone https://github.com/ECPay/ECPay-API-Skill.git .ecpay-skill
```

然後在專案根目錄的 `AGENTS.md`（若已有）末尾追加，或直接建立新的 `AGENTS.md`：

```bash
cat >> AGENTS.md << 'EOF'

## ECPay Skill

讀取 `.ecpay-skill/AGENTS.md` 作為 ECPay 整合知識庫入口。
完整指南位於 `.ecpay-skill/guides/`（25 份），即時 API 規格索引位於 `.ecpay-skill/references/`。
EOF
```

### 方案 B：全域安裝

所有專案共享，適合個人開發者：

```bash
git clone https://github.com/ECPay/ECPay-API-Skill.git ~/.codex/ecpay-skill
```

在 `~/.codex/AGENTS.md`（若已有）末尾追加：

```bash
mkdir -p ~/.codex
cat >> ~/.codex/AGENTS.md << 'EOF'

## ECPay Skill

讀取 `~/.codex/ecpay-skill/AGENTS.md` 作為 ECPay 整合知識庫入口。
完整指南位於 `~/.codex/ecpay-skill/guides/`，即時規格索引位於 `~/.codex/ecpay-skill/references/`。
EOF
```

### 方案 C：Git Submodule（團隊共用）

固定版本於版控中，適合多人團隊：

```bash
git submodule add https://github.com/ECPay/ECPay-API-Skill.git .ecpay-skill
git submodule update --init
```

## 步驟 3：啟動驗證

```bash
codex "請問綠界 AIO 金流的測試 MerchantID 是什麼？"
# 預期：應回答 3002607，並引用 guides/00 或 AGENTS.md 中的測試帳號資訊
```

## 步驟 4：更新 Skill

```bash
cd .ecpay-skill   # 或 ~/.codex/ecpay-skill
git pull origin main
```

## 使用方式

安裝後，直接在 Codex CLI 對話中用自然語言描述需求：

```
"幫我用 Python 串接綠界 AIO 信用卡付款"
"CheckMacValue 一直驗證失敗，這是我的程式碼..."
"我想開電子發票給 B2B 客戶，需要哪些參數？"
```

## 常見問題

**Q：Codex CLI 找不到 AGENTS.md？**
確認 `AGENTS.md` 位於 Codex CLI 執行目錄的父目錄鏈中，或全域設定於 `~/.codex/AGENTS.md`。

**Q：Skill 知識過期了怎麼辦？**
執行 `git pull origin main` 更新，或在提問時指定「請使用 web_fetch 查詢最新 ECPay 官方規格」。

**Q：如何同時使用既有 AGENTS.md 和 ECPay Skill？**
在現有 `AGENTS.md` 末尾追加 ECPay Skill 的引用即可，不需要替換整個檔案。
