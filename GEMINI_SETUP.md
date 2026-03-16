# Google Gemini CLI 安裝指南

> **版本**：V1.0 | 對應 GEMINI.md V1.0

> 將 ECPay Skill 安裝到 Google Gemini CLI，讓 AI Agent 具備完整的綠界 API 串接能力。
> 前置條件：Google 帳號、Gemini CLI 已安裝（`npm install -g @google/gemini-cli`）。

## 步驟 1：安裝 Gemini CLI（若尚未安裝）

```bash
npm install -g @google/gemini-cli
gemini --version
```

詳細說明：[github.com/google-gemini/gemini-cli](https://github.com/google-gemini/gemini-cli)

## 步驟 2：Clone ECPay Skill

### 方案 A：專案層級安裝（推薦）

放在開發專案目錄下，只對此專案有效：

```bash
# 在你的開發專案目錄下執行
git clone https://github.com/ECPay/ECPay-API-Skill.git .ecpay-skill
```

然後在專案根目錄建立（或追加至）`GEMINI.md`：

```bash
cat >> GEMINI.md << 'EOF'

## ECPay Skill

讀取 `.ecpay-skill/GEMINI.md` 作為 ECPay 整合知識庫入口。
完整指南位於 `.ecpay-skill/guides/`（25 份），即時 API 規格索引位於 `.ecpay-skill/references/`。
EOF
```

### 方案 B：全域安裝

所有專案共享，適合個人開發者：

```bash
git clone https://github.com/ECPay/ECPay-API-Skill.git ~/.gemini/ecpay-skill
```

在 `~/.gemini/GEMINI.md`（若已有）末尾追加：

```bash
mkdir -p ~/.gemini
cat >> ~/.gemini/GEMINI.md << 'EOF'

## ECPay Skill

讀取 `~/.gemini/ecpay-skill/GEMINI.md` 作為 ECPay 整合知識庫入口。
完整指南位於 `~/.gemini/ecpay-skill/guides/`，即時規格索引位於 `~/.gemini/ecpay-skill/references/`。
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
gemini "請問綠界 AIO 金流的測試 MerchantID 是什麼？"
# 預期：應回答 3002607，並引用 guides/00 或 GEMINI.md 中的測試帳號資訊
```

## 步驟 4：更新 Skill

```bash
cd .ecpay-skill   # 或 ~/.gemini/ecpay-skill
git pull origin main
```

## 使用方式

安裝後，直接在 Gemini CLI 對話中用自然語言描述需求：

```
"幫我用 Go 串接綠界 AIO 信用卡付款"
"AES 解密失敗，這是我的程式碼..."
"我想開電子發票給 B2C 客戶，需要哪些參數？"
```

Gemini CLI 支援 Google Search，當遇到具體 API 參數問題時，可直接搜尋 `site:developers.ecpay.com.tw` 取得最新官方規格，不需要切換工具。

## 常見問題

**Q：Gemini CLI 找不到 GEMINI.md？**
確認 `GEMINI.md` 位於 Gemini CLI 執行目錄或全域設定於 `~/.gemini/GEMINI.md`。

**Q：Skill 知識過期了怎麼辦？**
執行 `git pull origin main` 更新，或在提問時指定「請 Google Search 查詢最新 ECPay 官方規格」。

**Q：如何同時使用既有 GEMINI.md 和 ECPay Skill？**
在現有 `GEMINI.md` 末尾追加 ECPay Skill 的引用即可，不需要替換整個檔案。
