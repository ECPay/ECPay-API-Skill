> 對應 ECPay API 版本 | 最後更新：2026-03

# 硬體與專用服務指引

> 本文件涵蓋兩種非標準 HTTP API 的特殊服務：**POS 刷卡機**（使用 TCP/IP 或 COM Port 通訊）與**直播收款**（後台建立收款網址，僅需實作 Callback）。
>
> 需要標準線上金流 HTTP API → [guides/01 AIO](./01-payment-aio.md)、[guides/02 ECPG](./02-payment-ecpg.md)

## 目錄

- [POS 刷卡機串接指引](#pos-刷卡機串接指引)
- [直播收款指引](#直播收款指引)

---

## POS 刷卡機串接指引

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/刷卡機POS串接規格.md` — 生成程式碼前請 web_fetch 取得最新規格

> **本指南為初步整合指引**，提供 POS 串接的概念說明和官方文件索引。
> POS 刷卡機為硬體設備，需搭配特定通訊協議，詳細技術規格見
> `references/Payment/刷卡機POS串接規格.md`。
>
> **注意**：POS 串接主要使用 TCP/IP 或 COM Port 直連協議（非 ECPay 標準 HTTP API），部分雲端 POS 廠商亦有封裝為 HTTP/HTTPS 通訊（見下方通訊方式說明）。`scripts/SDK_PHP/example/` 目錄中無對應範例。請參照 `references/Payment/刷卡機POS串接規格.md` 的協議規格自行實作。

### 概述

POS 刷卡機串接適用於實體門市、餐飲業等需要現場刷卡收款的場景。與線上金流（AIO/ECPG）不同，POS 整合需要搭配綠界提供的實體刷卡終端機。

### 適用場景

- 實體門市收款（零售、百貨）
- 餐飲業桌邊結帳
- 市集、展覽臨時收款
- 需要感應支付（NFC）的場景

### 與線上金流的差異

| 面向 | 線上金流 (AIO/ECPG) | POS 刷卡機 |
|------|---------------------|-----------|
| 付款方式 | 消費者在網頁/App 操作 | 消費者在實體終端刷卡/感應 |
| 整合方式 | HTTP API | 串接 POS 通訊協定 |
| 加密方式 | CheckMacValue / AES | 依 POS 規格 |
| 退款 | API 操作 | 可透過 POS 或後台 |

### 串接架構

```
POS 終端（刷卡機）           POS 應用（你的系統）          ECPay Server
      │                           │                         │
      ├─ 刷卡/感應 ──────────────→│                         │
      │                           ├─ 授權請求 ────────────→│
      │                           │                         ├─ 銀行授權
      │                           │←── 授權回應 ────────────┤
      │←── 交易結果 ──────────────┤                         │
      │                           │                         │
      │   （營業結束）              │                         │
      │                           ├─ 結帳批次上傳 ─────────→│
      │                           │←── 上傳結果 ────────────┤
```

### 通訊方式

| 方式 | 說明 | 適用場景 |
|------|------|---------|
| TCP/IP | 透過區域網路連線 | 固定門市、多台 POS |
| COM Port (RS-232) | 串接埠直連 | 傳統 POS 機台 |
| HTTP/HTTPS（廠商協議封裝） | 透過設備廠商封裝的網路通訊 | 雲端 POS、行動收款 |

### HTTP 協議說明

POS 刷卡機使用**專用 POS 串接協定**（TCP/IP 或 COM Port），**不使用標準 HTTP API**。

與線上金流（AIO CMV-SHA256 / ECPG AES-JSON）的 HTTP 協議不同，POS 整合需搭配綠界提供的實體刷卡終端機，並依照 POS 通訊規格進行串接。

詳細規格請參考官方文件：`references/Payment/刷卡機POS串接規格.md`

> 若需要線上收款的 HTTP API 串接，請參考 [guides/19-http-protocol-reference.md](./19-http-protocol-reference.md)。

### API 端點概覽

POS 刷卡機的串接規格包含：

- 交易授權（一般交易 / 分期 / 紅利）
- 交易查詢
- 取消交易（void）
- 退款
- 結帳批次上傳
- 終端機參數下載

### 基本交易流程

#### 1. 授權請求

```
發送授權請求 → 等待回應（timeout 建議 60 秒）
├── 授權成功：記錄授權碼、交易序號
├── 授權失敗：顯示失敗原因
└── 逾時：發送查詢確認交易狀態
```

#### 2. 結帳批次上傳

每日營業結束後，需將當日所有交易上傳至綠界進行對帳：

```
1. 收集當日所有授權成功的交易
2. 組成批次上傳資料
3. 呼叫結帳 API
4. 確認上傳結果（逐筆比對）
```

### 常見整合注意事項

| 項目 | 說明 |
|------|------|
| 心跳機制 | 建議每 30 秒與終端機保持心跳，確認連線狀態 |
| 斷線重連 | 網路中斷後自動重連，交易中斷需查詢確認狀態 |
| 結帳批次 | 每日必須結帳，未結帳的交易隔日可能無法請款 |
| 簽單列印 | 授權成功後需列印簽單，供消費者簽名 |
| 交易逾時 | 授權請求建議 timeout 60 秒，逾時後查詢確認 |

### 完整規格文件

詳細的 POS 通訊協議規格、指令定義、錯誤碼對照,請參閱官方技術文件索引:

> 📄 `references/Payment/刷卡機POS串接規格.md`(13 個外部文件 URL;**實作前務必 web_fetch** 取得最新規格)

### POS 整合快速指引

| 步驟 | 說明 |
|:---:|------|
| 1 | 向綠界申請 POS 刷卡機服務（需獨立申請，非金流帳號自動包含）|
| 2 | 取得 POS 專用 API 金鑰（與一般金流帳號的 HashKey/HashIV **不同**）|
| 3 | 驗證機制:POS 專用驗證方式,**與 AIO 金流的 CheckMacValue SHA256 不同**。⚠️ 由於 POS 廠商/型號多樣,各廠商實作的雜湊演算法可能不同(部分資料顯示為 SHA-1 Hash,但各型號請以 POS 廠商提供的規格文件為準)。正式實作前**必須 web_fetch `references/Payment/刷卡機POS串接規格.md` 中列出的官方 URL**,確認所使用 POS 型號的確切驗證機制 |
| 4 | API 端點：`https://pos.ecpay.com.tw/`（詳見 `references/Payment/刷卡機POS串接規格.md`）|
| 5 | 測試：使用綠界提供的模擬 POS 終端機或 API 測試工具 |

> ℹ️ **POS API 完整規格**：使用 AI 工具時，請 web_fetch `references/Payment/刷卡機POS串接規格.md` 中的 URL 取得最新參數表。

### 相關文件

- 線上信用卡收款：[guides/01-payment-aio.md](./01-payment-aio.md)
- 嵌入式付款：[guides/02-payment-ecpg.md](./02-payment-ecpg.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)

---

## 直播收款指引

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/直播主收款網址串接技術文件.md` — 生成程式碼前請 web_fetch 取得最新規格

> **本指南為初步整合指引**，說明直播收款的概念與 ReturnURL Callback 處理方式。
> 收款網址透過綠界後台建立（無建立 API），特店僅需實作付款成功 Callback。
> 詳細規格見 `references/Payment/直播主收款網址串接技術文件.md`。
>
> **注意**：本指南的 PHP 範例為依 `references/Payment/直播主收款網址串接技術文件.md` 手寫，非官方 SDK 範例。

### 概述

直播收款網址服務讓直播主或賣家能快速產生收款連結，在直播過程中分享給觀眾完成付款。適用於直播電商、網紅經濟等即時銷售場景。

### 適用場景

- 直播電商（Facebook Live、YouTube Live、蝦皮直播等）
- 網紅 / KOL 即時帶貨
- 社群團購分享收款連結
- 不需要自建購物車的輕量收款

### 核心流程

```
1. 賣家透過綠界後台建立收款網址（收款工具 → 實況主收款功能）
2. 直播中分享收款連結給觀眾
3. 觀眾點擊連結完成付款
4. 綠界以 JSON POST 通知 ReturnURL（付款成功 Callback）
5. 賣家透過後台查詢訂單與管理收款網址
```

### HTTP 協議速查（ReturnURL Callback）

| 項目 | 規格 |
|------|------|
| 協議模式 | AES-JSON + CMV（ECTicket 式）— 詳見 [guides/19-http-protocol-reference.md](./19-http-protocol-reference.md) |
| Callback 格式 | JSON POST (`application/json`) |
| 認證 | **AES-128-CBC**(Block Mode,PKCS7 padding)加密 Data 欄位;Key=HashKey(16 bytes ASCII)、IV=HashIV(16 bytes ASCII)— 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| 回應結構 | 三層 JSON(TransCode → 解密 Data → RtnCode) |
| CMV 驗證 | ECTicket 式 CheckMacValue(SHA256);與電子票證相同公式(詳見 [guides/09 §CheckMacValue 計算](./09-ecticket.md))。⚠️ **直播收款 CMV 公式尚未完整公開**,實作前**必須 web_fetch `references/Payment/直播主收款網址串接技術文件.md` 中列出的官方 URL** 確認最新規格。驗證使用 timing-safe 比對(`hash_equals` / `crypto.timingSafeEqual` / `hmac.compare_digest`) |
| 回應 | 純文字 `1\|OK` |

### 建立收款網址（後台操作）

> ⚠️ 直播收款的收款網址透過綠界後台設定（收款工具 → 實況主收款功能），**無建立訂單 API**。特店僅需實作 ReturnURL（付款成功通知 Callback）。

> ℹ️ 查詢、關閉、紀錄等功能透過綠界後台操作，目前無對應 API。

測試帳號使用 ECPG 同組（MerchantID `3002607` / HashKey `pwFHCqoQZGmho4w6` / HashIV `EkRm7iFT261dpevs`）。

### ReturnURL 付款成功通知參數

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/直播主收款網址串接技術文件.md`

消費者付款完成後，綠界以 JSON POST 傳送付款結果至 ReturnURL。僅通知付款成功，付款失敗或待付款不通知。

#### 外層 Response（JSON）

| 參數 | 型別 | 說明 |
|------|------|------|
| MerchantID | String(10) | 特店編號 |
| RpHeader | JSON | 回傳資料（含 Timestamp） |
| TransCode | Int | 回傳代碼（`1` = 傳輸成功，需再檢查 Data 內的 RtnCode） |
| TransMsg | String(200) | 回傳訊息 |
| Data | String | 加密資料（AES 解密後為 JSON） |
| CheckMacValue | String | 檢查碼（ECTicket 式 CMV，需 timing-safe 驗證） |

#### Data 解密後參數（JSON）

| 參數 | 型別 | 說明 |
|------|------|------|
| RtnCode | Int | 交易狀態（`1` = 成功） |
| RtnMsg | String(200) | 回應訊息 |
| MerchantID | String(10) | 特店編號 |
| DonateURL | String(200) | 贊助收款網址 |
| SimulatePaid | Int | 是否為模擬付款（`0` = 非模擬，`1` = 模擬付款，勿出貨） |
| OrderInfo | JSON | 訂單資訊（見下方） |
| PatronName | String(100) | 贊助者名稱 |
| PatronNote | String(100) | 贊助者留言 |
| LivestreamURL | String(200) | 直播頻道網址（未設定時回傳空字串） |

#### OrderInfo 子物件

| 參數 | 型別 | 說明 |
|------|------|------|
| MerchantTradeNo | String(20) | 特店交易編號 |
| TradeNo | String(20) | 綠界交易編號（請保存與 MerchantTradeNo 的關聯） |
| TradeAmt | Int | 交易金額 |
| TradeDate | String(20) | 訂單成立時間（yyyy/MM/dd HH:mm:ss） |
| PaymentType | String(20) | 付款方式（參考附錄回覆付款方式一覽表） |
| PaymentDate | String(20) | 付款時間（yyyy/MM/dd HH:mm:ss） |
| ChargeFee | Number | 手續費 |
| TradeStatus | String(8) | 交易狀態（`0` = 未付款，`1` = 已付款） |

> ⚠️ **注意事項**：
> - 當 `SimulatePaid` 為 `1` 時，為廠商後台模擬付款測試，綠界不會撥款，**請勿出貨**。
> - 特店務必判斷 `RtnCode` 是否為 `1`，非 `1` 時請勿出貨。
> - ATM/超商條碼/超商代碼的付款時間以銀行與超商告知綠界的銷帳時間為主。
> - 若未正確回應 `1|OK`，系統會隔 5~15 分鐘後重發，當天最多重複 4 次。

### 收款網址管理（後台操作）

- **有效期限**：建立時於後台設定，過期後消費者無法付款
- **狀態管理**：透過綠界後台關閉不再需要的收款網址（無對應 API）
- **付款通知**：消費者付款後，綠界以 **JSON POST** 傳送到 ReturnURL（格式同 ECTicket：AES 解密 Data + ECTicket-式 CMV，**非** AIO Form POST 格式）；驗證順序：TransCode === 1 → AES 解密 → CMV timing-safe 驗證 → RtnCode === 1（**整數**）；**回應純文字 `1|OK`**（⚠️ 與電子票證不同 — ECTicket 回 AES+CMV，直播收款回純文字）

### 完整規格文件

詳細的 API 參數和串接流程，請參閱官方技術文件：

> 📄 `references/Payment/直播主收款網址串接技術文件.md`（7 個外部文件 URL）

### 直播收款快速指引

| 步驟 | 說明 |
|:---:|------|
| 1 | 向綠界申請「直播收款」功能（需獨立申請）|
| 2 | 使用 **AES-JSON + CheckMacValue（ECTicket 式 SHA256）**雙重驗證（與電子票證相同協議）|
| 3 | 外層 JSON 需包含 `CheckMacValue`（公式同 ECTicket，非 AIO）|
| 4 | **ECPay 通知格式**（你收到的）：JSON POST，Data 欄位為 AES 加密 JSON，外層含 ECTicket 式 CheckMacValue（與電子票證相同協議）。**你必須回應**：純文字 `1\|OK`（⚠️ 與電子票證不同——電子票證回應需 AES 加密 JSON + CheckMacValue）|
| 5 | API 端點：`https://ecpayment.ecpay.com.tw/`（測試環境：`https://ecpayment-stage.ecpay.com.tw/`）（詳見 `references/Payment/直播主收款網址串接技術文件.md`）|

> ⚠️ **直播收款的協議混淆**：雖然直播收款的 Callback 使用 ECTicket 式 CheckMacValue，但回應格式為 `1|OK`（不同於電子票證的 AES JSON 回應）。
>
> ℹ️ **完整規格**：請 web_fetch `references/Payment/直播主收款網址串接技術文件.md` 中的 URL 取得最新參數表。

### 相關文件

- 標準金流串接：[guides/01-payment-aio.md](./01-payment-aio.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
