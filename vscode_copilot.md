# 用 VS Code Copilot Chat 使用 ECPay 綠界整合助手

> **版本**：V2.5
>
> **這份文件是給非技術人員的**——不需要會寫程式、不需要用終端機。
> 只要有 VS Code 和 GitHub Copilot 擴充套件，就能讓 AI 幫你回答綠界 API 串接的所有問題。

---

## 你能用它做什麼？

在 VS Code 裡用中文問 AI 任何關於綠界的問題，例如：

- 「我要信用卡收款，最簡單的方式是什麼？」
- 「測試環境的帳號密碼是什麼？」
- 「CheckMacValue 驗證失敗怎麼辦？」
- 「幫我用 Python 寫一個信用卡付款的範例程式」
- 「我要收款完自動開發票再出貨，怎麼做？」
- 「測試好了，要怎麼切正式環境？」

AI 會根據綠界官方文件，給你具體的步驟和程式碼。

---

## 事前準備（約 5 分鐘）

### 1. 安裝 VS Code

如果還沒有 VS Code，請先下載安裝：

1. 用瀏覽器打開 **[code.visualstudio.com](https://code.visualstudio.com)**
2. 點 **Download** 下載對應你作業系統的版本（Windows / macOS / Linux）
3. 安裝完成後開啟 VS Code

### 2. 安裝 GitHub Copilot 擴充套件

1. 開啟 VS Code
2. 點左側的 **擴充套件** 圖示（方塊圖案，或按 `Ctrl+Shift+X`）
3. 在搜尋欄輸入 **GitHub Copilot**
4. 搜尋 **GitHub Copilot**，點 **Install** 安裝（較新版本的 VS Code 已將 Copilot Chat 整合為單一擴充套件，安裝一個即可）
5. 安裝後，VS Code 會提示你登入 GitHub 帳號——按提示完成登入即可

> 💡 **免費方案可用**：GitHub Copilot 提供 **Free 方案**（每月 2,000 次程式碼補全 + 50 次 Chat 對話），不需信用卡，足夠測試和諮詢使用。
> 前往 [github.com/features/copilot](https://github.com/features/copilot) 註冊。
> 若需要更多用量，可升級 Pro（月費 10 美元）或請 IT 部門開通公司的 Business / Enterprise 方案。

### 3. 下載 ECPay Skill 檔案

1. 用瀏覽器打開 **[github.com/ECPay/ECPay-API-Skill](https://github.com/ECPay/ECPay-API-Skill)**
2. 點綠色的 **< > Code** 按鈕
3. 選 **Download ZIP**
4. 解壓縮到桌面（會產生一個 `ECPay-API-Skill-master` 資料夾）

> 💡 如果你在公司，可以請工程師直接把這個資料夾分享給你。

---

## 設定步驟（約 2 分鐘，只需做一次）

### 步驟 1：用 VS Code 開啟 ECPay Skill 資料夾

1. 開啟 VS Code
2. 選單列 → **File** → **Open Folder...**（或按 `Ctrl+K Ctrl+O`）
3. 選擇剛才解壓縮的 `ECPay-API-Skill-master` 資料夾
4. 點 **Select Folder**（選取資料夾）

> 💡 **自動載入**：本資料夾內有 `.github/copilot-instructions.md` 檔案，VS Code Copilot Chat **會自動讀取**這個檔案中的指令——你不需要做任何額外設定，開啟資料夾就完成了！

### 步驟 2：開啟 Copilot Chat 對話面板

有三種方式開啟 Copilot Chat：

- **方式一**：點擊 VS Code 標題列的 **Chat** 選單，或底部狀態列的 **Copilot 圖示**
- **方式二**：按快捷鍵 `Ctrl+Alt+I`（Windows/Linux）或 `Ctrl+Cmd+I`（macOS）
- **方式三**：按 `Ctrl+Shift+P` 開啟命令面板，輸入 **Copilot Chat** 後按 Enter

### 步驟 3：開始提問！

在 Copilot Chat 對話框中直接用中文輸入你的問題，AI 就會根據 ECPay Skill 知識回答。

> 💡 **驗證安裝成功**：輸入「綠界 AIO 金流的測試 MerchantID 是多少？」，若回應為 `3002607` 表示 Skill 已正確載入。

---

## 三種對話模式

VS Code Copilot Chat 提供三種模式，適合不同情境：

| 模式 | 說明 | 適合場景 |
|------|------|---------|
| **Ask**（詢問） | 只回答問題，不修改程式碼 | 了解串接流程、查詢 API 規格、排查問題 |
| **Edit**（編輯） | 直接修改你選取的程式碼 | 修改現有程式碼、加入 ECPay 串接邏輯 |
| **Agent**（代理） | 自主完成複雜任務，可建立/修改多個檔案 | 從零開始建立串接程式碼、完整專案建置 |

> 💡 **非技術人員建議**：使用 **Ask** 模式就足夠了——用來了解串接流程、查詢測試帳號、取得程式碼範例交給工程師。

---

## 實用技巧

### 直接問自然語言（最推薦）

不需要記任何特殊語法，直接用中文描述需求即可：

```
我想用 Node.js 串接綠界信用卡付款，前後端分離架構，請給我完整範例。
```

### 進階：引用特定檔案（選用）

> 💡 以下是給工程師的進階技巧，非技術人員可以跳過。

在對話中輸入 `#file` 後選擇特定檔案，讓 AI 參考該檔案內容回答：

```
#file:guides/01-payment-aio.md 信用卡一次付清需要哪些參數？
```

### 進階：工作區自動搜尋

Copilot Chat 在 Ask 和 Agent 模式下會**自動搜尋**你的工作區內容（包含 ECPay Skill 資料夾），不需要額外操作。如果需要明確觸發工作區搜尋，可在提問中加上 `#codebase`：

```
#codebase ATM 虛擬帳號的 Callback 格式是什麼？
```

---

## 在其他專案中使用 ECPay Skill

如果你想在**自己的專案**（而不是 ECPay Skill 資料夾）中使用綠界知識，有兩種方式：

### 方法一：同時開啟兩個資料夾（推薦，不影響現有設定）

1. 在 VS Code 中開啟你的專案
2. 選單列 → **File** → **Add Folder to Workspace...** → 選擇 ECPay Skill 資料夾
3. 這樣你的工作區同時包含兩個資料夾，Copilot 可以存取所有內容
4. 你原有的 `.github/copilot-instructions.md` 完全不受影響

> 💡 **最大優點**：不需要修改任何檔案，你的專案和 ECPay Skill 各自獨立。

### 方法二：將指令合併到現有專案

> ⚠️ **注意**：如果你的專案已有 `.github/copilot-instructions.md`，**不可直接覆蓋**，否則會丟失原有的 Copilot 指令。

1. 從 ECPay Skill 資料夾中，找到 `.github/copilot-instructions.md`，用文字編輯器開啟
2. 打開你自己專案的 `.github/copilot-instructions.md`（如果沒有就直接複製整個檔案）
3. 將 ECPay Skill 的指令內容**附加**到你的檔案末尾（不要取代原有內容）
4. 儲存後，Copilot Chat 就會同時載入你原有的指令和 ECPay 知識

如果你的專案**沒有** `.github/copilot-instructions.md`，則可以直接複製過去。

---

## 使用範例

### 情境 1：我想了解 ECPay 能做什麼

```
請幫我整理綠界提供的所有服務，以及各服務適合什麼情境。
```

### 情境 2：我要讓網站能收信用卡

```
我們公司網站要加信用卡收款功能，
網站是用 PHP 寫的，
請告訴我最簡單的串接方式和步驟。
```

### 情境 3：工程師說串接一直失敗

```
工程師說 CheckMacValue 驗證一直失敗，
錯誤碼是 10400002，
可能是什麼問題？怎麼解決？
```

### 情境 4：我要收款 + 開發票 + 出貨

```
我們是電商，希望：
1. 消費者下單後用信用卡付款
2. 付款成功自動開電子發票
3. 同時建立超商取貨的物流單
請告訴我整體的串接流程和需要哪些 API。
```

### 情境 5：測試好了要上線

```
我們的串接在測試環境都正常了，
要切換到正式環境需要注意什麼？
有沒有上線前的檢查清單？
```

### 情境 6：給工程師生成程式碼

```
請幫我用 Python + Flask 寫一個完整的 AIO 信用卡收款範例，
包含建立訂單和接收付款結果的 Callback。
```

---

## 常見問題

### Q：跟其他 CLI 工具有什麼不同？

本 Skill 支援多種平台（詳見 [README.md](./README.md#安裝)）。VS Code Copilot Chat 與其他 CLI 工具的主要差異：

| | VS Code Copilot Chat | CLI 工具（Claude Code、Codex 等） |
|---|---|---|
| 使用方式 | VS Code 內的對話面板 | 終端機命令列 |
| 適合對象 | 所有人（含非技術人員） | 工程師 |
| 操作門檻 | 低（圖形介面，滑鼠操作） | 高（需熟悉終端機） |
| 功能 | 問答 + 編輯程式碼 + 自主建置 | 問答 + 執行命令 + 自主建置 |

### Q：需要付費嗎？

GitHub Copilot 提供 **Free 方案**（每月 50 次 Chat），足夠測試使用。需要更多用量可升級 Pro（月費 10 美元）。若你的公司已有 Business 或 Enterprise 方案，請聯繫 IT 部門取得授權。

### Q：為什麼開啟資料夾就能自動載入？

VS Code Copilot Chat 會自動讀取專案根目錄 `.github/copilot-instructions.md` 的內容作為上下文。ECPay Skill 已經準備好了這個檔案，所以開啟資料夾就等於「安裝」完成。

### Q：AI 回答的內容可靠嗎？

AI 的回答基於綠界官方文件，準確度高。但涉及具體金額上限、手續費率、合約細節等商務問題，請聯繫綠界業務確認。

API 技術問題請洽：sysanalydep.sa@ecpay.com.tw

### Q：我的資料會被看到嗎？

你開啟的是綠界的公開技術文件（已公開在 GitHub 上），不含任何機密資訊。在對話中不要輸入真實的正式環境 HashKey / HashIV 或客戶個資即可。

### Q：Google AI Studio 和 VS Code Copilot Chat 哪個好？

| | VS Code Copilot Chat | Google AI Studio |
|---|---|---|
| 費用 | Free 方案可用（每月 50 次 Chat）；Pro $10/月無限制 | 免費 |
| 優點 | 可直接編輯程式碼、三種模式（Ask/Edit/Agent） | 免安裝、免費、純瀏覽器操作 |
| 設定 | 開啟資料夾即自動載入 | 每次新對話需載入範本 |
| 建議 | 工程師日常開發、需要編輯程式碼 | 非技術人員快速諮詢 |

---

## 需要更多幫助？

- **技術問題**：在 Copilot Chat 中直接問 AI
- **帳號申請 / 合約問題**：聯繫綠界業務
- **API 技術支援**：sysanalydep.sa@ecpay.com.tw
- **Skill 使用問題 / 回報錯誤**：[GitHub Issues](https://github.com/ECPay/ECPay-API-Skill/issues)
