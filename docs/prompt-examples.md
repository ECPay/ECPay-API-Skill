# ECPay 整合 Prompt 範例集

> 本文件提供 **36 個完整詳盡的 Prompt 範例**，可直接複製貼上給任何 AI 助手使用。
> 每個 Prompt 已包含測試帳號、環境網址、關鍵規則與注意事項，即使使用免費或較基礎的 AI 模型也能一次成功產出可用程式碼。
>
> **使用方式**：選擇符合你需求的範例，整段複製後貼給 AI 助手即可。如需調整語言或框架，直接修改 Prompt 中的「程式語言」欄位。

---

## 金流 — AIO 全方位金流

### 1. 信用卡一次付清（Go）

> 我要用 Go 串接 ECPay AIO 全方位金流的信用卡一次付清付款。
>
> **服務**：ECPay AIO 全方位金流
> **付款方式**：信用卡一次付清（ChoosePayment=Credit）
> **程式語言**：Go（標準庫 net/http，不使用第三方框架）
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
> - 測試信用卡號：4311-9522-2222-2222（安全碼任意三碼，有效期限任意未來月年，3D 驗證碼輸入 1234）
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **需要實作的完整流程**：
> 1. 建立一個 HTTP handler，接收前端傳來的訂單資訊（金額、商品名稱）
> 2. 組合 ECPay 需要的所有參數：MerchantID、MerchantTradeNo（不可重複，最長 20 字元，建議用時間戳+亂數）、MerchantTradeDate（格式 yyyy/MM/dd HH:mm:ss）、PaymentType=aio、TotalAmount、TradeDesc、ItemName、ReturnURL（接收付款結果的 Server URL）、ChoosePayment=Credit、EncryptType=1
> 3. 計算 CheckMacValue：將所有參數依照參數名稱 A-Z 排序 → 串成 key=value&key=value → 前面加上 HashKey= 後面加上 &HashIV= → 整串做 URL encode（使用「金流版 ecpayUrlEncode」：先 percent-encode，再全部轉小寫，再做 .NET 字元替換：%2d→-、%5f→_、%2e→.、%21→!、%2a→*、%28→(、%29→)、%20→+）→ SHA256 雜湊 → 轉大寫
> 4. 產生一個自動 submit 的 HTML Form（method=POST，action 為測試環境 URL），所有參數作為 hidden input，瀏覽器載入後 JavaScript 自動送出表單
> 5. 實作 ReturnURL handler（POST），接收綠界的付款結果通知：驗證回傳的 CheckMacValue 正確後，回傳純文字 `1|OK`（僅這 4 個字元，無 HTML）
>
> **關鍵規則（務必遵守）**：
> - CheckMacValue 驗證必須使用 timing-safe 比較（Go 用 `crypto/subtle.ConstantTimeCompare`），禁止用 `==` 比較
> - MerchantTradeNo 每次必須唯一，重複會被拒絕
> - ReturnURL 是 Server-to-Server 通知（不是用戶瀏覽器跳轉），必須是公開可訪��的 URL
> - 付款成功時回傳的 RtnCode=1；RtnCode 不是 1 表示付款失敗
> - 禁止將 HashKey/HashIV 寫在前端程式碼中

---

### 2. ATM 虛擬帳號付款（Python Flask）

> 我要用 Python Flask 串接 ECPay AIO 全方位金流的 ATM 虛擬帳號付款。
>
> **服務**：ECPay AIO 全方位金流
> **付款方式**：ATM 虛擬帳號（ChoosePayment=ATM）
> **程式語言**：Python 3.10+，Flask 框架
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **需要實作的完整流程**：
> 1. 建立 Flask route `/create-order`，產生 ECPay AIO 所需參數，ChoosePayment 設為 ATM
> 2. 計算 CheckMacValue（SHA256）：所有參數依照 key 名稱 A-Z 排序 → 組成 `key=value&` 字串 → 前方加 `HashKey=...&` 後方加 `&HashIV=...` → URL encode（金流版 ecpayUrlEncode：先 percent-encode → 全轉小寫 → .NET 字元替換 %2d→- %5f→_ %2e→. %21→! %2a→* %28→( %29→) %20→+）→ SHA256 → 轉大寫
> 3. 回傳自動 submit 的 HTML Form（POST 到測試環境 URL）
> 4. 實作 `/payment-info-callback` route（POST）接收「取號結果通知」：當消費者取得虛擬帳號後，綠界會通知你 BankCode 和 vAccount（虛擬帳號），你需要顯示給消費者
> 5. 實作 `/payment-result-callback` route（POST）接收「付款完成通知」：消費者實際去 ATM 轉帳後，綠界再通知 RtnCode=1 表示已入帳
> 6. 兩個 callback 都要驗證 CheckMacValue 後回傳純文字 `1|OK`
>
> **ATM 特有注意事項**：
> - ATM 有兩階段通知：第一次是取號通知（PaymentInfoURL），第二次是付款完成通知（ReturnURL）
> - 可設定 ExpireDate（1-60 天）指定虛擬帳號有效期限，預設 3 天
> - 測試環境無法實際轉帳完成，僅能收到取號通知
> - 付款金額限制：最低 1 元，單筆上限以銀行為準（一般 5 萬元）
>
> **關鍵規則**：
> - CheckMacValue 驗證必須使用 timing-safe 比較（Python 用 `hmac.compare_digest`）
> - 回傳的 `1|OK` 必須是純文字，Content-Type 為 text/plain，不可包含 HTML 標籤
> - 禁止將 HashKey/HashIV 寫在前端或版本控制中

---

### 3. 超商代碼繳費（TypeScript Express）

> 我要用 TypeScript + Express 串接 ECPay AIO 全方位金流的超商代碼繳費。
>
> **服務**：ECPay AIO 全方位金流
> **付款方式**：超商代碼繳費（ChoosePayment=CVS）
> **程式語言**：TypeScript，Express 框架，Node.js 18+
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **需要實作的完整流程**：
> 1. 建立 Express route `POST /api/create-cvs-order`，組合 ECPay AIO 參數，ChoosePayment 設為 CVS
> 2. 計算 CheckMacValue（SHA256）：參數 A-Z 排序 → 串成 key=value → 前後加 HashKey/HashIV → 金流版 URL encode（percent-encode → 全轉小寫 → .NET 替換 %2d→- %5f→_ %2e→. %21→! %2a→* %28→( %29→) %20→+）→ SHA256 → 轉大寫
> 3. 回傳自動 submit 的 HTML Form（POST 到測試環境 URL）
> 4. 實作 `POST /api/payment-info` 接收取號通知（PaymentInfoURL）：綠界會通知 PaymentNo（超商代碼）和 ExpireDate，你需要顯示給消費者去超商繳費
> 5. 實作 `POST /api/payment-result` 接收付款完成通知（ReturnURL）：消費者去超商繳完費後綠界通知你 RtnCode=1
> 6. 兩個 callback 都驗證 CheckMacValue 後回傳 `1|OK`
>
> **超商代碼特有注意事項**：
> - 超商代碼有效期限預設 10080 分鐘（7 天），可設定 StoreExpireDate 自訂（分鐘為單位）
> - 金額限制：最低 27 元，最高 20000 元（含手續費）
> - 和 ATM 一樣有兩階段通知（取號 + 付款完成）
> - 超商代碼為一組 14 碼數字，消費者需到超商多媒體機台（ibon、FamiPort）輸入後列印繳費單，再到櫃台繳費
>
> **關鍵規則**：
> - CheckMacValue 驗證用 `crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b))`，不可用 `===`
> - 禁止 HashKey/HashIV 出現在前端程式碼或 git 中

---

### 4. 定期定額信用卡訂閱扣款（Java Spring Boot）

> 我要用 Java Spring Boot 串接 ECPay AIO 全方位金流的信用卡定期定額扣款（訂閱制）。
>
> **服務**：ECPay AIO 全方位金流
> **付款方式**：信用卡定期定額（ChoosePayment=Credit，加上 PeriodAmount、PeriodType、Frequency、ExecTimes 參數）
> **程式語言**：Java 17+，Spring Boot 3.x
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
> - 測試信用卡號：4311-9522-2222-2222（安全碼任意三碼，有效期限任意未來月年，3D 驗證碼 1234）
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **需要實作的完整流程**：
> 1. 建立 Spring Boot Controller `POST /api/subscribe`，組合 ECPay AIO 定期定額參數
> 2. 除了一般 AIO 參數外，必須額外帶入：PeriodAmount（每期金額，需等於 TotalAmount）、PeriodType（D=日/M=月/Y=年）、Frequency（執行頻率，日1-365/月1-12/年1）、ExecTimes（執行次數，日最多999/月最多99/年最多9）、PeriodReturnURL（每期扣款結果通知 URL）
> 3. 計算 CheckMacValue（SHA256，金流版 URL encode）
> 4. 產生自動 submit HTML Form
> 5. 實作首次付款結果 callback（ReturnURL）：第一期扣款結果
> 6. 實作每期扣款結果 callback（PeriodReturnURL）：後續每期扣款結果通知
> 7. 兩個 callback 都驗證 CheckMacValue 後回傳 `1|OK`
>
> **定期定額特有注意事項**：
> - 首期扣款走一般信用卡流程（消費者需輸入卡號），後續自動扣款
> - PeriodAmount 必須等於 TotalAmount，不一致會被拒絕
> - 如需停止訂閱，需用另一支 API：`POST https://payment-stage.ecpay.com.tw/Cashier/CreditCardPeriodAction`（Action=ReAuth 停止）
> - 測試環境不會真的每月扣款，只會有第一期
>
> **關鍵規則**：
> - CheckMacValue 驗證用 `MessageDigest.isEqual()`，不可用 `.equals()`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 5. 信用卡分期付款（C# ASP.NET Core）

> 我要用 C# ASP.NET Core 串接 ECPay AIO 全方位金流的信用卡分期付款。
>
> **服務**：ECPay AIO 全方位金流
> **付款方式**：信用卡分期（ChoosePayment=Credit，加上 CreditInstallment 參數）
> **程式語言**：C# .NET 8，ASP.NET Core Minimal API
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
> - 測試信用卡號（永豐 30 期）：4938-1777-7777-7777（安全碼任意三碼，有效期限任意未來月年，3D 驗證碼 1234）
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **需要實作的完整流程**：
> 1. 建立 ASP.NET Core endpoint `POST /api/installment-order`，組合 ECPay AIO 分期參數
> 2. 額外參數：CreditInstallment（分期期數，用逗號分隔可提供多選，如 "3,6,12,18,24"），消費者在綠界頁面自行選擇期數
> 3. 計算 CheckMacValue（SHA256，金流版 URL encode）
> 4. 產生自動 submit HTML Form
> 5. 實作 ReturnURL callback（POST），驗證 CheckMacValue 後回傳 `1|OK`
>
> **分期特有注意事項**：
> - CreditInstallment 填入可選期數（如 "3,6,12"），消費者在綠界付款頁面自行選擇
> - 分期最低金額依銀行規定（一般 1000 元以上才可分期）
> - 回傳結果中 gwsr 為授權交易序號，可用於查詢
> - 可同時設定 CreditInstallment 和 UnionPay，讓消費者選擇分期或銀聯
> - 測試用永豐 30 期卡號 4938-1777-7777-7777 可模擬分期
>
> **關鍵規則**：
> - CheckMacValue 驗證用 `CryptographicOperations.FixedTimeEquals()`，不可用 `==`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 6. BNPL 先買後付（Ruby on Rails）

> 我要用 Ruby on Rails 串接 ECPay AIO 全方位金流的 BNPL 先買後付。
>
> **服務**：ECPay AIO 全方位金流
> **付款方式**：BNPL 先買後付（ChoosePayment=BNPL）
> **程式語言**：Ruby 3.2+，Rails 7
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **需要實作的完整流程**：
> 1. 建立 Rails controller action `POST /orders/create_bnpl`，組合 ECPay AIO 參數，ChoosePayment 設為 BNPL
> 2. 計算 CheckMacValue（SHA256，金流版 URL encode：percent-encode → 全轉小寫 → .NET 字元替換 %2d→- %5f→_ %2e→. %21→! %2a→* %28→( %29→) %20→+）→ SHA256 → 轉大寫
> 3. 回傳自動 submit HTML Form（POST 到測試環境 URL）
> 4. 實作 ReturnURL callback，驗證 CheckMacValue 後回傳 `1|OK`
>
> **BNPL 特有注意事項**：
> - BNPL 為綠界與第三方合作的「先買後付」服務，消費者可延後付款或分期
> - 最低金額依合作方規定
> - 此方式不會用到信用卡號，消費者在綠界頁面選擇 BNPL 合作方後完成付款
> - 除了 ChoosePayment=BNPL，其他參數和一般 AIO 完全相同
>
> **關鍵規則**：
> - CheckMacValue 驗證用 `OpenSSL.secure_compare()`，不可用 `==`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 7. TWQR 台灣 Pay 付款（Kotlin Spring Boot）

> 我要用 Kotlin + Spring Boot 串接 ECPay AIO 全方位金流的 TWQR（台灣 Pay）掃碼付款。
>
> **服務**：ECPay AIO 全方位金流
> **付款方式**：TWQR 台灣 Pay（ChoosePayment=TWQR）
> **程式語言**：Kotlin，Spring Boot 3.x
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **需要實作的完整流程**：
> 1. 建立 Kotlin Controller `POST /api/twqr-order`，組合 ECPay AIO 參數，ChoosePayment 設為 TWQR
> 2. 計算 CheckMacValue（SHA256，金流版 URL encode）
> 3. 產生自動 submit HTML Form
> 4. 實作 ReturnURL callback，驗證 CheckMacValue 後回傳 `1|OK`
>
> **TWQR 特有注意事項**：
> - TWQR 是透過台灣 Pay App 掃碼付款，消費者需安裝支援台灣 Pay 的銀行 App
> - 綠界頁面會顯示 QR Code 供消費者掃描
> - 金額限制依各銀行台灣 Pay 規範
> - 除了 ChoosePayment=TWQR，其他參數和一般 AIO 完全相同
>
> **關鍵規則**：
> - CheckMacValue 驗證用 `MessageDigest.isEqual()`（Kotlin 使用 Java 標準庫），不可用 `==`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

## 金流 — 站內付 2.0

### 8. 站內付 2.0 信用卡付款 — 前後端分離架構（Node.js + React）

> 我要用 Node.js（Express）+ React 串接 ECPay 站內付 2.0（ECPG）信用卡付款，前後端分離架構。
>
> **服務**：ECPay ECPG 站內付 2.0（EC Payment Gateway）
> **付款方式**：信用卡（在自己的網頁上嵌入綠界信用卡表單，消費者不離開你的網站）
> **程式語言**：後端 Node.js 18+ Express，前端 React
> **加密方式**：AES-128-CBC（與 AIO 的 CheckMacValue 完全不同！）
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
> - 測試信用卡號：4311-9522-2222-2222（安全碼任意三碼，有效期限任意未來月年，3D 驗證碼 1234）
>
> **測試環境 URL**：
> - Token API（GetTokenbyTrade、CreatePayment）：`https://ecpg-stage.ecpay.com.tw`
> - 查詢/請退款 API（QueryTrade、DoAction）：`https://ecpayment-stage.ecpay.com.tw`
> - **嚴禁混用這兩個 domain，混用會 404！**
>
> **前端 SDK（正式版）**：`https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js`（不要用 stage 版 SDK）
>
> **需要實作的完整流程（5 步驟）**：
> 1. **後端 GetTokenbyTrade**：`POST https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade`
>    - Request Body 為 JSON：`{ MerchantID, RqHeader: { Timestamp }, Data: "AES加密後的字串" }`
>    - Data 明文為 JSON 包含：MerchantID、OrderInfo（MerchantTradeNo、MerchantTradeDate、TotalAmount、ReturnURL、TradeDesc、ItemName）、CardInfo（OrderResultURL）、ConsumerInfo（MerchantMemberID—必填！格式為 MerchantID+買家識別碼，如 "3002607_member001"）
>    - AES 加密方式：明文 JSON → URL encode（AES 版：只做 urlencode，不轉小寫，不做 .NET 替換）→ AES-128-CBC 加密（key=HashKey, iv=HashIV, PKCS7 padding）→ Base64
>    - 回應解密後取得 Token
> 2. **前端載入 SDK + 渲染信用卡表單**：React 中載入 `sdk-1.0.0.js`，呼叫 `ECPay.initialize(...)` 帶入 Token，SDK 會在 `<div id="ECPayPayment">` 中渲染信用卡輸入表單（此 div id 不可更改，SDK 硬編碼）
> 3. **消費者填卡號 → SDK 回傳 PayToken**：消費者填完卡號按送出，SDK 觸發回調回傳 PayToken（一次性 token）
> 4. **後端 CreatePayment**：`POST https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment`
>    - Data 明文帶入 PayToken 和 MerchantTradeNo
>    - 回應解密後檢查：若有 ThreeDURL（非空字串）→ 導向消費者去 3D 驗證頁面；若無 → 付款直接完成
> 5. **接收付款結果 Callback（OrderResultURL）**：消費者完成付款或 3D 驗證後，綠界 POST 結果到 OrderResultURL，驗證後回傳 `1|OK`
>
> **AES 加密/解密完整步驟**（站內付 2.0 專用）：
> - 加密：明文 JSON string → `encodeURIComponent()`（即 URL encode，但不轉小寫、不做 .NET 替換）→ AES-128-CBC(key=HashKey, iv=HashIV, PKCS7 padding) → Base64 encode
> - 解密：Base64 decode → AES-128-CBC decrypt → URL decode → JSON.parse
> - **此 URL encode 與 AIO 的 CheckMacValue URL encode 完全不同！不要混用！**
>
> **關鍵規則**：
> - GetTokenbyTrade 和 CreatePayment 用 `ecpg-stage.ecpay.com.tw`
> - QueryTrade 和 DoAction 用 `ecpayment-stage.ecpay.com.tw`
> - 前端 div 必須是 `<div id="ECPayPayment">`，不可自訂 ID
> - ConsumerInfo.MerchantMemberID 必填（格式：MerchantID + 底線 + 會員識別碼）
> - 回應必須先檢查 TransCode===1（傳輸層），再解密 Data 檢查 RtnCode===1（業務層）
> - 禁止 HashKey/HashIV 出現在前端

---

### 9. 站內付 2.0 綁卡快速付款（Vue + Express）

> 我要用 Vue 3 + Node.js Express 串接 ECPay 站內付 2.0 的綁卡快速付款功能，讓會員綁定信用卡後免再輸入卡號。
>
> **服務**：ECPay ECPG 站內付 2.0 — 綁卡（TokenBind）+ 綁卡付款（CreatePaymentWithCardID）
> **程式語言**：後端 Node.js Express，前端 Vue 3
> **加密方式**：AES-128-CBC
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
> - 測試信用卡號：4311-9522-2222-2222（安全碼任意三碼，有效期限任意未來月年，3D 驗證碼 1234）
>
> **測試環境 URL**：
> - Token/綁卡/付款 API：`https://ecpg-stage.ecpay.com.tw`（GetTokenbyTrade、CreatePayment、GetTokenbyBindingCard、CreatePaymentWithCardID）
> - 查詢 API：`https://ecpayment-stage.ecpay.com.tw`（QueryTrade）
>
> **需要實作的完整流程**：
>
> **Part A — 首次綁卡付款（消費者第一次使用）**：
> 1. 後端呼叫 GetTokenbyTrade（同範例 8），但 CardInfo 中加入 `Bind: "1"` 表示要綁卡
> 2. 前端渲染信用卡表單，消費者輸入卡號付款
> 3. 後端 CreatePayment，回應中會包含 `BindCardID`（綁卡識別碼），儲存此 ID 與會員的對應關係
>
> **Part B — 快速付款（已��卡的會員）**：
> 1. 後端呼叫 `POST https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyBindingCard`
>    - Data 明文帶入 MerchantID、MerchantMemberID、BindCardID
> 2. 取得 Token 後，前端僅顯示卡號末四碼確認畫面（不需再輸入完整卡號）
> 3. 後端呼叫 `POST https://ecpg-stage.ecpay.com.tw/Merchant/CreatePaymentWithCardID` 完成付款
>
> **AES 加密方式**（同範例 8）：明文 → URL encode（只做 urlencode，不轉小寫，不做 .NET 替換）→ AES-128-CBC → Base64
>
> **關鍵規則**：
> - BindCardID 綁定在特定 MerchantMemberID 上，不同會員的 BindCardID 不可互用
> - 回應必須先檢查 TransCode===1，再解密 Data 檢查 RtnCode===1
> - Domain 不可混用：綁卡和付款用 ecpg-stage，查詢用 ecpayment-stage
> - 禁止 HashKey/HashIV 出現在前端

---

### 10. 站內付 2.0 iOS App 信用卡付款（Swift）

> 我要在 iOS App 中串接 ECPay 站內付 2.0 信用卡付款，使用 App WebView 載入綠界信用卡表單。
>
> **服務**：ECPay ECPG 站內付 2.0
> **程式語言**：Swift 5.9+，iOS 16+，使用 WKWebView
> **架構**：App 端用 WKWebView 載入前端頁面 → 前端頁面嵌入綠界 SDK → 後端提供 API
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
> - 測試信用卡號：4311-9522-2222-2222（安全碼任意三碼，有效期限任意未來月年，3D 驗證碼 1234）
>
> **測試環境 URL**：`https://ecpg-stage.ecpay.com.tw`
>
> **需要實作的完整流程**：
> 1. **後端 API**（任何語言均可）：實作 GetTokenbyTrade 和 CreatePayment（同範例 8 的後端邏輯）
> 2. **前端網頁**：建立一個 HTML 頁面，載入綠界 SDK `https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js`，在 `<div id="ECPayPayment">` 渲染信用卡表單
> 3. **Swift App**：
>    - 使用 WKWebView 載入上述前端頁面
>    - 設定 WKNavigationDelegate 攔截 3D 驗證頁面跳轉
>    - 透過 WKScriptMessageHandler 接收付款結果回調
>    - 設定 WKWebView 的 `customUserAgent` 避免被判定為 WebView 被阻擋
>
> **iOS App 特有注意事項**：
> - WKWebView 必須設定 `javaScriptEnabled = true`
> - 必須處理 3D 驗證的頁面跳轉（WKWebView 中開啟新視窗需特別處理 `createWebViewWith` delegate）
> - **嚴禁在 LINE/Facebook App 內建 WebView 開啟付款頁面**：會導致 MerchantID is Null 錯誤。需引導用戶用 Safari 開啟
> - App Transport Security (ATS) 需允許 `ecpg.ecpay.com.tw` 和 `ecpg-stage.ecpay.com.tw`
>
> **關鍵規則**：
> - AES 加密在後端執行，App 端不存放 HashKey/HashIV
> - 回應先檢查 TransCode===1 再檢查 RtnCode===1
> - 前端 div 必須是 `<div id="ECPayPayment">`

---

### 11. 站內付 2.0 Android App 信用卡付款（Kotlin）

> 我���在 Android App 中串接 ECPay 站內付 2.0 信用卡付款，使用 Android WebView 載入綠界信用卡表單。
>
> **服務**：ECPay ECPG 站內付 2.0
> **程式語言**：Kotlin，Android SDK 26+（minSdk），使用 Android WebView
> **架構**：App 端用 WebView 載入前端頁面 → 前端頁面嵌入綠界 SDK → 後端提供 API
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
> - 測試信用卡號：4311-9522-2222-2222（安全碼任意三碼，有效期限任意未來月年，3D 驗證碼 1234）
>
> **測試環境 URL**：`https://ecpg-stage.ecpay.com.tw`
>
> **需要實作的完整流程**：
> 1. **後端 API**（任何語言均可）：實作 GetTokenbyTrade 和 CreatePayment（同範例 8 的後端邏輯）
> 2. **前端網頁**：建立 HTML 頁面，載入 `https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js`，渲染 `<div id="ECPayPayment">`
> 3. **Android App**：
>    - 建立 WebView，設定 `WebSettings.javaScriptEnabled = true`
>    - 設定 `WebViewClient` 處理頁面導航和 3D 驗證跳轉
>    - 設定 `WebChromeClient` 處理新視窗開啟（3D 驗證可能開新頁）
>    - 使用 `addJavascriptInterface` 接收付款結果回調
>
> **Android App 特有注意事項**：
> - WebView 必須啟用 JavaScript：`webView.settings.javaScriptEnabled = true`
> - 必須設定 `webView.settings.domStorageEnabled = true`（SDK 可能使用 localStorage）
> - 3D 驗證跳轉需在 `WebViewClient.shouldOverrideUrlLoading` 中正確處理
> - **嚴禁在 LINE/Facebook App 內建 WebView 開啟**：會導致付款失敗
> - 需在 AndroidManifest.xml 加入 `android:usesCleartextTraffic="false"`（僅 HTTPS）
>
> **關鍵規則**：
> - AES 加密在後端執行，App 端不存放 HashKey/HashIV
> - 回應先檢查 TransCode===1 再檢查 RtnCode===1
> - 前端 div 必須是 `<div id="ECPayPayment">`

---

## 金流 — 幕後授權 / 查詢 / 退款

### 12. 幕後取號 — ATM 虛擬帳號背景產生（Python）

> 我要用 Python 串接 ECPay 幕後取號 API（GenPaymentCode），在後台自動產生 ATM 虛擬帳號給消費者，不需要消費者看到任何付款頁面。
>
> **服務**：ECPay ECPG 幕後取號（非信用卡幕後取號 — GenPaymentCode）
> **功能**：後台直接產生 ATM 虛擬帳號，然後把帳號資訊顯示給消費者去轉帳
> **程式語言**：Python 3.10+
> **加密方式**：AES-128-CBC + JSON（與站內付 2.0 相同的三層結構）
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **測試環境 URL**：`https://ecpg-stage.ecpay.com.tw/Merchant/GenPaymentCode`
>
> **需要實作的完整流程**：
> 1. 組合請求明文 JSON：MerchantID、MerchantTradeNo、MerchantTradeDate、TotalAmount、TradeDesc、ItemName、ChoosePayment="ATM"、ReturnURL、PaymentInfoURL
> 2. AES 加密明文：JSON string → URL encode（AES 版：只做 urlencode，不轉小寫，不做 .NET 替換）→ AES-128-CBC(key=HashKey, iv=HashIV, PKCS7) → Base64
> 3. 組合外層 JSON：`{ MerchantID, RqHeader: { Timestamp }, Data: "加密後字串" }`
> 4. POST 到測試環境 URL，Content-Type: application/json
> 5. 回應處理：先檢�� TransCode===1 → Base64 decode Data → AES decrypt → URL decode → JSON parse → 檢查 RtnCode===1 → 取得 BankCode + vAccount（虛擬帳號）
> 6. 將虛擬帳號和銀行代碼顯示給消費者
> 7. 實作 PaymentInfoURL callback 接收取號結果，ReturnURL 接收付款完成通知
>
> **幕後取號注意事項**：
> - 此 API 不需要消費者互動，適合電話訂購、後台建立訂單等場景
> - RqHeader 只需 Timestamp（Unix timestamp 秒），不需要 Revision
> - ChoosePayment 為頂層字串 "ATM"（與 AIO 格式相同，但與信用卡幕後授權的物件格式不同！）
> - 回應雙層檢查：TransCode===1 且 RtnCode===1 才算成功
>
> **關鍵規則**：
> - AES URL encode 只做 urlencode，不轉小寫，不做 .NET 替換（與金流 CheckMacValue 的 URL encode 不同！）
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 13. 查詢 AIO 訂單狀態（Python）

> 我要用 Python 查詢 ECPay AIO 全方位金流的訂單付款狀態。
>
> **服務**：ECPay AIO 訂單查詢（QueryTradeInfo）
> **功能**：查詢已建立的 AIO 訂單目前的付款狀態
> **程式語言**：Python 3.10+
> **加密方式**：CheckMacValue SHA256（與 AIO 建立訂單相同）
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5`
>
> **需要實作的完整流程**：
> 1. 組合查詢參數：MerchantID、MerchantTradeNo（要查詢的訂單編號）、TimeStamp（Unix timestamp 秒）
> 2. 計算 CheckMacValue（SHA256，金流版 URL encode）
> 3. POST 到查詢 URL（Content-Type: application/x-www-form-urlencoded）
> 4. 回傳為 URL-encoded 字串（如 `MerchantID=xxx&MerchantTradeNo=xxx&TradeStatus=1&...`），需 URL decode 解析
> 5. 驗證回傳的 CheckMacValue
> 6. 判斷 TradeStatus：0=未付款、1=已付款、10200095=付款失敗
>
> **注意事項**：
> - 查詢 API 的 Content-Type 是 application/x-www-form-urlencoded（不是 JSON）
> - 回傳也是 URL-encoded 字串（不是 JSON）
> - TimeStamp 必須是當下的 Unix timestamp（秒），與伺服器時間差異太大會被拒絕
> - 此 API 有頻率限制，不建議用於輪詢，應透過 ReturnURL callback 接收即時通知
>
> **關鍵規則**：
> - CheckMacValue 驗證用 `hmac.compare_digest()`，不可用 `==`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 14. AIO 信用卡退款 — 當日取消 vs 事後請退（Node.js）

> 我要用 Node.js 實作 ECPay AIO 信用卡退款功能，需要區分「當日取消授權」和「事後請退款」兩種情境。
>
> **服務**：ECPay AIO 信用卡退款（DoAction）
> **程式語言**：Node.js 18+，Express
> **加密方���**：CheckMacValue SHA256
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/CreditDetail/DoAction`
>
> **需要實作的兩種退款情境**：
>
> **情境 A — 當日取消授權（Void）**：交易當天尚未請款前，可直接取消整筆授權
> - Action = "N"（取消授權）
> - TotalAmount = 原交易金額（必須等於原交易全額）
>
> **情境 B — 事後請退款（Refund）**：交易已請款入帳後，可退全額或部分金額
> - Action = "R"（退款）
> - TotalAmount = 要退的金額（可小於等於原交易金額，支持部分退款）
>
> **共同實作步驟**：
> 1. 組合參數：MerchantID、MerchantTradeNo（原訂單編號）、TradeNo（綠界交易編號）、Action（"N" 或 "R"）、TotalAmount
> 2. 計算 CheckMacValue（SHA256，金流版 URL encode）
> 3. POST 到退款 URL（Content-Type: application/x-www-form-urlencoded）
> 4. 回傳為 pipe-separated 字串，解析 RtnCode：1=成功
>
> **退款注意事項**：
> - 當日取消（N）：只能在授權當天、撥款前執行，必須全額取消
> - 事後退款（R）：已撥款後才能執行，可部分退款，每筆訂單最多退 10 次
> - 需要 TradeNo（綠界交易編號），此值來自建立訂單時 callback 回傳的結果
> - 撥款日（T+N 天）前用取消，撥款日後用退款。不確定時用退款（R）較安全
>
> **關鍵規則**：
> - CheckMacValue 驗證用 `crypto.timingSafeEqual()`，不可用 `===`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 15. 站內付 2.0 定期定額查詢與停止代扣（Go）

> 我要用 Go 查詢 ECPay 站內付 2.0（ECPG）的定期定額訂閱狀態，以及停止自動代扣。
>
> **服務**：ECPay ECPG 定期定額查詢與操作（CreditCardPeriodAction、QueryTrade）
> **程式語言**：Go（標準庫 net/http）
> **加密方式**：AES-128-CBC + JSON
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **測試環境 URL**：
> - 查詢：`https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryTrade`（注意是 ecpayment，不是 ecpg）
> - 定期定額操作：`https://ecpayment-stage.ecpay.com.tw/1.0.0/Credit/CreditCardPeriodAction`
>
> **需要實作的功能**：
>
> **功能 A — 查詢訂閱狀態**：
> 1. 組合 Data 明文 JSON：MerchantID、MerchantTradeNo
> 2. AES 加密（AES 版 URL encode → AES-128-CBC → Base64）
> 3. POST 到查詢 URL，JSON body：`{ MerchantID, RqHeader: { Timestamp }, Data }`
> 4. 解密回應取得訂閱狀態資訊
>
> **功能 B — 停止自動代扣**：
> 1. 組合 Data 明文 JSON：MerchantID、MerchantTradeNo、Action="ReAuth"（停止）
> 2. AES 加密 → POST 到操作 URL
> 3. 解密回應確認 RtnCode===1
>
> **注意事項**：
> - 查詢和操作 API 都在 `ecpayment-stage`（不是 ecpg-stage！）
> - AES URL encode 只做 urlencode，不轉小寫，不做 .NET 替換
> - 回應雙層檢查：TransCode===1 且 RtnCode===1
> - Action 選項：ReAuth=停止扣款、Cancel=取消訂閱（不可復原）
>
> **關鍵規則**：
> - Domain 必須用 `ecpayment-stage.ecpay.com.tw`（查詢/操作類 API）
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

## 電子發票

### 16. B2C 電子發票開立（Python）

> 我要用 Python 串接 ECPay 電子發票 B2C 開立（企業開給消費者）。
>
> **服務**：ECPay 電子發票 B2C 開立
> **功能**：消費者付款完成後，自動開立電子發票
> **程式語言**：Python 3.10+
> **加密方式**：AES-128-CBC + JSON（AES-JSON 協定）
>
> **測試帳號**（發票專用，與金流不同！）：
> - MerchantID：2000132
> - HashKey：ejCk326UnaZWKisg
> - HashIV：q9jcZX8Ib9LM8wYk
>
> **測試環境 URL**：`https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue`
>
> **需要實作的完整流程**：
> 1. 組合發票開立明文 JSON（Data 內容）：
>    - MerchantID、RelateNumber（關聯編號，對應訂單號，不可重複，最長 30 字元）
>    - CustomerID（選填）、CustomerIdentifier（統一編號，個人發票不填）
>    - CustomerName、CustomerAddr、CustomerPhone、CustomerEmail（至少填 Phone 或 Email 之一）
>    - Print（0=不印紙本/1=印紙本）、Donation（0=不捐贈/1=捐贈）
>    - LoveCode（捐贈碼，Donation=1 時必填）
>    - CarrierType（載具類型：空字串=不用載具、1=綠界會員載具、2=自然人憑證、3=手機條碼）
>    - CarrierNum（載具號碼，CarrierType=3 時填手機條碼 /XXX+XXXX）
>    - TaxType（1=應稅、2=零稅率、3=免稅、9=混合）
>    - SalesAmount（發票金額，含稅）
>    - InvoiceRemark（備註）
>    - Items（商品陣列）：每項包含 ItemSeq、ItemName、ItemCount、ItemWord（單位）、ItemPrice、ItemTaxType、ItemAmount
>    - InvType（07=一般稅額、08=特種稅額）
>    - vat（1=含稅價）
> 2. AES 加密：明文 JSON → URL encode（AES 版：只做 urlencode，不轉小寫，不做 .NET 替換）→ AES-128-CBC(key=HashKey, iv=HashIV, PKCS7) → Base64
> 3. 組合外層 JSON：`{ MerchantID: "2000132", RqHeader: { Timestamp, Revision: "3.0.0" }, Data: "加密字串" }`
>    - **注意**：發票的 RqHeader 需要 Revision: "3.0.0"（與其他服務不同！）
> 4. POST 到測試 URL，Content-Type: application/json
> 5. 回應處理：檢查 TransCode===1 → 解密 Data → 檢查 RtnCode===1 → 取得 InvoiceNo（發票號碼）
>
> **B2C 發票注意事項**：
> - MerchantID 用發票專用帳號 2000132（不是金流的 3002607！帳號混用會失敗）
> - RqHeader 必須包含 `Revision: "3.0.0"`
> - Items 為陣列，每項的 ItemAmount = ItemPrice × ItemCount
> - SalesAmount 必須等於所有 Items 的 ItemAmount 加總
> - 個人發票（無統一編號）：CarrierType 填手機條碼(3)或自然人憑證(2)，Print=0
> - 公司發票（有統一編號）：CustomerIdentifier 填 8 碼統編，Print=1
>
> **關鍵規則**：
> - AES URL encode 與金流 CheckMacValue URL encode 完全不同，不可混用
> - 回應雙層檢查：TransCode===1（傳輸）+ RtnCode===1（業務）
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 17. B2B 電子發票開立（Java）

> 我要用 Java 串接 ECPay 電子發票 B2B 開立（企業對企業）。
>
> **服務**：ECPay 電子發票 B2B 開立
> **功能**：開立給其他公司的電子發票（含買方統一編號）
> **程式語言**：Java 17+
> **加密方式**：AES-128-CBC + JSON
>
> **測試帳號**（發票專用）：
> - MerchantID：2000132
> - HashKey：ejCk326UnaZWKisg
> - HashIV：q9jcZX8Ib9LM8wYk
>
> **測試環境 URL**：`https://einvoice-stage.ecpay.com.tw/B2BInvoice/Issue`
>
> **需要實作的完整流程**：
> 1. 組合 B2B 發票明文 JSON：
>    - MerchantID、RelateNumber（不可重複）
>    - CustomerIdentifier（買方統一編號，B2B 必填 8 碼）
>    - CustomerName（買方公司名稱）、CustomerAddr（買方地址）
>    - CustomerEmail
>    - TaxType（1=應稅、2=零稅率、3=免稅）
>    - SalesAmount（含稅金額）
>    - Items 陣列：ItemSeq、ItemName、ItemCount、ItemWord、ItemPrice、ItemTaxType、ItemAmount
>    - InvType（07=一般）
>    - vat=1（含稅）
> 2. AES 加密（同 B2C）
> 3. 外層 JSON 帶 `RqHeader: { Timestamp, Revision: "3.0.0" }`
> 4. POST → 解密回應 → 取得發票號碼
>
> **B2B 與 B2C 差異**：
> - 端點路徑不同：B2B 用 `/B2BInvoice/Issue`，B2C 用 `/B2CInvoice/Issue`
> - B2B 必填 CustomerIdentifier（買方統編）
> - B2B 無載具、無捐贈選項（公司發票必須印紙本）
> - B2B 有「交換模式」和「存證模式」，一般用交換模式（透過政府平台傳送）
>
> **關鍵規則**：
> - 使用發票專用帳號 2000132，不是金流帳號
> - RqHeader 需 Revision: "3.0.0"
> - AES URL encode 只做 urlencode，不轉小寫，不做 .NET 替換
> - CheckMacValue 驗證（如有回傳）用 `MessageDigest.isEqual()`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 18. 電子發票折讓（C#）

> 我要用 C# 串接 ECPay 電子發票折讓功能，消費者部分退貨時需要開立折讓單。
>
> **服務**：ECPay 電子發票 B2C 折讓（Allowance）
> **功能**：已開立的發票需要部分退款時，開折讓單（而非作廢整張發票重開）
> **程式語言**：C# .NET 8
> **加密方式**：AES-128-CBC + JSON
>
> **測試帳號**（發票專用）：
> - MerchantID：2000132
> - HashKey：ejCk326UnaZWKisg
> - HashIV：q9jcZX8Ib9LM8wYk
>
> **測試環境 URL**：`https://einvoice-stage.ecpay.com.tw/B2CInvoice/Allowance`
>
> **需要實作的完整流程**：
> 1. 組合折讓明文 JSON：
>    - MerchantID
>    - InvoiceNo（原發票號碼，10 碼，如 AB12345678）
>    - InvoiceDate（原發票開立日期，格式 yyyy-MM-dd）
>    - AllowanceNotify（通知方式：S=簡訊、E=Email、A=全部、N=不通知）
>    - CustomerName（買方名稱）
>    - NotifyMail（通知 Email，AllowanceNotify=E 或 A 時必填）
>    - NotifyPhone（通知手機，AllowanceNotify=S 或 A 時必填）
>    - AllowanceAmount（折讓金額）
>    - Items 陣列：ItemSeq、ItemName、ItemCount、ItemWord、ItemPrice、ItemTaxType、ItemAmount
> 2. AES 加密（同 B2C 開立）
> 3. 外層 JSON 帶 `RqHeader: { Timestamp, Revision: "3.0.0" }`
> 4. POST → 解密回應 → 檢查 RtnCode===1 → 取得 IA_Allow_No（折讓編號）
>
> **折讓注意事項**：
> - 折讓金額不可大於原發票金額
> - 同一張發票可開多次折讓，但累計不可超過原始金額
> - 折讓開立後不可修改，但可以「作廢折讓」（另一支 API：/B2CInvoice/AllowanceInvalid）
> - 如果要整張發票作廢重開，用 `/B2CInvoice/Invalid`（作廢）而非折讓
>
> **關鍵規則**：
> - 使用發票專用帳號 2000132
> - RqHeader 需 Revision: "3.0.0"
> - AES URL encode 只做 urlencode，不轉小寫，不做 .NET 替換
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 19. 電子發票作廢 + 查詢（Rust）

> 我要用 Rust 串接 ECPay 電子發票作廢和查詢功能。
>
> **服務**：ECPay 電子發票 B2C 作廢（Invalid）+ 查詢（Issue）
> **程式語言**：Rust（使用 reqwest + serde_json + aes crate）
> **加密方式**：AES-128-CBC + JSON
>
> **測試帳號**（發票專用）：
> - MerchantID：2000132
> - HashKey：ejCk326UnaZWKisg
> - HashIV：q9jcZX8Ib9LM8wYk
>
> **測試環境 URL**：
> - 作廢發票：`https://einvoice-stage.ecpay.com.tw/B2CInvoice/Invalid`
> - 查詢發票：`https://einvoice-stage.ecpay.com.tw/B2CInvoice/GetIssue`
>
> **功能 A — 作廢發票**：
> 1. 組合明文 JSON：MerchantID、InvoiceNo（要作廢的發票號碼）、InvoiceDate（開立日期）、Reason（作廢原因）
> 2. AES 加密 → POST → 解密回應 → 確認 RtnCode===1
>
> **功能 B — 查詢發票**：
> 1. 組合明文 JSON：MerchantID、RelateNumber（關聯編號）或 InvoiceNo
> 2. AES 加密 → POST → 解密回應 → 取得發票詳細資訊
>
> **注意事項**：
> - 作廢後無法復原，須重新開立
> - 作廢日期必須在開立日期的當期或次期（跨期無法作廢）
> - 查詢可用 RelateNumber 或 InvoiceNo 任一查詢
> - RqHeader 都需要 Revision: "3.0.0"
>
> **Rust AES 實作提示**：
> - 使用 `aes` + `cbc` + `cipher` crate 實作 AES-128-CBC
> - PKCS7 padding 使用 `cipher::block_padding::Pkcs7`
> - URL encode 使用 `urlencoding::encode()`（只做 percent-encode，不轉小寫，不做 .NET 替換）
>
> **關鍵規則**：
> - 使用發票專用帳號 2000132
> - AES URL encode 不轉小寫、不做 .NET 替換
> - CheckMacValue 驗證（如有）用 `subtle::ConstantTimeEq`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

## 物流

### 20. 超商取貨付款 — 7-11 / 全家（C#）

> 我要用 C# 串接 ECPay 國內物流超商取貨付款（7-11 和全家超商）。
>
> **服務**：ECPay 國內物流（超商 B2C 取貨付款）
> **功能**：消費者選擇超商門市，商品寄到該門市後消費者取貨時付款
> **程式語言**：C# .NET 8，ASP.NET Core
> **加密方式**：CheckMacValue MD5（國內物流用 MD5，不是 SHA256！）
>
> **測試帳號**（物流專用，與金流不同！）：
> - MerchantID：2000132
> - HashKey：5294y06JbISpM5x9
> - HashIV：v77hoKGq4kWxNNIS
>
> **測試環境 URL**：`https://logistics-stage.ecpay.com.tw`
>
> **需要實作的完整流程（三步驟）**：
>
> **步驟 1 — 開啟門市地圖讓消費者選店**：
> - 用 Form POST 到 `https://logistics-stage.ecpay.com.tw/Express/map`
> - 參數：MerchantID、LogisticsType=CVS、LogisticsSubType（UNIMART=7-11、FAMI=全家）、IsCollection=Y（取貨付款）、ServerReplyURL（收到選店結果的 URL）
> - 綠界會開啟門市地圖，消費者選好店後 POST 回 ServerReplyURL 帶 CVSStoreID、CVSStoreName、CVSAddress
>
> **步驟 2 — 建立物流訂單**：
> - POST 到 `https://logistics-stage.ecpay.com.tw/Express/Create`
> - Content-Type: application/x-www-form-urlencoded
> - 參數：MerchantID、MerchantTradeNo、MerchantTradeDate、LogisticsType=CVS、LogisticsSubType（UNIMART 或 FAMI）、GoodsAmount（金額，取貨付款時為代收金額）、CollectionAmount（代收金額，同 GoodsAmount）、IsCollection=Y、GoodsName、SenderName、SenderPhone、SenderCellPhone、ReceiverName、ReceiverPhone、ReceiverCellPhone、ReceiverStoreID（步驟 1 取得的 CVSStoreID）、ServerReplyURL（物流狀態通知 URL）
> - 計算 CheckMacValue（**MD5**！不是 SHA256）：排序 → 組字串 → 前後加 HashKey/HashIV → 金流版 URL encode（同 AIO，percent-encode → 轉小寫 → .NET 替換）→ **MD5** → 轉大寫
> - 回傳為 pipe-separated 字串（1|OK|AllPayLogisticsID|...），用 | 分隔解析
>
> **步驟 3 — 接收物流狀態通知**：
> - 綠界會持續 POST 物流狀態到 ServerReplyURL
> - 驗證 CheckMacValue（MD5）後回傳 `1|OK`
> - 物流狀態碼：2030=到店（消費者可取貨）、2067=已取貨、3024=退貨
>
> **國內物流注意事項**：
> - 國內物流用 **MD5** 加密（不是 SHA256！與金流不同）
> - 國內物流用 Form POST（不是 JSON！與全方位物流不同）
> - 帳號用 2000132（不是金流的 3002607！）
> - 7-11 包裹限制：長+寬+高 ≤ 105cm，重量 ≤ 10kg
> - 全家包裹限制：長+寬+高 ≤ 100cm，重量 ≤ 10kg
> - 取貨付款代收金額上限依超商規定（一般 20000 元）
>
> **關鍵規則**：
> - 加密用 **MD5**（不是 SHA256）
> - CheckMacValue 驗證用 `CryptographicOperations.FixedTimeEquals()`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 21. 宅配物流 + 列印託運單（Go）

> 我要用 Go 串接 ECPay 國內物流宅配服務，並產生可列印的託運單。
>
> **服務**：ECPay 國內物流（宅配）
> **功能**：建立宅配物流訂單，取得託運單列印 URL
> **程式語言**：Go（標準庫 net/http）
> **加密方式**：CheckMacValue MD5
>
> **測試帳號**（物流專用）：
> - MerchantID：2000132
> - HashKey：5294y06JbISpM5x9
> - HashIV：v77hoKGq4kWxNNIS
>
> **測試環境 URL**：`https://logistics-stage.ecpay.com.tw`
>
> **需要實作的完整流程**：
>
> **步驟 1 — 建立宅配物流訂單**：
> - POST 到 `https://logistics-stage.ecpay.com.tw/Express/Create`
> - Content-Type: application/x-www-form-urlencoded
> - 參數：MerchantID、MerchantTradeNo、MerchantTradeDate、LogisticsType=HOME、LogisticsSubType=TCAT（黑貓）或 ECAN（宅配通）、GoodsAmount、GoodsName、GoodsWeight（公克）、SenderName、SenderPhone、SenderCellPhone、SenderZipCode、SenderAddress、ReceiverName、ReceiverPhone、ReceiverCellPhone、ReceiverZipCode、ReceiverAddress、Temperature（0001=常溫/0002=冷藏/0003=冷凍）、Distance（00=同縣市/01=外縣市/02=離島）、Specification（0001=60cm/0002=90cm/0003=120cm/0004=150cm）、ServerReplyURL
> - 計算 CheckMacValue（MD5，金流版 URL encode）
> - 回傳 pipe-separated 字串，解析取得 AllPayLogisticsID
>
> **步驟 2 — 產生測試標籤用於列印**：
> - POST 到 `https://logistics-stage.ecpay.com.tw/Express/CreateTestData`
> - 帶入 MerchantID、AllPayLogisticsID、CheckMacValue
> - 回傳 HTML 可直接列印託運單（測試環境為測試標籤）
>
> **步驟 3 — 接收物流狀態通知**：
> - ServerReplyURL 接收物流狀態 POST，驗證 CheckMacValue 後回傳 `1|OK`
>
> **宅配注意事項**：
> - 宅配不需要門市地圖（直接填收件地址）
> - LogisticsSubType：TCAT=黑貓宅急便、ECAN=宅配通
> - 黑貓支援冷藏/冷凍（Temperature 參數）
> - 正式環境列印託運單有另外的端點（非 CreateTestData）
> - 加密方式是 **MD5**（國內物流統一用 MD5）
>
> **關鍵規則**：
> - 加密用 **MD5**（不是 SHA256）
> - CheckMacValue 驗證用 `subtle.ConstantTimeCompare()`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 22. 跨境物流寄送（TypeScript）

> 我要用 TypeScript 串接 ECPay 跨境物流，將商品從台灣寄送到海外（香港、馬來西亞等）。
>
> **服務**：ECPay 跨境物流
> **功能**：建立跨境物流訂單，將台灣商品寄到海外
> **程式語言**：TypeScript，Node.js 18+
> **加密方式**：AES-128-CBC + JSON（跨境物流用 AES-JSON，與國內物流的 MD5 不同！）
>
> **測試帳號**（物流專用）：
> - MerchantID：2000132
> - HashKey：5294y06JbISpM5x9
> - HashIV：v77hoKGq4kWxNNIS
>
> **測試環境 URL**：`https://logistics-stage.ecpay.com.tw/CrossBorder/`
>
> **需要實作的完整流程**：
> 1. 組合跨境物流明文 JSON：
>    - MerchantID、MerchantTradeNo、MerchantTradeDate
>    - LogisticsType（CROSS_BORDER_CVS=跨境超商、CROSS_BORDER_HOME=跨境宅配）
>    - GoodsAmount、GoodsName、GoodsCurrency（幣別，如 HKD、MYR）
>    - SenderName、SenderPhone、SenderAddress
>    - ReceiverName、ReceiverPhone、ReceiverAddress、ReceiverCountry
>    - ServerReplyURL
> 2. AES 加密：JSON → URL encode（AES 版，只做 urlencode，不轉小寫，不做 .NET 替換）→ AES-128-CBC → Base64
> 3. 組合外層 JSON：`{ MerchantID, RqHeader: { Timestamp, Revision: "1.0.0" }, Data }`
>    - 注意跨境物流 RqHeader 需 `Revision: "1.0.0"`
> 4. POST 到 `https://logistics-stage.ecpay.com.tw/CrossBorder/CreateLogisticsOrder`，Content-Type: application/json
> 5. 回應處理：檢查 TransCode===1 → 解密 Data → 檢查 RtnCode===1 → 取得物流編號
>
> **跨境物流注意事項**：
> - 跨境物流用 AES-JSON 協定（不是國內物流的 Form POST + MD5）
> - RqHeader 需 Revision: "1.0.0"（不同於發票的 "3.0.0"）
> - 帳號和國內物流相同（2000132），但加密方式不同
> - 目前支援地區依綠界開放範圍，可能包含香港、馬來西亞、新加坡等
> - 跨境物流的 callback 回應也需要 AES 加密 JSON（不是 `1|OK`）
>
> **關鍵規則**：
> - AES URL encode 只做 urlencode，不轉小寫，不做 .NET 替換
> - 回應雙層檢���：TransCode===1 + RtnCode===1
> - Callback 回應必須是 AES 加密 JSON 格式（不是純文字 `1|OK`！跨境物流和國內物流不同）
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 23. 物流狀態查詢與回調處理（PHP）

> 我要用 PHP 實作 ECPay 國內物流的狀態查詢 API 和物流狀態回調（ServerReplyURL）處理。
>
> **服務**：ECPay 國內物流 — 查詢物流訂單 + 狀態回調處理
> **程式語言**：PHP 8.1+
> **加密方式**：CheckMacValue MD5
>
> **測試帳號**（物流專用）：
> - MerchantID：2000132
> - HashKey：5294y06JbISpM5x9
> - HashIV：v77hoKGq4kWxNNIS
>
> **測試環境 URL**：
> - 查詢：`https://logistics-stage.ecpay.com.tw/Helper/QueryLogisticsTradeInfo/V5`
>
> **功能 A — 主動查詢物流狀態**：
> 1. 組合參數：MerchantID、AllPayLogisticsID（建立訂單時取得的物流編號）、TimeStamp
> 2. 計算 CheckMacValue（MD5，金流版 URL encode）
> 3. POST（application/x-www-form-urlencoded）
> 4. 回傳 URL-encoded 字串，解析取得 LogisticsStatus（物流狀態碼）
>
> **功能 B — 接收物流狀態回調（ServerReplyURL）**：
> 1. 綠界會 POST 到你建立訂單時指定的 ServerReplyURL
> 2. 接收 POST 參數（form data）
> 3. 驗證 CheckMacValue（MD5）
> 4. 解析 LogisticsStatus 更新訂單狀態
> 5. 回傳純文字 `1|OK`
>
> **常用物流狀態碼**：
> - 300=已出貨、2030=到店（消費者可取貨）、2067=消費者已取貨、3024=退貨（超過取貨期限）
>
> **注意事項**：
> - 國內物流用 MD5（不是 SHA256）
> - 查詢 API 有頻率限制，建議依賴 ServerReplyURL 被動接收通知
> - ServerReplyURL 會被呼叫多次（每次物流狀態變更都會通知）
> - 回傳的 `1|OK` 必須是純文字，非 HTML
>
> **關鍵規則**：
> - 加密用 MD5
> - CheckMacValue 驗證用 `hash_equals()`，不可用 `==` 或 `===`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

## 電子票證

### 24. 電子票券發行 — 演唱會門票（Rust）

> 我要用 Rust 串接 ECPay 電子票證服務發行演唱會電子票券（價金保管-使用後核銷模式）。
>
> **服務**：ECPay 電子票證（ECTicket）— 價金保管-使用後核銷
> **功能**：發行電子票券，消費者購買後取得票券，入場使用後核銷並撥款
> **程式語言**：Rust（使用 reqwest + serde_json + aes crate）
> **加密方式**：AES-128-CBC + JSON + CheckMacValue SHA256（三重驗證！與其他服務不同）
>
> **測試帳號**（電子票證專用，與金流、發票完全不同！）：
> - MerchantID：3085676（特店模式）
> - HashKey：7b53896b742849d3
> - HashIV：37a0ad3c6ffa428b
>
> **測試環境 URL**：`https://ecticket-stage.ecpay.com.tw`
>
> **需要實作的完整流程**：
>
> **步驟 1 — 發行票券（IssueVoucher）**：
> 1. 組合 Data 明文 JSON：MerchantID、VoucherName（票券名稱）、VoucherAmount（票價）、VoucherQuantity（發行數量）、VoucherExpireDate（有效期限）、UseStatusNotifyURL（核銷通知 URL）等
> 2. AES 加密 Data：JSON → URL encode（AES 版：只做 urlencode，不轉小寫，不做 .NET 替換）→ AES-128-CBC(key=HashKey, iv=HashIV, PKCS7) → Base64
> 3. **計算 CheckMacValue**（電子票證特有！）：`HashKey={HashKey}&Data={加密後Data字串}&HashIV={HashIV}` → SHA256 → 轉大寫
>    - 注意：CMV 公式與 AIO 不同！AIO 是排序所有參數，電子票證是固定 `HashKey + Data + HashIV`
> 4. 組合完整 Request JSON：`{ MerchantID, RqHeader: { Timestamp }, Data: "加密字串", CheckMacValue: "CMV字串" }`
> 5. POST 到 `https://ecticket-stage.ecpay.com.tw/Voucher/IssueVoucher`
>
> **步驟 2 — 處理回應（三重驗證）**：
> 1. 檢查外層 `TransCode === 1`
> 2. AES 解密 Data 取得明文 JSON
> 3. **驗證回應的 CheckMacValue**：用解密後的 Data 明文字串計算 CMV，與回應中的 CheckMacValue 比對
> 4. 檢查內層 `RtnCode === 1`（注意是整數 1，不是字串 "1"）
>
> **步驟 3 — 接收核銷通知（UseStatusNotifyURL）**：
> - 消費者使用票券後，綠界 POST 到 UseStatusNotifyURL
> - **回應格式與 AIO 完全不同！** 不是回�� `1|OK`，而是必須回傳 AES 加密 JSON + CheckMacValue
> - 回傳格式：`{ MerchantID, RqHeader: { Timestamp }, Data: "AES加密的回應JSON", CheckMacValue }`
>
> **電子票證特有注意事項**：
> - 加密用獨立帳號（3085676），HashKey/HashIV 與金流、發票、物流全都不同
> - CheckMacValue 計算公式與 AIO 不同：固定 `HashKey + Data + HashIV` 組合，非參數排序
> - 回應需要三重檢查（TransCode → CMV → RtnCode）
> - Callback 回應格式是 AES JSON + CMV，不是純文字 `1|OK`！
> - RtnCode 為整數 1（`=== 1`），不是字串 "1"
>
> **關鍵規則**：
> - CheckMacValue 驗證用 `subtle::ConstantTimeEq`
> - 不可對 Callback 回傳 `1|OK`（會被視為失敗並重試）
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 25. 電子票券核銷與退票（C++）

> 我要用 C++ 實作 ECPay 電子票證的核銷和退票功能。
>
> **服務**：ECPay 電子票證（ECTicket）— 核銷（UseVoucher）+ 退票（ReturnVoucher）
> **程式語言**：C++17（使用 libcurl + OpenSSL + nlohmann/json）
> **加密方式**：AES-128-CBC + JSON + CheckMacValue SHA256
>
> **測試帳號**（電子票證專用）：
> - MerchantID：3085676
> - HashKey：7b53896b742849d3
> - HashIV：37a0ad3c6ffa428b
>
> **測試環境 URL**：`https://ecticket-stage.ecpay.com.tw`
>
> **功能 A — 核銷票券（消費者入場）**：
> 1. 組合 Data 明文：MerchantID、VoucherNo（票券編號）、VerifyCode（驗證碼）、UseQuantity（核銷數量）
> 2. AES 加密 → 計算 CheckMacValue（`HashKey={HashKey}&Data={加密Data}&HashIV={HashIV}` → SHA256 → 大寫）
> 3. POST 到 `https://ecticket-stage.ecpay.com.tw/Voucher/UseVoucher`
> 4. 三重驗證回應：TransCode===1 → 解密 → 驗 CMV → RtnCode===1
>
> **功能 B — 退票（消費者申請退票）**：
> 1. 組合 Data 明文：MerchantID、VoucherNo、ReturnQuantity
> 2. 同樣 AES 加密 + CMV
> 3. POST 到 `https://ecticket-stage.ecpay.com.tw/Voucher/ReturnVoucher`
> 4. 三重驗證回應
>
> **C++ 實作提示**：
> - AES-128-CBC：使用 OpenSSL `EVP_EncryptInit_ex()` + `EVP_CIPHER_aes_128_cbc()`
> - PKCS7 padding：OpenSSL 的 EVP 預設啟用 PKCS padding
> - URL encode：自行實作 percent-encoding（只做 percent-encode，不轉小寫，不做 .NET 替換）
> - SHA256：使用 OpenSSL `EVP_DigestInit_ex()` + `EVP_MD_sha256()`
> - HTTP POST：使用 libcurl `curl_easy_setopt()`
>
> **關鍵規則**：
> - CheckMacValue 驗證用 `CRYPTO_memcmp()`（timing-safe），不可用 `strcmp()` 或 `==`
> - 回應三重檢查：TransCode → CMV → RtnCode
> - CMV 公式為 `HashKey + Data + HashIV`（與 AIO 不同）
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

## 跨服務整合

### 26. 完整電商流程：收款 + 開發票 + 出貨（Python Django）

> 我要用 Python Django 建立完整的電商整合流程：消費者付款 → 自動開立電子發票 → 建立物流出貨，全部使用 ECPay 服務。
>
> **整合服務**：
> 1. ECPay AIO 金流（信用卡收款）
> 2. ECPay 電子發票 B2C（自動開立）
> 3. ECPay 國內物流（超商取貨）
>
> **程式語言**：Python 3.10+，Django 4.x
>
> **測試帳號**（三組不同帳號！各服務帳號不可混用）：
> - 金流：MerchantID=3002607, HashKey=pwFHCqoQZGmho4w6, HashIV=EkRm7iFT261dpevs（SHA256）
> - 發票：MerchantID=2000132, HashKey=ejCk326UnaZWKisg, HashIV=q9jcZX8Ib9LM8wYk（AES）
> - 物流：MerchantID=2000132, HashKey=5294y06JbISpM5x9, HashIV=v77hoKGq4kWxNNIS（MD5）
>
> **測試信用卡號**：4311-9522-2222-2222（安全碼任意三碼，有效期限任意未來月年，3D 驗證碼 1234）
>
> **完整流程實作**：
>
> **Phase 1 — 收款（AIO 金流）**：
> - 端點：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
> - 加密：CheckMacValue SHA256（金流版 URL encode）
> - 消費者在綠界頁面付款 → ReturnURL 收到 RtnCode=1 → 標記訂單已付款
>
> **Phase 2 — 開發票（B2C 電子發票）**：
> - 端點：`https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue`
> - 加密：AES-128-CBC（AES 版 URL encode，不轉小寫，不做 .NET 替換）
> - 付款成功後自動觸發開立發票 → 取得 InvoiceNo
> - RqHeader 需 Revision: "3.0.0"
>
> **Phase 3 — 出貨（國內物流超商取貨）**：
> - 端點：`https://logistics-stage.ecpay.com.tw/Express/Create`
> - 加密：CheckMacValue **MD5**（金流版 URL encode，但雜湊用 MD5 不是 SHA256）
> - 發票開立成功後建立物流訂單 → 取得物流編號
>
> **關鍵注意事項**：
> - 三個服務使用三組不同的 MerchantID + HashKey + HashIV，絕不可混用
> - 三個服務使用三種不同的加密方式：金流=SHA256、發票=AES、物流=MD5
> - 金流和物流的 URL encode 是同一種（金流版：轉小寫 + .NET 替換）
> - 發票的 URL encode 是另一種（AES 版：只做 urlencode，不轉小寫，不做 .NET 替換）
> - 建議每個 Phase 獨立一個 Django app（payments、invoices、logistics）
> - 使用 Django signals 或 Celery 串接各 Phase（付款成功 → 觸發開發票 → 觸發出貨）
>
> **關鍵規則**：
> - 帳號不可混用！金流/發票/物流各用自己的帳號
> - CheckMacValue 驗證用 `hmac.compare_digest()`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 27. 訂閱制 SaaS：定期扣款 + 自動開發票（Node.js Express）

> 我要用 Node.js Express 建立 SaaS 訂閱制的完整流程：會員訂閱 → 每月自動扣款 → 每月自動開發票。
>
> **整合服務**：
> 1. ECPay AIO 金流 — 定期定額信用卡扣款
> 2. ECPay 電子發票 B2C — 每期扣款成功後自動開立
>
> **程式語言**：Node.js 18+，Express，TypeScript
>
> **測試帳號**：
> - 金流：MerchantID=3002607, HashKey=pwFHCqoQZGmho4w6, HashIV=EkRm7iFT261dpevs（SHA256）
> - 發票：MerchantID=2000132, HashKey=ejCk326UnaZWKisg, HashIV=q9jcZX8Ib9LM8wYk（AES）
>
> **測試信用卡號**：4311-9522-2222-2222（安全碼任意三碼，有效期限任意未來月年，3D 驗證碼 1234）
>
> **完整流程**：
>
> **Phase 1 — 訂閱建立（首次付款）**：
> - 端點：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
> - 額外參數：PeriodAmount、PeriodType=M（月）、Frequency=1（每1月）、ExecTimes=12（執行12次）、PeriodReturnURL
> - 首次扣款結果通知到 ReturnURL
>
> **Phase 2 — 每月自動扣款通知**：
> - 綠界每月自動扣款後 POST 到 PeriodReturnURL
> - 驗證 CheckMacValue → 確認 RtnCode=1 → 回傳 `1|OK`
> - 此時觸發開立當月發票
>
> **Phase 3 — 自動開立發票**：
> - 端點：`https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue`
> - 加密：AES-128-CBC
> - 每次收到扣款成功通知 → 自動呼叫發票 API 開立
> - RqHeader 需 Revision: "3.0.0"
>
> **注意事項**：
> - 金流帳號（3002607）和發票帳號（2000132）不同，不可混用
> - PeriodReturnURL 和 ReturnURL 是兩個不同的 callback（首期用 ReturnURL，後續用 PeriodReturnURL）
> - 如需停止訂閱，呼叫 `POST https://payment-stage.ecpay.com.tw/Cashier/CreditCardPeriodAction`（Action=ReAuth）
> - 測試環境只會有首期扣款，不會真的每月扣
>
> **關鍵規則**：
> - 金流用 CheckMacValue SHA256，發票用 AES-128-CBC，兩者 URL encode 方式不同
> - CheckMacValue 驗證用 `crypto.timingSafeEqual()`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

## 除錯與排查

### 28. CheckMacValue 驗證失敗（錯誤碼 10400002）

> 我的 ECPay AIO 串接遇到 CheckMacValue 驗證失敗，回傳錯誤碼 10400002，請幫我排查問題。
>
> **問題描述**：
> - 我在串接 ECPay AIO 全方位金流
> - 送出付款請求後，綠界回傳 CheckMacValue 驗證失敗（錯誤碼 10400002）
> - 使用的加密方式：SHA256
>
> **我的環境**：
> - 測試帳號：MerchantID=3002607, HashKey=pwFHCqoQZGmho4w6, HashIV=EkRm7iFT261dpevs
> - 測試環境 URL：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **請幫我逐項檢查以下常見錯誤原因**：
>
> 1. **參數排序**：是否有按照參數名稱 A-Z（不分大小寫）正確排序？（CheckMacValue 本身不參與排序）
> 2. **URL encode 版本錯誤**：AIO 金流必須用「金流版 ecpayUrlEncode」：
>    - 先 percent-encode（RFC 3986）
>    - 再全部轉小寫（整個 encode 後的字串轉小寫，不是只有 %XX 轉小寫）
>    - 再做 .NET 字元替換：%2d→-、%5f→_、%2e→.、%21→!、%2a→*、%28→(、%29→)、%20→+
>    - **常見錯誤**：忘了轉小寫、忘了 .NET 替換、或用了 AES 版的 URL encode
> 3. **HashKey/HashIV 拼接位置**：格式為 `HashKey=xxxx&參數A=值A&...&HashIV=xxxx`（HashKey 在最前，HashIV 在最後，用 & 連接）
> 4. **SHA256 後未轉大寫**：SHA256 計算結果必須轉為全大寫
> 5. **EncryptType 參數**：必須帶 `EncryptType=1`（表示 SHA256，不帶或為 0 會被當作 MD5 計算）
> 6. **特殊字元處理**：ItemName 或 TradeDesc 中有特殊字元（`&`、`#`、`+`、`%` 等）嗎？這些字元 encode 後可能影響排序
> 7. **MerchantTradeNo 格式**：是否超過 20 字元？是否包含特殊字元？
> 8. **帳號混用**：確認 HashKey/HashIV 是金流帳號的（pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs），不是物流或發票的
>
> **請提供**：
> - 一個正確的 CheckMacValue 計算函式（用我的程式語言）
> - 一個測試範例：給定固定參數，計算出的 CheckMacValue 值（讓我比對自己的實作是否正確）
> - 如果可能，幫我檢查我現有程式碼的問題

---

### 29. AES 解密回來是亂碼 — 站內付 2.0 回呼解不開

> 我的 ECPay 站內付 2.0（ECPG）的 callback 收到的資料解密後是亂碼或解密失敗，請幫我排查。
>
> **問題描述**：
> - 站內付 2.0 的 OrderResultURL callback 收到了 POST 請求
> - 嘗試 AES 解密 Data 欄位，結果是亂碼或 JSON parse 失敗
> - 有時候解出一半正常一半亂碼
>
> **我的環境**：
> - 測試帳號：MerchantID=3002607, HashKey=pwFHCqoQZGmho4w6, HashIV=EkRm7iFT261dpevs
> - 加密方式：AES-128-CBC
>
> **請幫我逐項檢查以下常見錯誤原因**：
>
> 1. **AES 解密步驟順序**（必須嚴格按照此順序）：
>    - 收到的 Data 字串 → Base64 decode → AES-128-CBC decrypt(key=HashKey, iv=HashIV) → 去除 PKCS7 padding → URL decode → 得到 JSON 字串 → JSON parse
>    - **常見錯誤**：漏了 URL decode 步驟（很多人直接解密後就 JSON parse，忘了中間有一層 URL encode）
>
> 2. **URL decode 版本**：AES 服務的 URL encode/decode 只是標準的 percent-encoding（不轉小寫、不做 .NET 替換）。如果你在解密時用了「金流版」的反向操作（加回 .NET 替換），會得到亂碼
>
> 3. **Key 和 IV 的類型**：
>    - Key = HashKey 直接作為 16 bytes 的 UTF-8 字串（`pwFHCqoQZGmho4w6` 剛好 16 字元 = 128 bits）
>    - IV = HashIV 直接作為 16 bytes 的 UTF-8 字串（`EkRm7iFT261dpevs`）
>    - **常見錯誤**：把 Key/IV 當 hex 解碼，或用 SHA256(Key) 當 key
>
> 4. **Padding 模式**：必須是 PKCS7（又稱 PKCS5）。不是 ZeroPadding、不是 NoPadding
>
> 5. **Base64 decode 失敗**：確認收到的 Data 字串確實是 valid Base64（沒有多餘空白或換行）
>
> 6. **帳號混用**：確認用的是 ECPG 帳號的 HashKey/HashIV（站內付 2.0 帳號與 AIO 相同都是 3002607），不是物流或發票的
>
> **請提供**：
> - 一個正確的 AES 解密函式（用我的程式語言）
> - 解密步驟的詳細說明（含每一步的中間結果示意）
> - 如果可能，幫我檢查我現有程式碼的問題

---

### 30. 站內付 2.0 的 CreatePayment API 回傳 404

> 我的 ECPay 站內付 2.0 的 GetToken 成功了，但 CreatePayment API 一直回傳 HTTP 404，請幫我排查。
>
> **問題描述**：
> - GetTokenbyTrade 呼叫成功，有拿到 Token
> - 前端 SDK 渲染信用卡表單成功，消費者可以填卡號
> - 但呼叫 CreatePayment 時得到 HTTP 404 Not Found
>
> **我的環境**：
> - 測試帳號：MerchantID=3002607, HashKey=pwFHCqoQZGmho4w6, HashIV=EkRm7iFT261dpevs
>
> **最常見原因（90% 機率）— Domain 用錯了**：
>
> ECPay 站內付 2.0 使用**兩個不同的 domain**，混用就是 404：
>
> | API | 正確 Domain | 錯誤 Domain（會 404） |
> |-----|------------|---------------------|
> | GetTokenbyTrade | `ecpg-stage.ecpay.com.tw` | ecpayment-stage... |
> | CreatePayment | `ecpg-stage.ecpay.com.tw` | ecpayment-stage... |
> | QueryTrade | `ecpayment-stage.ecpay.com.tw` | ecpg-stage... |
> | DoAction（退款） | `ecpayment-stage.ecpay.com.tw` | ecpg-stage... |
>
> **請幫我確認**：
> 1. CreatePayment 的完整 URL 是否為 `https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment`（注意是 **ecpg**，不是 ecpayment）
> 2. 是否在程式碼中不小心把所有 API 都指向了同一個 domain
> 3. URL 路徑是否正確（`/Merchant/CreatePayment`，注意大小寫）
>
> **其他可能原因**：
> - HTTP Method 錯誤：必須是 POST（不是 GET）
> - Content-Type 錯誤：必須是 application/json
> - URL 拼字錯誤（多了空白、少了斜線、大小寫錯）
>
> **請幫我**：
> - 確認我的 API URL 配置是否正確
> - 提供站內付 2.0 所有 API 的完整 URL 對照表（包含 domain + path）
> - 如果不是 domain 問題，幫我排查其他可能原因

---

### 31. ReturnURL Callback 一直收不到（PHP Laravel）

> 我的 ECPay AIO 串接（PHP Laravel），付款流程正常完成（消費者有看到付款成功頁面），但我的伺服器一直收不到 ReturnURL 的 Callback 通知，請幫我排查。
>
> **程式語言**：PHP 8.1+，Laravel 10
>
> **問題描述**：
> - 消費者在綠界付款頁面完成付款，有看到「交易成功」畫面
> - 但我的伺服器 ReturnURL 端點完全沒有收到任何 POST 請求
> - 伺服器 IP 已確認開放，防火牆也調整過了
>
> **我的環境**：
> - 測試帳號：MerchantID=3002607, HashKey=pwFHCqoQZGmho4w6, HashIV=EkRm7iFT261dpevs
> - 測試環境 URL：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **請幫我逐項檢查以下常見原因**：
>
> 1. **ReturnURL 是否為公開可訪問的 URL**：
>    - 不可以是 `localhost`、`127.0.0.1`、或內網 IP（192.168.x.x、10.x.x.x）
>    - 綠界的伺服器需要能連到你的 URL（Server-to-Server，非瀏覽器跳轉）
>    - 測試時可用 ngrok 或類似工具將本機暴露為公開 URL
>
> 2. **Port 限制**：
>    - 正式環境只接受 port 80（HTTP）和 443（HTTPS）
>    - 測試環境較寬鬆但也建議用 80/443
>    - 如果你的服務跑在 3000、8080 等 port，必須用 reverse proxy 轉到 80/443
>
> 3. **HTTPS 憑證問題**：
>    - 如果 ReturnURL 是 HTTPS，憑證必須是有效的（非自簽章）
>    - 測試環境可能接受 HTTP，但正式環境強制 HTTPS + 有效憑證
>
> 4. **ReturnURL 回傳格式錯誤**：
>    - ReturnURL 接收到通知後必須回傳純文字 `1|OK`（僅這 4 個字元）
>    - 如果回傳 HTML、JSON、或 HTTP 500 錯誤，綠界會視為通知失敗
>    - 綠界收到非 `1|OK` 回應會重試，但重試次數有限，全部失敗後就不再通知
>
> 5. **防火牆/雲端安全群組**：
>    - 確認伺服器防火牆允許來自綠界 IP 的入站連線
>    - 雲端平台（AWS、GCP、Azure）需在 Security Group/防火牆規則中開放
>    - 綠界的通知 IP 不固定，建議開放 443/80 port 給所有來源（或洽綠界取得 IP 白名單）
>
> 6. **ReturnURL 與 OrderResultURL / ClientRedirectURL 搞混**：
>    - ReturnURL：Server-to-Server 背景通知（消費者看不到），用於更新訂單狀態
>    - ClientRedirectURL：消費者瀏覽器跳轉（前端跳轉），用於顯示結果頁面
>    - 站內付 2.0 用的是 OrderResultURL（功能同 ReturnURL）
>    - 如果你只設了 ClientRedirectURL 沒設 ReturnURL��就收不到背景通知
>
> 7. **綠界測試環境特性**：
>    - 測試環境的 Callback 可能有延遲（數秒到數分鐘）
>    - 可登入測試特店後台 `https://vendor-stage.ecpay.com.tw` 查看交易記錄確認付款是否成功
>
> **請幫我**：
> - 提供一個 Laravel route 的 ReturnURL handler 實作（PHP），確保格式正確回傳 `1|OK`
> - 確認 Laravel 的 CSRF middleware 是否會阻擋綠界的 POST（需排除 ReturnURL route）
> - 提供一個用 ngrok 測試 Callback 的步驟說明
> - 如果以上都正確，幫我排查其他可能原因

---

### 32. Callback 驗證失敗與重試機制處理（C++）

> 我的 ECPay 串接（C++ 後端，使用 libcurl + OpenSSL），ReturnURL/ServerReplyURL 的 Callback 收到了但 CheckMacValue 驗證一直失敗，而且同一筆通知被綠界重複發送了好幾次，請幫我排查。
>
> **程式語言**：C++17（libcurl + OpenSSL + nlohmann/json）
>
> **問題描述**：
> - Callback 有收到（伺服器 log 有紀錄）
> - 但我計算的 CheckMacValue 和綠界傳來的對不上
> - 因為驗證失敗我沒有回傳 `1|OK`，導致綠界一直重送
>
> **我的環境**：
> - 測試帳號（金流）：MerchantID=3002607, HashKey=pwFHCqoQZGmho4w6, HashIV=EkRm7iFT261dpevs
> - 測試帳號（物流）：MerchantID=2000132, HashKey=5294y06JbISpM5x9, HashIV=v77hoKGq4kWxNNIS
>
> **請幫我逐項檢查以下常見原因**：
>
> 1. **用錯帳號的 HashKey/HashIV**：
>    - 金流 Callback 必須用金流帳號的 HashKey/HashIV 驗證
>    - 物��� Callback 必須用物流帳號的 HashKey/HashIV 驗證
>    - 發票 Callback 必須用發票帳號的 HashKey/HashIV 驗證
>    - 混用就會永遠驗證失敗
>
> 2. **加密演算法用錯**：
>    - AIO 金流 Callback：CheckMacValue 用 **SHA256**
>    - 國內物流 Callback：CheckMacValue 用 **MD5**
>    - 用 SHA256 驗證物流 Callback（應該用 MD5）就會失敗，反之亦然
>
> 3. **Callback 參數中的 CheckMacValue 不參與計算**：
>    - 收到的 POST 參數中有 CheckMacValue 欄位
>    - 計算時要把 CheckMacValue 這個參數本身排除，用其他所有參數計算
>    - 然後比對計算結果和收到的 CheckMacValue 值
>
> 4. **URL encode 版本**：
>    - 金流和物流的 CheckMacValue 都用「金流版 URL encode」：percent-encode → 全轉小寫 → .NET 替換
>    - 常見錯誤：驗證 Callback 時忘了做 URL encode，或用了 AES 版的 encode
>
> 5. **參數值的空白/編碼問題**：
>    - 某些 Web 框架會自動 decode POST body，確認你拿到的是 decoded 值
>    - 排序時參數名稱的大小寫必須保持原樣（ECPay 參數名稱是 PascalCase）
>
> 6. **重試機制說明**：
>    - 綠界在你未回傳 `1|OK` 時會重試通知
>    - AIO 金流：間隔約 1 分鐘、5 分鐘、30 分鐘...最多重試約 10 次
>    - 重複通知的內容完全相同（同一筆交易），你的系統需做冪等處理（收過的訂單不重複處理）
>    - 正確回傳 `1|OK` 後綠界就不會再重送
>
> **請幫我**：
> - 提供完整的 C++ Callback 驗證函式，包含：接收 POST body → 解析參數 → 排除 CheckMacValue → A-Z 排序 → 金流版 URL encode → SHA256 計算 → `CRYPTO_memcmp()` timing-safe 比較
> - 提供 C++ 的冪等處理最佳實踐（如何防止重複通知導致重複處理）
> - 提供 C++ 的金流版 URL encode 完整實作（percent-encode → 全轉小寫 → .NET 字元替換）
> - 如果以上都正確，幫我把收到的原始 POST body 列印出來以便進一步除錯

---

## 上線與環境切換

### 33. 測試環境切換到正式環境

> 我的 ECPay 串接在測試環境都通過了，現在要切換到正式環境上線。請給我完整的切換檢查清單和步驟。
>
> **我目前使用的服務**（請根據我實際串接的服務調整）：
> - AIO 金流
> - 電子發票 B2C
> - 國內物流超商取貨
>
> **請提供完整的上線切換清單，包含**：
>
> **1. Domain 切換**（所有 URL 移除 `-stage`）：
> - 金流：`payment-stage.ecpay.com.tw` → `payment.ecpay.com.tw`
> - 站內付 Token：`ecpg-stage.ecpay.com.tw` → `ecpg.ecpay.com.tw`
> - 站內付查詢：`ecpayment-stage.ecpay.com.tw` → `ecpayment.ecpay.com.tw`
> - 物流：`logistics-stage.ecpay.com.tw` → `logistics.ecpay.com.tw`
> - 發票：`einvoice-stage.ecpay.com.tw` → `einvoice.ecpay.com.tw`
> - 電子票證：`ecticket-stage.ecpay.com.tw` → `ecticket.ecpay.com.tw`
>
> **2. 帳號切換**：
> - 測試帳號（公開共用）→ 正式帳號（向綠界申請取得，每個商家���立）
> - 正式的 MerchantID / HashKey / HashIV 必須以環境變數管理，禁止寫在程式碼中
>
> **3. 安全檢查**：
> - [ ] HashKey/HashIV 是否已從程式碼中移除，改用環境變數？
> - [ ] 是否已確認正式環境的 ReturnURL / OrderResultURL / ServerReplyURL 是正確的正式 URL（非 localhost）？
> - [ ] 所有 Callback URL 是否使用 HTTPS？（正式環境強制 TLS 1.2+）
> - [ ] CheckMacValue 驗證是否使用 timing-safe 比較？
> - [ ] 是否有完整的錯誤處理和 logging？
> - [ ] 是否已移除所有測試用的 hardcoded 值（測試卡號、測試帳號等）？
>
> **4. 功能測試**：
> - [ ] 使用正式帳號在正式環境做一筆小額測試交易
> - [ ] 確認 Callback 可正確接收並處理
> - [ ] 確認退款流程可正常執行
> - [ ] 若有發票，確認正式環境發票開立成功
>
> **5. 其他注意事項**：
> - 正式環境的信用卡付款會有 3D Secure 驗證（2025/8 起強制實施）
> - Port 限制：正式環境 Callback URL 只接受 80 和 443 port
> - 正式環境禁止使用 iframe 嵌入付款頁面
> - 建議先以小額（如 1 元）測試正式環境是否正確串通

---

## 特殊場景

### 34. POS 刷卡機門市串接（Node.js）

> 我要在門市使用 ECPay 實體 POS 刷卡機串接收款。
>
> **服務**：ECPay POS 刷卡機
> **場景**：實體門市，店員在 POS 系統輸入金額後，消費者在刷卡機上刷卡/感應付款
> **程式語言**：Node.js（POS 應用程式端）
>
> **POS 串接與線上金流的差異**：
> - POS 使用 TCP/IP 或 COM Port 通訊協議（非標準 HTTP API）
> - 需要搭配綠界提供的實體刷卡終端機設備
> - 不使用 CheckMacValue 或 AES 加密（加密方式依 POS 規格文件）
>
> **請幫我實作以下功能**：
> 1. 建立與 POS 終端機的通訊連線（TCP/IP socket 或 Serial port）
> 2. 組合交易電文（依綠界 POS 規格）
> 3. 發送交易請求到 POS 終端機
> 4. 接收並解析交易回應
> 5. 查詢交易狀態
> 6. 取消/退貨功能
>
> **測試帳號**（POS 串接需聯繫綠界取得測試設備和帳號）：
> - MerchantID：需向綠界申請 POS 專用帳號
> - 測試需使用綠界提供的測試刷卡機
>
> **重要參考**：
> - 技術規格詳見 `references/Payment/刷卡機POS串接規格.md`
> - POS 無官方 SDK PHP 範例（`scripts/SDK_PHP/example/` 無對應範例）
> - 需自行依照通訊協議規格實作
>
> **注意事項**：
> - POS 串接前須先向綠界申請，取得測試設備和相關文件
> - 部分雲端 POS 廠商有封裝為 HTTP/HTTPS API，具體依合作廠商而定
> - 感應支付（NFC）、Apple Pay 實體感應付款，取決於刷卡機型號支援度

---

### 35. 直播收款網址（Ruby）

> 我要用 Ruby 實作 ECPay 直播收款功能，在直播中讓觀眾直接透過付款連結下單付款。
>
> **服務**：ECPay 收款網址（直播收款場景）
> **場景**：直播主在直播中介紹商品，觀眾點擊收款連結直接付款
> **程式語言**：Ruby 3.2+
>
> **收款網址的運作方式**：
> - 在綠界後台建立「收款網址」，取得一個短連結
> - 將短連結分享給觀眾（貼在直播聊天室、留言區）
> - 觀眾點擊連結 → 進入綠界付款頁面 → 完成付款
> - 你的系統接收付款結果 Callback
>
> **實作方式**：
> - 收款網址主要透過綠界**後台手動建立**或透過 **API 建立**
> - 如需程式化建立，使用 AIO 金流 API 搭配 `ClientRedirectURL` 導回指定頁面
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **請幫我實作**：
> 1. 一個 API endpoint 可以動態產生付款連結（輸入商品名稱、金額 → 產生綠界付款頁面 URL）
> 2. 使用 ECPay AIO 金流，以 Form POST 方式建立交易，但設定 ClientRedirectURL 讓消費者付款後跳回指定頁面
> 3. 實作 ReturnURL callback 接收付款結果
> 4. 產生可分享的短連結或 QR Code
>
> **測試環境 URL**：`https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
>
> **注意事項**：
> - ClientRedirectURL 是消費者瀏覽器付款後跳轉的 URL（前端跳轉，非 Server callback）
> - ReturnURL 是 Server-to-Server 通知（非瀏覽器跳轉）
> - 兩者功能不同：ClientRedirectURL 顯示結果頁，ReturnURL 更新後台訂單狀態
> - 直播場景建議金額設定為固定或從有限選項中選擇
>
> **關鍵規則**：
> - CheckMacValue（SHA256，金流版 URL encode）
> - 驗證用 `OpenSSL.secure_compare()`
> - 禁止 HashKey/HashIV 出現在前端或版本控制中

---

### 36. Apple Pay 收款（Swift iOS App）

> 我要在 iOS App 中使用 ECPay 收取 Apple Pay 付款。
>
> **服務**：ECPay AIO 金流 — Apple Pay
> **場景**：iOS App 中消費者使用 Apple Pay（Face ID / Touch ID）快速付款
> **程式語言**：Swift 5.9+，iOS 16+
>
> **Apple Pay 透過 ECPay 的兩種方式**：
>
> **方式 A — AIO 金流網頁版（推薦，較簡單）**：
> - 使用 WKWebView 載入 ECPay AIO 付款頁面
> - ECPay 付款頁面本身支援 Apple Pay 按鈕（消費者在綠界頁面點選 Apple Pay）
> - App 端只需處理 WebView 和 callback
> - 不需要 App 端做 Apple Pay 的 PKPaymentAuthorizationController
>
> **方式 B — 站內付 2.0 + Apple Pay SDK（進階）**：
> - App 端先取得 Apple Pay 的 Payment Token（透過 PassKit framework）
> - 再將 Payment Token 傳給後端
> - 後端用 ECPG API 帶入 Apple Pay Token 完成交易
> - 此方式需要 Apple Developer 帳號設定 Merchant ID 和 Payment Processing Certificate
>
> **測試帳號**：
> - MerchantID：3002607
> - HashKey：pwFHCqoQZGmho4w6
> - HashIV：EkRm7iFT261dpevs
>
> **方式 A 實作步驟（推薦）**：
> 1. 後端建立 AIO 訂單（同範例 1），ChoosePayment 設為 ALL 或 Credit
> 2. 後端產生自動 submit 的 HTML Form 頁面
> 3. App 使用 WKWebView 載入該頁面
> 4. 消費者在綠界付款頁面可以選擇 Apple Pay 付款
> 5. 後端 ReturnURL 接收付款結果
>
> **iOS App 注意事項**：
> - WKWebView 需啟用 JavaScript
> - Apple Pay 在 WKWebView 中可能需要特殊處理（視 iOS 版本）
> - 如使用方式 B，需在 Apple Developer Portal 註冊 Merchant ID
> - 測試 Apple Pay 需使用 Apple 的 Sandbox 測試環境和測試卡片
> - **嚴禁在 LINE/Facebook 內建 WebView 中使用**
>
> **關鍵規則**：
> - AES 或 CheckMacValue 加密在後端執行
> - App 端不存放 HashKey/HashIV
> - 禁止 HashKey/HashIV 出現在 App 程式碼中

---

## 附錄

### 各服務帳號速查表

| 服務 | MerchantID | HashKey | HashIV | 加密方式 |
|------|-----------|---------|--------|---------|
| 金流 AIO | 3002607 | pwFHCqoQZGmho4w6 | EkRm7iFT261dpevs | SHA256 |
| 站內付 2.0 | 3002607 | pwFHCqoQZGmho4w6 | EkRm7iFT261dpevs | AES |
| 國內物流 | 2000132 | 5294y06JbISpM5x9 | v77hoKGq4kWxNNIS | MD5 |
| 電子發票 | 2000132 | ejCk326UnaZWKisg | q9jcZX8Ib9LM8wYk | AES |
| 電子票證（特店） | 3085676 | 7b53896b742849d3 | 37a0ad3c6ffa428b | AES+CMV |

> **嚴禁帳號混用！** 金流、物流、發票使用不同的 MerchantID 和 HashKey/HashIV。

### 兩種 URL Encode 差異

| | 金流版 ecpayUrlEncode（用於 CheckMacValue） | AES 版 aesUrlEncode（用於 AES 加密） |
|---|---|---|
| 步驟 1 | percent-encode | percent-encode |
| 步驟 2 | 全轉小寫 | **（無）** |
| 步驟 3 | .NET 字元替換（%2d→- 等） | **（無）** |
| 使用場景 | AIO 金流、國內物流 | 站內付 2.0、發票、全方位物流、跨境物流、電子票證 |

### 測試信用卡號

| 卡號 | 用途 |
|------|------|
| 4311-9522-2222-2222 | 一般測試（VISA 國內） |
| 4311-9511-1111-1111 | 一般測試（VISA 國內） |
| 4938-1777-7777-7777 | 永豐分期測試（30 期） |
| 安全碼 | 任意三碼（如 222） |
| 有效期限 | 任意大於當前月年的值 |
| 3D 驗證碼 | 1234（測試環境固定，不需簡訊） |
