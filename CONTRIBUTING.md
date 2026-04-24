# 貢獻指南

<!-- English summary for international contributors -->
<details>
<summary><strong>English Summary for International Contributors</strong></summary>

This is a **Markdown-only AI knowledge base** (no build system, no package manager). All content is consumed by AI coding assistants (Claude Code, VS Code Copilot Chat, GitHub Copilot CLI, Cursor, ChatGPT GPTs, etc.).

**Architecture**: `SKILL.md` (AI entry point) → `guides/` (29 integration guides with SNAPSHOT parameter tables) → `references/` (20 files with 443 live API spec URLs for `web_fetch`)

**To contribute**:
1. Fork the repo and create a feature branch
2. Follow the conventions below (SNAPSHOT dates, lang-standards, timing-safe patterns)
3. Run validation scripts before submitting a PR:
   - `bash scripts/validate-ai-index.sh` (if editing guides/13, 14, or 23)
   - `pip install pycryptodome && python test-vectors/verify.py` (if editing crypto examples)
   - `bash scripts/validate-agents-parity.sh` (if editing AGENTS.md or GEMINI.md)
   - `bash scripts/validate-version-sync.sh` (if bumping the version)
4. Open a Pull Request with a clear description of what changed and why

**Security**: Never commit real MerchantID / HashKey / HashIV. Use official test credentials only. See [SECURITY.md](./SECURITY.md).

**Contact**: techsupport@ecpay.com.tw | GitHub Issues: https://github.com/ECPay/ECPay-API-Skill/issues

</details>

感謝您有興趣為 ECPay API Skill 做出貢獻！

## 回報問題

- 在 Issues 中描述問題，附上：使用的 AI 平台（Claude Code / VS Code Copilot Chat / Copilot CLI / Cursor / ChatGPT GPTs）、重現步驟、預期行為
- API 規格錯誤請附上 `developers.ecpay.com.tw` 對應頁面截圖或連結

## 安全漏洞通報

詳見 [SECURITY.md](./SECURITY.md)。

**重點提醒**：安全漏洞請**不要**在公開 Issues 中提交，請參閱 [SECURITY.md](./SECURITY.md) 的回報流程。

## 修改指南

1. Fork 本 repo 並建立 feature branch
2. 修改時遵守以下原則：
   - **guides/** 內的參數表為 SNAPSHOT，修改時同步更新 `SNAPSHOT 2026-XX` 標記
   - **references/** 內為 URL 索引，維持 blockquote AI 指令標頭 + 章節 URL 列表格式
   - 更新 **guides/13、14、23** 的章節結構後，執行 `bash scripts/validate-ai-index.sh` 確認 AI Section Index 行號索引正確
   - **commands/** 為 Claude Code 快速指令，保持精簡（每個 ≤ 20 行）
   - **CI 驗證範圍**：所有 `guides/` 下的修改都會觸發完整 CI pipeline（validate.yml），其中 AI Section Index 行號驗證步驟（validate-ai-index.sh）只驗證 guides/13、14、23；其餘 25 份 guide 的修改仍會觸發 CI，但不會執行 AI Section Index 行號驗證，修改後可手動執行 `bash scripts/validate-ai-index.sh` 確認（若有修改章節結構）
   - **AGENTS.md / GEMINI.md / SKILL_OPENAI.md 同步**：
     - **AGENTS.md ↔ GEMINI.md**：決策樹、關鍵規則、測試帳號三個區段必須完全一致（由 `validate-agents-parity.sh` CI 強制執行）
     - **SKILL_OPENAI.md**：使用英文、不同段落標題，可包含 GPT 平台專屬規則；但所有安全關鍵規則（如「不可假設 JSON 回應」、「ATM RtnCode=2 為取號成功」）必須同步反映在 AGENTS.md / GEMINI.md 中
     - 修改關鍵規則時需同步更新三個檔案，同時在 `validate-agents-parity.sh` 的 Part 1b 新增對應關鍵詞 grep 檢查，防止 AI 在不同平台行為不一致
3. 確認 SKILL.md / SKILL_OPENAI.md / README.md / SETUP.md / AGENTS.md / GEMINI.md 的版本號一致
4. 提交 Pull Request 並說明變更原因

## Guide 編號規則

guides/ 目錄下的指南依以下規則編號：

| 範圍 | 主題 | 說明 |
|------|------|------|
| 00 | 入門 | 術語、環境設定、Quick Start |
| 01-03 | 金流 | 01=AIO、02=ECPG（含 02a/b/c 子指南）、03=幕後授權/取號 |
| 04-05 | 電子發票 | 04=B2C、05=B2B |
| 06-09 | 物流與電子票證 | 06=國內物流、07=全方位物流、08=跨境物流、09=電子票證 |
| 10-12 | 跨服務與參考 | 10=購物車、11=跨服務整合、12=SDK 工具函式 |
| 13-14 | 加密基礎 | 13=CheckMacValue、14=AES（各含 12 語言實作） |
| 15-17 | 營運 | 15=除錯、16=上線、17=硬體服務 |
| 18-24 | 進階 | 18=離線發票、19=HTTP 協議、20=錯誤碼、21=Webhook、22=效能、23=多語言 E2E、24=本地開發 |

### 拆分規則

- **子指南**（02a/b/c）：同一服務有多種學習路徑時使用（如 02a=快速入門、02b=特定付款管道、02c=上線部署）
- **序號**（06/07/08）：不同服務類型使用獨立序號（國內物流 vs 全方位物流 vs 跨境物流是獨立 API）
- **新增指南**：在 24 之後遞增

### guides/02x 所有權

guides/02、02a、02b、02c 形成一個單元。修改其中任一檔案時，應檢查其他三個檔案是否需要同步更新。

## 目錄結構規範

| 目錄 | 用途 | 修改注意事項 |
|------|------|-------------|
| `guides/` | AI 知識文件 | 保持 SNAPSHOT 標記一致，參數表附來源 reference 路徑 |
| `references/` | 官方 API URL 索引 | 維持 blockquote AI 指令標頭 + 章節 URL 列表格式 |
| `scripts/SDK_PHP/` | 官方 PHP 範例 | 僅追蹤官方 SDK 更新，不自行修改 |
| `commands/` | Claude Code 指令 | 指令負責導航，不重複 SKILL.md 的 SNAPSHOT 邏輯 |

## 新增語言支援

1. **語言規範**：建立 `guides/lang-standards/{language}.md`（命名、型別、錯誤處理、HTTP、Callback、URL Encode 注意）
2. **加密函式**（guides/13, 14）：需提供 timing-safe 比較 + 測試向量驗證
3. **E2E 範例**（guides/23）：提供安裝指令、框架選擇、與 Go 參考版的差異點
4. **更新 AI Section Index**：在 guides/13、14、23 的 HTML comment 索引中加入新語言的行號範圍，並執行 `bash scripts/validate-ai-index.sh` 確認正確
5. 更新 SKILL.md 語言計數、語言特定陷阱表和 lang-standards 對照表

## 維護指引

### 定期驗證（建議每季執行）

1. **URL 可達性驗證**：抽查 references/ 中的 URL 是否仍可存取（建議每季抽查 10-20 個 URL）
2. **AI 即時讀取測試**：使用 `web_fetch` 讀取 2-3 個 reference URL，確認回傳內容包含預期的 API 參數表
3. **PHP SDK 版本檢查**：比對 `scripts/SDK_PHP/composer.json` 與 [ECPay 官方 PHP SDK](https://github.com/ECPay/ECPayAIO_PHP) 最新版本
4. **AI Section Index 驗證**：執行 `bash scripts/validate-ai-index.sh` 確認行號索引正確（含導航表格交叉驗證）
5. **測試向量驗證**：執行 `pip install pycryptodome && python test-vectors/verify.py` 確認全部 25 個加密測試向量通過（8 CMV + 9 AES-CBC + 4 AES-GCM + 4 URL Encode；CI 已自動執行，手動驗證可用於本地除錯）
6. **平台規則一致性驗證**：執行 `bash scripts/validate-agents-parity.sh` 確認 AGENTS.md ↔ GEMINI.md 的決策樹、關鍵規則、測試帳號區段一致（CI 已自動執行，手動驗證可用於本地除錯）
7. **版本同步驗證**：執行 `bash scripts/validate-version-sync.sh` 確認 9 個入口文件版本號一致（CI 已自動執行，手動驗證可用於本地除錯）
8. **內部連結驗證**：執行 `bash scripts/validate-internal-links.sh` 確認所有指南交叉引用無失效連結

> **URL 失效回退策略**：若 `developers.ecpay.com.tw` 單一 URL 失效（404/重新導向），先在該站搜尋替代頁面更新 reference 檔案。
> 若大量 URL 同時失效（網站改版），聯繫綠界技術支援 (techsupport@ecpay.com.tw) 取得新 URL 結構。

> **本機腳本相容性注意**：`scripts/validate-ai-index.sh` 和 CI 中的 URL 驗證腳本使用 `grep -P`（Perl 正規式），**macOS 內建的 BSD grep 不支援 `-P` flag**。本機執行請先安裝 GNU grep：`brew install grep`，並確認 PATH 中 `ggrep` 優先於系統 `grep`，或直接在 CI（ubuntu-latest）環境執行。

> **CHANGELOG 版本格式說明**：`SKILL.md` front-matter 及各平台入口檔案使用兩段式版號（`V1.0`），而 `CHANGELOG.md` 遵循 Keep a Changelog 慣例使用三段式語意版號（`[1.0.0]`）。兩者並非不一致——`validate-version-sync.sh` 只驗證 `V1.0` pattern，不驗證 CHANGELOG。新增版本時請同時在兩處更新。

### 新增 API 端點時
1. 在對應 `guides/` 中新增或補充 API 說明
2. 在 `references/` 中新增官方文件 URL 索引（確保 URL 可被 AI 即時讀取）
3. 更新 SKILL.md 決策樹（若為新服務類型）
4. 更新文件索引表

### PHP SDK 更新時
1. 比對新版 PHP 範例與現有 guide 內容
2. 更新參數差異、新增 API
3. 同步加密實作（若有變更）

### API 版本演進處理

當 ECPay 更新 API 規格時（棄用端點、新增參數、變更格式）：
1. 更新 `references/` 對應文件中的 URL 索引
2. 更新對應 `guides/` 的 SNAPSHOT 日期戳記
3. 若為新服務類型，更新 SKILL.md 決策樹與文件索引表

ECPay 官方 API 變更公告請見：[developers.ecpay.com.tw](https://developers.ecpay.com.tw)

## 版本相容性承諾

- **v1.x（當前系列）**：同系列版本保持向下相容，不移除現有 guide 結構或加密函式介面
- **棄用警告**：若 ECPay 官方廢棄某 API 端點，對應 guide 頂部將標注 `⚠️ 已棄用（官方公告：YYYY-MM）`，並維持至少一個版本的過渡說明
- **主版本升級**（如 v3.x）：提前在 `guides/00` 頂部列出破壞性變更清單，讓開發者有足夠時間準備遷移；所有破壞性變更同步記錄於 [CHANGELOG.md](./CHANGELOG.md)
- ECPay API 官方棄用公告請見 [developers.ecpay.com.tw](https://developers.ecpay.com.tw)

### 消費者版本固定

若需固定到特定版本（適合生產環境），建議使用 git tag：

```bash
# Clone 後切換至特定版本
git clone https://github.com/ECPay/ECPay-API-Skill.git ~/.codex/ecpay-skill
cd ~/.codex/ecpay-skill

# 查看所有可用版本（先確認 tag 名稱，再 checkout）
git tag -l

# 切換至指定版本（以 git tag -l 查到的實際 tag 為準）
git checkout v3.0   # 例如：固定至 V3.0

# 之後如需升級
git fetch --tags
git checkout v3.0   # 升級至新版本（以實際發布 tag 為準）
```

> 💡 目前可用 tag：`v1.0`、`v2.5`、`v2.6`、`v2.7`、`v3.0`。後續版本隨 release 陸續建立。

> 使用 `git pull origin main` 時，會自動取得最新版本（建議開發環境使用）。生產環境建議固定 tag 以避免意外的破壞性變更。

### 版本發布流程（維護者）

發布新版本時，維護者應執行以下步驟：

```bash
# 1. 更新所有版本號（SKILL.md + 8 個相依檔案）
# 2. 更新 CHANGELOG.md（在 [Unreleased] 下新增變更，並將 [Unreleased] 升版為 [X.Y.0] — YYYY-MM-DD）
# 3. commit 所有變更
git add .
git commit -m "chore: release vX.Y.0"

# 4. 建立 tag 並推送
git tag vX.Y.0
git push origin main --tags
```

> ⚠️ **Tag 必須在推送後才存在**：若文件記載 `git checkout v1.0`，請確認 `git push origin main --tags`（或 `git push origin vX.Y.0`）已執行，否則使用者會收到 `error: pathspec 'v1.0' did not match any file(s) known to git`。

---

## AI Section Index 維護規則

guides/13-checkmacvalue.md、guides/14-aes-encryption.md、guides/23-multi-language-integration.md 頂部有 AI Section Index HTML 注釋，記錄各語言/章節的精確行號。每次編輯這些檔案後必須更新。

### 何時需要更新

- 在 guides/13、14、23 中的任何位置**新增或刪除行**後
- 行號範圍變更超過 5 行時（小幅調整可容忍偏差）

### 更新方法

```bash
# 用文字編輯器搜尋目標語言的第一個 ## 標題（如 ## Python）
# 記錄實際行號，更新 AI Section Index 中對應的 line number

# 驗證方式：在 guides/14 中，用 offset/limit 參數讀取 AI Section Index 指定的行範圍
# 確認首行確實是目標語言的標題行
```

### AI Section Index 格式說明

```
<!-- AI Section Index（供 AI 部分讀取大檔案用）
Python: line X-Y | Node.js: line X-Y | ...
-->
```

- 行號從 **1** 開始計算
- 範圍包含語言標題行到下一個語言標題行的前一行
- 每次更新後在注釋第一行加入 `（精確行號，YYYY-MM-DD 校準）`

### 文件更新清單

當修改以下內容時，需同步更新以下文件：

| 修改內容 | 需更新的文件 |
|---------|------------|
| API 端點 URL | guides/01-24 相關 guide + references/ 對應 URL index |
| 加密演算法細節 | guides/13 + guides/14 + lang-standards/{語言}.md |
| 新增語言支援 | guides/13 + guides/14 + guides/23 + SKILL.md（語言數量）+ guides/00 |
| 新增 guide | SKILL.md 文件索引 + guides/00 導覽表 |
| 版本號升級 | SKILL.md + SKILL_OPENAI.md + AGENTS.md + GEMINI.md + README.md + SETUP.md + CHANGELOG.md + .github/copilot-instructions.md |

## 授權

貢獻即同意將您的修改之著作權讓與綠界科技股份有限公司。詳見 [LICENSE](LICENSE)。
