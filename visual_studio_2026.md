# Visual Studio 2026 安裝與使用指南

> **版本**:V3.4
>
> 本指南說明如何在 **Visual Studio 2026**（v18.x）中透過 GitHub Copilot Chat 使用 ECPay API Skill。
> Visual Studio 2022（v17.14+）亦適用相同步驟。

## 前置需求

| 項目 | 需求 |
|------|------|
| **Visual Studio** | 2026（v18.0+）或 2022（v17.14+）；Community / Professional / Enterprise 皆可 |
| **GitHub Copilot** | 需有 Copilot 存取權限（[Free 方案](https://learn.microsoft.com/en-us/visualstudio/ide/copilot-free-plan?view=visualstudio)即可使用 Chat，每月 50 次對話；Pro（USD $10/月）為無限補全 + 無限標準 Chat，另含每月進階模型額度；Business / Enterprise 依方案而定。各方案最新額度以 [GitHub 官方說明](https://docs.github.com/en/copilot/concepts/billing/individual-plans) 為準） |
| **登入** | 在 Visual Studio 中以具備 Copilot 權限的 GitHub 帳號登入 |

> 💡 **GitHub Copilot Free 方案**：不需信用卡、不需試用期，只需 GitHub 帳號即可啟用。每月 2,000 次程式碼補全 + 50 次 Chat 對話（自 2025 Q4 起另含每月 50 次進階模型請求）。對於初次評估 ECPay 串接已相當充足。

## 安裝步驟

### 步驟 1：取得 ECPay API Skill 檔案

在 Visual Studio 的 Terminal 或 Developer Command Prompt 中執行：

```powershell
# Clone 到專案目錄下（僅當前專案使用）
git clone https://github.com/ECPay/ECPay-API-Skill.git .ecpay-skill
```

### 步驟 2：設定 Custom Instructions

ECPay API Skill 透過 GitHub Copilot 的 **Custom Instructions**（`.github/copilot-instructions.md`）機制載入。此設定檔可隨專案一起版控、供團隊共用,設定步驟如下：

1. 在你的**專案根目錄**建立 `.github` 資料夾（如不存在）
2. 建立檔案 `.github/copilot-instructions.md`
3. 貼入以下內容：

> 💡 以下內容為「**使用導向**」設定,與本 repo 自身的 `.github/copilot-instructions.md`（維護導向）刻意不同,請勿互相複製混用。

```markdown
# ECPay API Skill — Copilot 自訂指令

本專案使用綠界科技 ECPay API Skill 作為 AI 串接知識庫。

## 知識庫位置

ECPay API Skill 安裝於 `.ecpay-skill/` 目錄：
- 入口文件：`.ecpay-skill/SKILL.md`（決策樹、快速參考、文件索引）
- 整合指南：`.ecpay-skill/guides/`（29 份深度指南）
- API 規格索引：`.ecpay-skill/references/`（443 個官方 API 文件 URL）
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

### 步驟 3：驗證安裝

開啟 Copilot Chat（點選 IDE 右上角的 **Copilot badge** → **Open Chat Window**，或按 **Ctrl+\\, C**），輸入：

```
綠界 AIO 金流的測試 MerchantID 是多少？
```

若回應包含 `3002607`，表示 ECPay API Skill 已成功載入。

## 使用 Agent Mode（推薦）

Visual Studio 2026 / 2022 v17.14+ 支援 **Agent Mode**，讓 Copilot 能自主完成多步驟任務，是串接 ECPay 的最佳方式。

### 啟用 Agent Mode

1. 開啟 Copilot Chat 視窗
2. 在 Chat 視窗點選 **Ask** 模式下拉選單，切換為 **Agent**
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

**Q：步驟 3 驗證時,Copilot 回應的 MerchantID 是 `2000132`,而不是 `3002607`？**

這是最常見的情況,代表 **Copilot 沒讀到你建立的 `.github/copilot-instructions.md`**,只能憑既有印象,回它預設知道的綠界通用範例帳號 `2000132`。

關鍵觀念:`2000132` 不是壞帳號,而是綠界**電子發票 / 物流**的測試帳號(也是網路上最常被引用的通用範例);**AIO 金流的正確測試 MerchantID 是 `3002607`**(見步驟 2 的測試帳號表格)。換言之,問題不在 Skill 內容,而在指令檔沒被載入。

> ⚠️ **最容易搞混的地方:系統裡會有「兩個同名」的 `copilot-instructions.md`,用途完全不同,請勿放錯或互相當成對方使用。**
>
> | 檔案位置 | 用途 | 內含測試帳號？ | 該不該用 |
> |----------|------|:---:|:---:|
> | **你專案根目錄**的<br>`.github/copilot-instructions.md` | 告訴「自己專案」的 Copilot 如何使用 ECPay 知識庫,**表格內直接寫了金流 = `3002607`** | ✅ 有 | ✅ **這份才是你要依步驟 2「自己建立」的** |
> | clone 下來的<br>`.ecpay-skill/.github/copilot-instructions.md` | 綠界**維護 Skill 這個 repo 本身**的內部開發指令(版本同步、驗證腳本…) | ❌ 沒有 | ❌ **請勿當成專案指令,也不需去動它** |
>
> Copilot **只會讀取「目前開啟方案(solution)最上層資料夾」**底下的 `.github/copilot-instructions.md`,**不會**自動去讀 `.ecpay-skill/.github/` 子目錄那一份;就算手動指定,那一份也沒有任何測試帳號。**正確做法是依步驟 2 在你自己專案根目錄「新建一份」**,再貼入指南提供的內容(含測試帳號表格)。

確認方式與下一題的排查清單相同。

**Q：Copilot Chat 沒有讀到 ECPay API Skill 的內容？**

1. 確認 `.github/copilot-instructions.md` 存在於**專案根目錄**(即目前 Visual Studio 開啟的方案最上層資料夾),**而不是** `.ecpay-skill/.github/` 子目錄那一份
2. 確認該檔內容是步驟 2 提供的版本(含測試帳號表格,金流 = `3002607`)
3. 確認已在 **Tools** → **Options** → **GitHub** → **Copilot** → **Copilot Chat** 勾選 **Enable custom instructions**(此選項預設可能未勾;未勾則 Visual Studio 完全不會載入該檔)
4. 確認 `.ecpay-skill/` 目錄位於專案根目錄下,且資料夾名稱(含開頭的點「.」)與 `copilot-instructions.md` 內所寫的路徑一致
5. 在提問中明確引用檔案來驗證:`綠界 AIO 金流的測試 MerchantID 是多少？請參考 #file:.ecpay-skill/SKILL.md`(若加 `#file` 會回 `3002607`、不加卻回 `2000132`,即可確認問題出在「自動載入」未設定好,知識庫本身正常)
6. 重新啟動 Visual Studio

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
| 自動生成 Instructions | `/generateInstructions` | `/create-instructions`（或 `/init`；等效功能，不同命令名） |
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
