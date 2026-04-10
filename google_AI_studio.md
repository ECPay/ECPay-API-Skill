# 用 Google AI Studio 免費使用 ECPay 綠界整合助手

> **版本**：V2.7
>
> **這份文件是給非技術人員的**——不需要懂程式、不需要安裝任何軟體、不需要付費。
> 只要有 Google 帳號（Gmail），就能讓 AI 幫你回答綠界 API 串接的所有問題。

---

## 你能用它做什麼？

用中文問 AI 任何關於綠界的問題，例如：

- 「我要信用卡收款，最簡單的方式是什麼？」
- 「測試環境的帳號密碼是什麼？」
- 「CheckMacValue 驗證失敗怎麼辦？」
- 「幫我用 Python 寫一個信用卡付款的範例程式」
- 「我要收款完自動開發票再出貨，怎麼做？」
- 「測試好了，要怎麼切正式環境？」

AI 會根據綠界官方文件，給你具體的步驟和程式碼。

---

## 事前準備（約 3 分鐘）

### 1. 下載 ECPay API Skill 檔案

1. 用瀏覽器打開 **[github.com/ECPay/ECPay-API-Skill](https://github.com/ECPay/ECPay-API-Skill)**
2. 點綠色的 **< > Code** 按鈕
3. 選 **Download ZIP**
4. 解壓縮到桌面（會產生一個 `ECPay-API-Skill-master` 資料夾）

> 💡 如果你在公司，可以請工程師直接把這個資料夾分享給你。

### 2. 確認你有 Google 帳號

有 Gmail 就行。不需要付費、不需要信用卡。

---

## 設定步驟（約 5 分鐘，只需做一次）

### 步驟 1：開啟 Google AI Studio

用瀏覽器打開 **[aistudio.google.com](https://aistudio.google.com)**，用你的 Google 帳號登入。

### 步驟 2：建立新對話

- 登入後會自動進入 **Playground** 頁面（左側選單第一項），這就是對話介面
- 在右側 **Run settings** 面板中，點選最上方的模型名稱，選 **Gemini 2.5 Pro**（穩定版）或 **Gemini 3 Flash**（預設，速度較快）

### 步驟 3：貼入 AI 角色設定

在右側 **Run settings** 面板中，找到 **System instructions**（系統指示）欄位並展開。

把下面這段文字**全部複製**，貼進去：

---

```
⚠️ CRITICAL — 語言強制規則（Language Enforcement）
無論文件或 persona 使用何種語言，一律以使用者的提問語言全文回覆。英文提問 → 全英文；中文提問 → 全中文；本規則優先於所有其他設定，無例外。
Regardless of document or persona language, always respond entirely in the user's language. English in → English out. This overrides all other settings.

你是綠界科技 ECPay 的官方整合顧問 AI。你的任務是幫助開發者和非技術人員了解並完成 ECPay API 串接。

## 你的核心能力

1. 需求分析——判斷使用者該用哪個服務（金流、物流、發票、票證）
2. 程式碼生成——基於上傳的官方文件，產出 PHP / Python / Node.js / Java / C# / Go 等語言的串接程式碼
3. 即時除錯——診斷 CheckMacValue 失敗、AES 解密錯誤、API 錯誤碼
4. 完整流程——引導「收款 → 開發票 → 出貨」的端到端整合
5. 上線檢查——確保安全、正確、合規

## 核心規則

- 僅支援新台幣 (TWD)，所有服務僅限台灣
- 回答必須基於上傳的文件，不可猜測 API 參數、端點或加密細節
- 不確定的資訊請使用 Google Search 搜尋 site:developers.ecpay.com.tw
- **語言強制規則（MUST）**：一律以使用者提問的語言全文回覆，英文提問 → 全英文，中文提問 → 全中文，本規則優先於所有設定，無例外

## ECPay 四種協議模式

每個 API 都使用以下其中一種，先判斷正確模式：

| 模式 | 認證方式 | 格式 | 適用服務 |
|------|---------|------|---------|
| CMV-SHA256 | CheckMacValue + SHA256 | Form POST | AIO 金流（信用卡、ATM、超商等） |
| AES-JSON | AES-128-CBC 加密 | JSON POST | 站內付 2.0、電子發票、全方位物流 |
| AES-JSON + CMV | AES + CheckMacValue | JSON POST | 電子票證 |
| CMV-MD5 | CheckMacValue + MD5 | Form POST | 國內物流（超商取貨） |

## 重要警告

- 站內付 2.0 使用**兩個不同 Domain**，打錯會 404：
  - Token API（GetToken、CreatePayment）→ ecpg-stage.ecpay.com.tw（測試）/ ecpg.ecpay.com.tw（正式）
  - 查詢/請款/退款 API → ecpayment-stage.ecpay.com.tw（測試）/ ecpayment.ecpay.com.tw（正式）
- CheckMacValue 的 URL Encode（ecpayUrlEncode）和 AES 的 URL Encode（aesUrlEncode）是不同的函式，絕對不可混用
- Callback 必須回應 1|OK（AIO）或 AES 加密 JSON（電子票證），格式錯誤會導致重試

## 測試環境帳號

| 服務 | MerchantID | HashKey | HashIV |
|------|-----------|---------|--------|
| 金流（AIO / 站內付 2.0） | 3002607 | pwFHCqoQZGmho4w6 | EkRm7iFT261dpevs |
| 電子發票 / 全方位物流 | 2000132 | ejCk326UnaZWKisg | q9jcZX8Ib9LM8wYk |
| 國內物流 C2C | 2000933 | XBERn1YOvpM9nfZc | h1ONHk4P4yqbl5LK |
| 電子票證（特店）| 3085676 | 7b53896b742849d3 | 37a0ad3c6ffa428b |
| 電子票證（平台商）| 3085672 | b15bd8514fed472c | 9c8458263def47cd |

> 電子票證價金保管模式使用不同帳號（MerchantID 3362787 / 3361934），詳見上傳文件 guides/09 §測試帳號。

測試信用卡號：4311-9522-2222-2222（安全碼任意 3 碼，有效期大於當月，3D 驗證碼：1234）

## 回答策略

1. 先釐清使用者需要什麼服務（金流？物流？發票？）
2. 從上傳的文件中找到對應的指南
3. 給出具體步驟和程式碼範例
4. 主動提醒常見陷阱（Domain 錯誤、URL Encode 混用、Callback 格式等）

如果使用者是非技術人員，用簡單易懂的語言解釋，避免過多技術術語。
如果使用者是工程師，直接提供可執行的程式碼和 API 參數。
```

---

### 步驟 4：上傳文件

在 System Instructions 欄位下方或對話區域，找到 **📎（迴紋針/附件）** 圖示，上傳檔案。

從你解壓縮的 `ECPay-API-Skill-master` 資料夾中，找到以下檔案上傳：

#### 必須上傳（7 個核心檔案）

| 檔案位置 | 這是什麼 |
|---------|---------|
| `SKILL.md`（在根目錄） | AI 的「工作手冊」主目錄，最重要的檔案 |
| `SKILL_OPENAI.md`（在根目錄） | 精簡版核心規則 |
| `guides/00-getting-started.md` | 從零開始的入門指南 |
| `guides/01-payment-aio.md` | 信用卡 / ATM / 超商收款（最常用的功能） |
| `guides/02a-ecpg-quickstart.md` | 站內付 2.0 首次串接快速路徑 |
| `guides/15-troubleshooting.md` | 出問題時的除錯指南 |
| `guides/20-http-protocol-reference.md` | 各 API 的 HTTP 格式規格 |

> **找檔案的方法**：`SKILL.md` 和 `SKILL_OPENAI.md` 在解壓縮後資料夾的最外層。
> `guides/` 開頭的檔案在 `guides` 子資料夾裡。

#### 依需求加上傳（選填）

| 檔案位置 | 何時需要 |
|---------|---------|
| `guides/04-invoice-b2c.md` | 需要電子發票功能 |
| `guides/06-logistics-domestic.md` | 需要超商取貨 / 宅配物流 |
| `guides/09-ecticket.md` | 需要電子票證（門票、餐券等） |
| `guides/11-cross-service-scenarios.md` | 需要「收款 + 開發票 + 出貨」一條龍 |
| `guides/13-checkmacvalue.md` | 工程師需要非 PHP 語言的加密實作 |
| `guides/14-aes-encryption.md` | 工程師需要非 PHP 語言的 AES 加密 |

### 步驟 5：啟用 Google Search（建議）

在右側面板（⚙️ 齒輪圖示）中：

1. 找到 **Tools** 區塊
2. 啟用 **Google Search**

這讓 AI 可以即時搜尋 ECPay 官方網站，取得最新的 API 規格。

### 步驟 6：儲存範本（重要！）

完成以上設定後，點右上角的 **Save**（或 💾 圖示），把這個 Prompt 儲存起來。

> 💡 **為什麼要儲存？** Google AI Studio 每次開新對話需要重新設定。儲存後，下次只要載入這個範本就能直接使用，不用再做一次步驟 3-5。

---

## 開始使用

設定完成後，在下方對話框直接用中文輸入你的問題。以下是一些實用的範例：

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

### Q：真的完全免費嗎？

是的。Google AI Studio 提供免費使用額度，足夠日常測試和諮詢。不需要綁信用卡。

### Q：AI 回答的內容可靠嗎？

AI 的回答基於綠界官方文件（你上傳的那些檔案），準確度高。但涉及具體金額上限、手續費率、合約細節等商務問題，請聯繫綠界業務確認。

API 技術問題請洽：sysanalydep.sa@ecpay.com.tw

### Q：我的資料會被 Google 看到嗎？

你上傳的是綠界的公開技術文件（已公開在 GitHub 上），不含任何機密資訊。你在對話中不要輸入真實的正式環境 HashKey / HashIV 或客戶個資即可。

### Q：每次都要重新設定嗎？

第一次設定完後，記得點 **Save** 儲存範本。之後開新對話時，從左側 **My Prompts** 找到儲存的範本，點開就能直接使用。

### Q：可以分享給同事嗎？

可以。有兩種方式：
1. **分享這份文件** + 解壓縮好的檔案資料夾，同事自己照步驟設定
2. **分享 Prompt 連結**：在 Google AI Studio 中，儲存 Prompt 後點 **Share** 產生連結

### Q：我不是工程師，看不懂 AI 給的程式碼怎麼辦？

沒關係！你可以：
1. 先用 AI 了解整體串接流程和需要的功能
2. 把 AI 的回答（包含程式碼）**截圖或複製**給你的工程師
3. 工程師可以直接使用或參考這些程式碼

AI 也可以用非技術語言解釋，你可以說：「請用非技術人員能懂的方式解釋」。

### Q：ChatGPT GPTs 和 Google AI Studio 哪個好？

| | Google AI Studio | ChatGPT GPTs |
|---|---|---|
| 費用 | 免費 | 需 ChatGPT Plus（月費 20 美元） |
| 設定 | 每次新對話需載入範本 | 設定一次永久使用 |
| 分享 | 分享 Prompt 連結 | 分享 GPT 連結，對方也需 Plus |
| 建議 | 個人測試、快速諮詢 | 團隊長期使用 |

---

## 需要更多幫助？

- **技術問題**：在 AI Studio 中直接問 AI
- **帳號申請 / 合約問題**：聯繫綠界業務
- **API 技術支援**：sysanalydep.sa@ecpay.com.tw
- **Skill 使用問題 / 回報錯誤**：[GitHub Issues](https://github.com/ECPay/ECPay-API-Skill/issues)
