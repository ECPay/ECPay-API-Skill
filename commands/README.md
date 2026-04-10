# Claude Code 快速指令（Slash Commands）

> **一句話**：這個資料夾有 6 個 `.md` 檔案，是為 **Claude Code** 使用者準備的快速指令。安裝後可以用 `/ecpay-pay`、`/ecpay-invoice` 等短指令，取代每次都要打一長段自然語言描述需求。**不裝也能用 Skill**——只是每次要多打幾個字而已。

---

## 🤔 這是什麼？30 秒看懂

Claude Code 支援「Slash Commands」：你在對話框打 `/` 開頭的短指令（例如 `/review-pr`），它會把這個短指令**展開成一段預先寫好的完整提示詞**餵給 AI。

這個資料夾的 6 個檔案就是這種預先寫好的提示詞，每個對應一個 ECPay 整合情境：

| 指令 | 用途 | 展開後 AI 會做什麼 |
|---|---|---|
| **`/ecpay-pay`** | 串接金流 | 讀 SKILL.md 決策樹選方案(AIO / 站內付 2.0 / 幕後授權)→ 讀對應 guide → 加密實作參考 guides/13 或 14 → 從 references/ web_fetch 最新規格 |
| **`/ecpay-invoice`** | 串接電子發票 | 讀 guides/04(B2C)/ guides/05(B2B)/ guides/19(離線) → 確認 InvoiceNo vs InvoiceNumber 陷阱 → web_fetch references/Invoice/ |
| **`/ecpay-logistics`** | 串接物流 | 選擇國內 / 全方位 / 跨境 → 讀 guides/06 / 07 / 08 → CMV-MD5 vs AES-JSON 判斷 → web_fetch references/Logistics/ |
| **`/ecpay-ecticket`** | 串接電子票證 | 選擇價金保管 / 純發行 → 讀 guides/09 → 使用 E-Ticket CMV 特殊公式(與 AIO CMV 不相容) |
| **`/ecpay-debug`** | 除錯排查 | 按症狀導航到 guides/15(troubleshooting)→ 驗證 CheckMacValue / AES 加密流程 → 提供具體根因 |
| **`/ecpay-go-live`** | 上線前檢查 | 逐項走 guides/16 上線檢查清單 → 確認 MerchantID / HashKey / HashIV / API domain 切換 |

## 👥 這跟我有關嗎？

| 你的角色 | 需要知道的事 |
|---|---|
| **業務 / PM / 主管** | ❌ 不用動手。只要知道「這是 Claude Code 使用者的便利工具,選配,不影響 Skill 本身的功能」 |
| **FAE / 客戶支援** | 🟡 略懂即可。客戶問「怎麼用 `/ecpay-pay`」時,可以回:「那是 Claude Code 的快速指令,把 `commands/` 內的 `.md` 複製到 `.claude/commands/` 就能用」 |
| **使用 Claude Code 的客戶工程師** | ✅ **可以安裝**。請見下方「如何安裝」章節 |
| **使用其他平台的客戶工程師**(Cursor / Windsurf / VS Code Copilot / ChatGPT GPTs) | ❌ **不需要**。你的平台沒有 slash command 機制,直接用自然語言對 AI 說「我要串綠界金流」就會觸發 Skill |
| **Skill 維護者** | ✅ 新增新服務時,若複雜度足夠可考慮加對應 `/ecpay-*` 指令 |

## ⚠️ 常見誤解

### 誤解 1:「不裝這個就不能用 Skill」
**正解**:❌ 不對。Skill 的核心是 `SKILL.md` + `guides/` + `references/`。這個資料夾的指令是**選配的捷徑**,目的是讓常用情境不用每次打一長段需求描述。不裝也完全能用 Skill,只是每次要多打幾個字。

### 誤解 2:「這是可執行的程式」
**正解**:❌ 不是。每個 `.md` 檔都是**預先寫好的提示詞模板**,Claude Code 在使用者輸入 `/ecpay-pay` 時讀取該檔案內容,展開成完整提示詞餵給 AI。檔案本身不會執行任何程式碼,只是被讀取為文字。

### 誤解 3:「這是給 Claude 以外的平台用的」
**正解**:❌ 只有 Claude Code 原生支援 slash commands。Cursor、Windsurf、VS Code Copilot、ChatGPT GPTs、Gemini CLI、Codex CLI 等平台**不支援這個機制**,他們需要用自然語言對話觸發 Skill(例如「我要用 Node.js 串綠界 AIO」),效果相同。

## 💻 如何安裝(只適用於 Claude Code)

有兩種安裝位置:

### 方式 1:專案層級(僅當前專案,團隊共用)

```bash
# 假設你已 clone ECPay API Skill 到 .ecpay-skill/
cp .ecpay-skill/commands/*.md .claude/commands/
```

### 方式 2:個人全域(所有專案共用)

```bash
cp .ecpay-skill/commands/*.md ~/.claude/commands/
```

### 驗證安裝

開啟 Claude Code,在對話框輸入 `/`,應該會看到 `ecpay-pay` / `ecpay-invoice` 等 6 個指令出現在 autocomplete 清單中。輸入 `/ecpay-pay` 按 Enter,AI 應該會詢問你的語言/框架/付款方式。

## 📐 指令檔的結構(維護者用)

每個 `ecpay-*.md` 檔案由三部分組成:

```markdown
---
description: 指令的一行說明(顯示在 Claude Code autocomplete)
---

> **你需要這個指令嗎?**(路由建議,幫使用者判斷是否選對指令)
> - 目標: xxx → ✅ 是
> - 目標: yyy → ❌ 改用 /ecpay-debug

使用者需要 xxx。請依以下步驟引導:
1. 讀取 SKILL.md 決策樹...
2. 詢問使用者語言/框架...
3. 讀對應 guide...
4. ...

---

## 完成後下一步
- 加入 xxx → /ecpay-yyy
- 遇到問題 → /ecpay-debug
```

**關鍵設計原則**:
- **短(≤ 40 行)**:這是路由器,不是教學內容。詳細資訊交給 `guides/` 處理
- **不重複 SKILL.md 邏輯**:指令只是「快速觸發」,不應該複製 SKILL.md 的決策樹內容
- **明確導航**:告訴 AI「讀哪個 guide」、「web_fetch 哪個 reference」,不自己展開知識
- **交叉引用其他指令**:在「完成後下一步」指出相關指令,讓使用者知道整個工作流

## 🛠️ 維護者:如何新增指令

新增新的 `/ecpay-xxx` 指令時:

1. 在 `commands/` 新增 `ecpay-xxx.md`
2. 依上方「指令檔結構」範本寫
3. **保持 ≤ 40 行**——超過就該考慮是否應該寫成 guide 而不是指令
4. 更新 `README.md` 主文件 §使用 段落的指令表格
5. 更新 `SKILL.md` §快速指令路由表
6. 更新本檔(`commands/README.md`)的指令清單表格
7. Commit message 建議格式:`feat(commands): add /ecpay-xxx for <scenario>`

## 📚 延伸閱讀

- [`SKILL.md`](../SKILL.md) §快速指令路由表 — AI 內部的指令對應邏輯
- [`README.md`](../README.md) §使用 — 給使用者的指令總覽與安裝指引
- [Claude Code 官方 Slash Commands 文件](https://code.claude.com/docs/en/skills) — Claude Code 的 slash commands / skills 官方規範
