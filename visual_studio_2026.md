# Visual Studio 2026 安裝與使用指南

> **版本**：V2.5
>
> 本指南說明如何在 **Visual Studio 2026**（v18.x）中透過 GitHub Copilot Chat 使用 ECPay API Skill。
> Visual Studio 2022（v17.14+）亦適用相同步驟。

## 前置需求

| 項目 | 需求 |
|------|------|
| **Visual Studio** | 2026（v18.0+）或 2022（v17.14+）；Community / Professional / Enterprise 皆可 |
| **GitHub Copilot** | 需有 Copilot 存取權限（[Free 方案](https://learn.microsoft.com/en-us/visualstudio/ide/copilot-free-plan?view=visualstudio)即可使用 Chat，每月 50 次對話；Pro 每月 300 次 + 無限補全；Business / Enterprise 依方案而定） |
| **登入** | 在 Visual Studio 中以具備 Copilot 權限的 GitHub 帳號登入 |

> 💡 **GitHub Copilot Free 方案**：不需信用卡、不需試用期，只需 GitHub 帳號即可啟用。每月 2,000 次程式碼補全 + 50 次 Chat 對話。對於初次評估 ECPay 串接已相當充足。

## 安裝步驟

### 步驟 1：取得 ECPay Skill 檔案

**方式 A：Git Clone（推薦）**

在 Visual Studio 的 Terminal 或 Developer Command Prompt 中執行：

```bash
# Clone 到專案目錄下（僅當前專案使用）
git clone https://github.com/ECPay/ECPay-API-Skill.git .ecpay-skill
```

**方式 B：下載 ZIP（不需 Git）**

1. 前往 https://github.com/ECPay/ECPay-API-Skill
2. 點選綠色 **Code** 按鈕 → **Download ZIP**
3. 解壓縮到你的專案根目錄，資料夾命名為 `.ecpay-skill`

### 步驟 2：設定 Custom Instructions

ECPay Skill 透過 GitHub Copilot 的 **Custom Instructions** 機制載入。你有兩種方式：

#### 方式 A：使用 `.github/copilot-instructions.md`（推薦，團隊共用）

1. 在你的**專案根目錄**建立 `.github` 資料夾（如不存在）
2. 建立檔案 `.github/copilot-instructions.md`
3. 貼入以下內容：

```markdown
# ECPay API Skill — Copilot 自訂指令

本專案使用綠界科技 ECPay API Skill 作為 AI 串接知識庫。

## 知識庫位置

ECPay Skill 安裝於 `.ecpay-skill/` 目錄：
- 入口文件：`.ecpay-skill/SKILL.md`（決策樹、快速參考、文件索引）
- 整合指南：`.ecpay-skill/guides/`（28 份深度指南）
- API 規格索引：`.ecpay-skill/references/`（431 個官方 API 文件 URL）
- PHP 範例：`.ecpay-skill/scripts/SDK_PHP/example/`（134 個驗證範例）

## 使用方式

當收到 ECPay / 綠界相關問題時：
1. 先讀取 `.ecpay-skill/SKILL.md` 了解決策樹和文件索引
2. 依決策樹路由到對應 guide（如 guides/01 AIO 金流、guides/02 站內付 2.0）
3. 生成程式碼前，參考 `.ecpay-skill/references/` 中的 URL 確認最新 API 規格
4. 加密實作參考 guides/13（CheckMacValue）和 guides/14（AES）

## 測試帳號

| 服務 | MerchantID | HashKey | HashIV |
|------|-----------|---------|--------|
| 金流 | 3002607 | pwFHCqoQZGmho4w6 | EkRm7iFT261dpevs |
| 發票 | 2000132 | ejCk326UnaZWKisg | q9jcZX8Ib9LM8wYk |
| 物流 | 2000132 | 5294y06JbISpM5x9 | v77hoKGq4kWxNNIS |
```

4. 在 Visual Studio 中啟用 Custom Instructions：
   - **Visual Studio 2026**：**Tools** → **Options** → 展開 **All Settings** → **GitHub** → **Copilot** → **Copilot Chat** → 勾選 **Enable custom instructions to be loaded from .github/copilot-instructions.md files and added to requests**
   - **Visual Studio 2022**：**Tools** → **Options** → **GitHub** → **Copilot** → **Copilot Chat** 區段 → 勾選同名選項

#### 方式 B：使用 File-Specific Instructions（針對特定檔案類型）

如果你只想在特定檔案類型（如 `.cs`）中啟用 ECPay 知識，可建立針對性的指令檔：

1. 建立目錄 `.github/instructions/`（如不存在）
2. 建立檔案 `.github/instructions/ecpay.instructions.md`：

```markdown
---
applyTo: "**/*.cs,**/*.py,**/*.js,**/*.ts,**/*.java,**/*.go,**/*.php"
description: ECPay API 串接知識庫 — 綠界金流/發票/物流整合
---

本專案使用綠界科技 ECPay API Skill，安裝於 `.ecpay-skill/`。
遇到 ECPay 相關需求時，請先讀取 `.ecpay-skill/SKILL.md` 作為入口。
```

3. 啟用方式同上（Tools → Options → 勾選 Enable custom instructions）

### 步驟 3：驗證安裝

開啟 Copilot Chat（點選 IDE 右上角的 **Copilot badge** → **Open Chat Window**，或按 **Ctrl+\\, C**），輸入：

```
綠界 AIO 金流的測試 MerchantID 是多少？
```

若回應包含 `3002607`，表示 ECPay Skill 已成功載入。

## 使用 Agent Mode（推薦）

Visual Studio 2026 / 2022 v17.14+ 支援 **Agent Mode**，讓 Copilot 能自主完成多步驟任務，是串接 ECPay 的最佳方式。

### 啟用 Agent Mode

1. 開啟 Copilot Chat 視窗
2. 點選左上角的 **Ask** 下拉選單，切換為 **Agent**
3. 確認 Agent Mode 已啟用：
   - **Visual Studio 2026**：**Tools** → **Options** → **All Settings** → **GitHub** → **Copilot** → **Copilot Chat** → 勾選 **Enable Agent mode in the chat pane**
   - **Visual Studio 2022**：**Tools** → **Options** → **GitHub** → **Copilot** → **Copilot Chat** → 勾選同名選項

### 使用範例

在 Agent Mode 下，直接用自然語言描述需求，Copilot 會自動建立計畫、編輯程式碼、執行終端機指令：

```
請幫我用 C# 串接 ECPay AIO 信用卡一次付清，
測試環境，MerchantID=3002607，
需要完整的 CheckMacValue 計算和 ReturnURL 接收處理。
參考 .ecpay-skill/SKILL.md 和 .ecpay-skill/guides/01-payment-aio.md。
```

```
幫我用 Python 串接 ECPay B2C 電子發票即時開立，
測試帳號 MerchantID=2000132，需要 AES 加解密和雙層錯誤檢查。
參考 .ecpay-skill/guides/04-invoice-b2c.md。
```

```
我在串接站內付 2.0 時遇到 TransCode ≠ 1 的錯誤，
請根據 .ecpay-skill/guides/15-troubleshooting.md 幫我診斷原因。
```

> 💡 **提示**：在提問中加上 `參考 .ecpay-skill/...` 可確保 Copilot 讀取正確的知識庫檔案。使用 `#file` 引用也能達到相同效果。

### Agent Mode 可執行的動作

| 動作 | 說明 |
|------|------|
| 讀取 Skill 檔案 | 自動讀取 `.ecpay-skill/` 中的指南和範例 |
| 產生程式碼 | 直接在編輯器中產出並套用程式碼 |
| 執行終端指令 | 如 `dotnet build`、`npm install` 等（需確認後執行） |
| 偵測錯誤並修正 | 偵測 build 錯誤或測試失敗後自動修正 |
| 多步驟規劃 | 將複雜任務分解為可追蹤的步驟 |

## 使用 Prompt Files（可重用提示）

你可以將常用的 ECPay 串接提示儲存為 Prompt File，方便重複使用。

### 建立 Prompt File

1. 建立目錄 `.github/prompts/`（如不存在）
2. 建立檔案，例如 `.github/prompts/ecpay-aio-payment.prompt.md`：

```markdown
請根據 #file:.ecpay-skill/SKILL.md 和 #file:.ecpay-skill/guides/01-payment-aio.md，
用 {{language}} 語言串接 ECPay AIO 全方位金流信用卡付款。

需求：
- 測試環境（MerchantID=3002607）
- 完整的 CheckMacValue SHA256 計算（參考 #file:.ecpay-skill/guides/13-checkmacvalue.md）
- ReturnURL callback 接收處理（需回應 1|OK）
- SimulatePaid=1 模擬付款

請同時提供注意事項和常見錯誤提醒。
```

### 使用 Prompt File

在 Copilot Chat 中輸入 `#prompt:ecpay-aio-payment` 或 `/ecpay-aio-payment`，即可載入已儲存的提示。

## Ask Mode vs Agent Mode 使用建議

| 情境 | 建議模式 |
|------|---------|
| 快速查詢 ECPay 參數或錯誤碼 | **Ask** Mode |
| 瞭解 AIO 和站內付 2.0 的差異 | **Ask** Mode |
| 產出完整的串接程式碼 | **Agent** Mode |
| 從零開始建立 ECPay 串接專案 | **Agent** Mode |
| 除錯 CheckMacValue 或 AES 問題 | **Agent** Mode（可讀取程式碼並修正） |
| 使用 MCP 工具 | **Agent** Mode（必須） |

## 常見問題

**Q：Copilot Chat 沒有讀到 ECPay Skill 的內容？**

1. 確認 `.github/copilot-instructions.md` 存在於專案根目錄
2. 確認已在 **Tools** → **Options** → **GitHub** → **Copilot** → **Copilot Chat** 勾選 **Enable custom instructions**
3. 確認 `.ecpay-skill/` 目錄位於專案根目錄下
4. 在提問中明確引用檔案：`請參考 #file:.ecpay-skill/SKILL.md`
5. 重新啟動 Visual Studio

**Q：Agent Mode 選項沒有出現？**

1. 確認 Visual Studio 版本為 2026 (v18.0+) 或 2022 (v17.14+)：**Help** → **About Visual Studio**
2. 確認已在 **Tools** → **Options** → **GitHub** → **Copilot** → **Copilot Chat** 勾選 **Enable Agent mode in the chat pane**
3. 確認 GitHub 帳號已登入且具備 Copilot 權限
4. 重新啟動 Visual Studio

**Q：Free 方案的 50 次 Chat 夠用嗎？**

對於初次串接評估和基礎開發通常足夠。如果需要大量 AI 輔助開發，建議升級至 Copilot Pro（每月 USD $10）或 Business 方案。

**Q：跟 VS Code Copilot Chat 有什麼差異？**

| 面向 | Visual Studio 2026 | VS Code |
|------|:---:|:---:|
| Custom Instructions | `.github/copilot-instructions.md` | 相同 |
| Agent Mode | 支援（v18.0+ / v17.14+） | 支援 |
| File-Specific Instructions | `.instructions.md` + `applyTo` glob | 相同 |
| Prompt Files | `.prompt.md` 支援 | 相同 |
| 右鍵 Copilot Actions | 支援（Explain/Fix/Generate/Tests/Optimize） | 支援 |
| 自動生成 Instructions | `/generateInstructions` | `/create-instruction`（等效功能，不同命令名） |
| 儲存對話為 Prompt File | `/savePrompt` | `/create-prompt`（等效功能，不同命令名） |
| 內建 Agent | @debug, @profiler, @test, @vs | 不同的內建 Agent |
| NuGet MCP Server | 內建 | 不適用 |
| 主要語言生態 | C# / .NET / C++ / VB | 多語言 |

## 相關資源

- [Visual Studio 2026 Copilot Chat 官方文件](https://learn.microsoft.com/en-us/visualstudio/ide/copilot-chat-context?view=visualstudio)
- [Visual Studio Agent Mode 官方文件](https://learn.microsoft.com/en-us/visualstudio/ide/copilot-agent-mode?view=visualstudio)
- [GitHub Copilot Custom Instructions](https://docs.github.com/copilot/customizing-copilot/adding-custom-instructions-for-github-copilot)
- [GitHub Copilot Free 方案說明](https://learn.microsoft.com/en-us/visualstudio/ide/copilot-free-plan?view=visualstudio)
- [Visual Studio 2026 Release Notes](https://learn.microsoft.com/en-us/visualstudio/releases/2026/release-notes)
